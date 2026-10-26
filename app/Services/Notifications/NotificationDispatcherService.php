<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\NotificationDeliveryContract;
use App\Models\Notification;
use App\Models\NotificationAttempt;
use Illuminate\Support\Facades\DB;
use Throwable;

class NotificationDispatcherService
{
    public function __construct(private readonly NotificationDeliveryContract $delivery)
    {
    }

    /**
     * @return array{scanned:int,sent:int,failed:int,skipped:int}
     */
    public function dispatchQueued(int $limit = 100): array
    {
        $ids = Notification::query()
            ->whereIn('channel', [Notification::CHANNEL_EMAIL, Notification::CHANNEL_WHATSAPP])
            ->whereIn('status', [Notification::STATUS_QUEUED, Notification::STATUS_FAILED])
            ->where('available_at', '<=', now('UTC'))
            ->whereDoesntHave('attempts', fn ($query) => $query->where('attempt_number', '>=', 5))
            ->orderBy('available_at')
            ->limit(max(1, min($limit, 500)))
            ->pluck('id');

        $result = ['scanned' => $ids->count(), 'sent' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($ids as $id) {
            $claim = $this->claim((int) $id);

            if (! $claim) {
                $result['skipped']++;

                continue;
            }

            $notification = Notification::query()
                ->with(['recipient', 'guestSession'])
                ->whereKey($claim['notification_id'])
                ->first();

            if (! $notification) {
                $result['skipped']++;

                continue;
            }

            try {
                $response = $this->delivery->send($notification);
                $this->markSucceeded($claim['attempt_id'], $notification, $response);
                $result['sent']++;
            } catch (Throwable $exception) {
                $this->markFailed($claim['attempt_id'], $notification);
                $result['failed']++;
            }
        }

        return $result;
    }

    private function claim(int $notificationId): ?array
    {
        return DB::transaction(function () use ($notificationId): ?array {
            $notification = Notification::query()->whereKey($notificationId)->lockForUpdate()->first();

            if (
                ! $notification
                || ! in_array($notification->status, [Notification::STATUS_QUEUED, Notification::STATUS_FAILED], true)
                || $notification->available_at->isFuture()
            ) {
                return null;
            }

            $lastAttempt = NotificationAttempt::query()
                ->where('notification_id', $notification->getKey())
                ->orderByDesc('attempt_number')
                ->lockForUpdate()
                ->first();
            $attemptNumber = (int) ($lastAttempt?->attempt_number ?? 0) + 1;

            if ($attemptNumber > 5) {
                return null;
            }

            $now = now('UTC');
            $notification->update([
                'status' => Notification::STATUS_PROCESSING,
                'last_error_code' => null,
                'last_error_message' => null,
            ]);

            $attempt = NotificationAttempt::query()->create([
                'notification_id' => $notification->getKey(),
                'attempt_number' => $attemptNumber,
                'provider' => 'pending',
                'status' => 'started',
                'provider_message_id' => null,
                'request_metadata_json' => [
                    'notification_id' => $notification->getKey(),
                    'channel' => $notification->channel,
                    'notification_type' => $notification->notification_type,
                ],
                'response_metadata_json' => null,
                'error_code' => null,
                'error_message' => null,
                'started_at' => $now,
                'finished_at' => null,
                'created_at' => $now,
            ]);

            return [
                'notification_id' => $notification->getKey(),
                'attempt_id' => $attempt->getKey(),
                'attempt_number' => $attemptNumber,
            ];
        }, attempts: 3);
    }

    private function markSucceeded(int $attemptId, Notification $notification, array $response): void
    {
        DB::transaction(function () use ($attemptId, $notification, $response): void {
            $lockedNotification = Notification::query()->whereKey($notification->getKey())->lockForUpdate()->firstOrFail();
            $attempt = NotificationAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();

            if ($lockedNotification->status === Notification::STATUS_SENT) {
                return;
            }

            $provider = is_string($response['provider'] ?? null) ? mb_substr($response['provider'], 0, 80) : 'unknown';
            $messageId = is_string($response['provider_message_id'] ?? null)
                ? mb_substr($response['provider_message_id'], 0, 160)
                : null;
            $metadata = is_array($response['response_metadata'] ?? null)
                ? $this->sanitizeMetadata($response['response_metadata'])
                : [];

            $attempt->update([
                'provider' => $provider,
                'status' => 'succeeded',
                'provider_message_id' => $messageId,
                'response_metadata_json' => $metadata,
                'finished_at' => now('UTC'),
            ]);

            $lockedNotification->update([
                'status' => Notification::STATUS_SENT,
                'sent_at' => now('UTC'),
                'last_error_code' => null,
                'last_error_message' => null,
            ]);
        }, attempts: 3);
    }

    private function markFailed(int $attemptId, Notification $notification): void
    {
        DB::transaction(function () use ($attemptId, $notification): void {
            $lockedNotification = Notification::query()->whereKey($notification->getKey())->lockForUpdate()->firstOrFail();
            $attempt = NotificationAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();
            $attemptNumber = (int) $attempt->attempt_number;
            $backoffMinutes = min(60 * (2 ** max(0, $attemptNumber - 1)), 1440);

            $attempt->update([
                'provider' => 'configured-adapter',
                'status' => 'failed',
                'error_code' => 'delivery_failed',
                'error_message' => 'Delivery failed; sensitive provider details were not stored.',
                'finished_at' => now('UTC'),
            ]);

            $lockedNotification->update([
                'status' => Notification::STATUS_FAILED,
                'available_at' => now('UTC')->addMinutes($backoffMinutes),
                'last_error_code' => 'delivery_failed',
                'last_error_message' => 'Delivery failed. The system will retry with bounded backoff when eligible.',
            ]);
        }, attempts: 3);
    }

    private function sanitizeMetadata(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            if (preg_match('/password|secret|token|authorization|cookie|account.?number|email|phone|ip.?address|user.?agent/i', (string) $key)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
