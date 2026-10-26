<?php

namespace App\Services\Notifications;

use App\Models\GuestSession;
use App\Models\Notification;
use App\Models\NotificationAttempt;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NotificationService
{
    public function queueForUser(
        User $recipient,
        string $channel,
        string $type,
        string $sourceKey,
        ?string $title = null,
        ?string $body = null,
        array $payload = [],
    ): Notification {
        return $this->queue(
            (int) $recipient->getKey(),
            null,
            $channel,
            $type,
            $sourceKey,
            $title,
            $body,
            $payload,
        );
    }

    public function queueForGuest(
        GuestSession $guest,
        string $channel,
        string $type,
        string $sourceKey,
        ?string $title = null,
        ?string $body = null,
        array $payload = [],
    ): Notification {
        if ($guest->converted_to_user_id !== null || $guest->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'guestSession' => 'Sesi tamu tidak lagi aktif untuk menerima notifikasi.',
            ]);
        }

        return $this->queue(
            null,
            (int) $guest->getKey(),
            $channel,
            $type,
            $sourceKey,
            $title,
            $body,
            $payload,
        );
    }

    public function markRead(User $recipient, int $notificationId): Notification
    {
        $notification = Notification::query()
            ->whereKey($notificationId)
            ->where('recipient_user_id', $recipient->getKey())
            ->where('channel', Notification::CHANNEL_DATABASE)
            ->where('status', Notification::STATUS_SENT)
            ->firstOrFail();

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now('UTC')]);
        }

        return $notification->refresh();
    }

    public function markAllRead(User $recipient): int
    {
        return Notification::query()
            ->where('recipient_user_id', $recipient->getKey())
            ->where('channel', Notification::CHANNEL_DATABASE)
            ->where('status', Notification::STATUS_SENT)
            ->whereNull('read_at')
            ->update(['read_at' => now('UTC')]);
    }

    private function queue(
        ?int $recipientUserId,
        ?int $guestSessionId,
        string $channel,
        string $type,
        string $sourceKey,
        ?string $title,
        ?string $body,
        array $payload,
    ): Notification {
        if (($recipientUserId === null) === ($guestSessionId === null)) {
            throw ValidationException::withMessages([
                'recipient' => 'Notifikasi harus memiliki tepat satu penerima.',
            ]);
        }

        if (
            ! in_array($channel, [
                Notification::CHANNEL_DATABASE,
                Notification::CHANNEL_EMAIL,
                Notification::CHANNEL_WHATSAPP,
            ], true)
            || trim($type) === ''
            || mb_strlen($type) > 100
            || trim($sourceKey) === ''
            || mb_strlen($sourceKey) > 500
            || ($title !== null && mb_strlen($title) > 180)
        ) {
            throw ValidationException::withMessages([
                'notification' => 'Channel, jenis, sumber, atau judul notifikasi tidak valid.',
            ]);
        }

        $recipientKey = $recipientUserId !== null ? 'user:' . $recipientUserId : 'guest:' . $guestSessionId;
        $deduplicationKey = hash('sha256', implode('|', [
            $recipientKey,
            $channel,
            trim($type),
            trim($sourceKey),
        ]));

        $existing = Notification::query()->where('deduplication_key', $deduplicationKey)->first();

        if ($existing) {
            return $existing;
        }

        $databaseChannel = $channel === Notification::CHANNEL_DATABASE;
        $now = now('UTC');

        try {
            return DB::transaction(function () use (
                $recipientUserId,
                $guestSessionId,
                $channel,
                $type,
                $title,
                $body,
                $payload,
                $deduplicationKey,
                $databaseChannel,
                $now,
            ): Notification {
                $notification = Notification::query()->create([
                    'recipient_user_id' => $recipientUserId,
                    'guest_session_id' => $guestSessionId,
                    'channel' => $channel,
                    'notification_type' => trim($type),
                    'title' => $title !== null ? trim($title) : null,
                    'body' => $body,
                    'payload_json' => $this->sanitizePayload($payload),
                    'deduplication_key' => $deduplicationKey,
                    'status' => $databaseChannel ? Notification::STATUS_SENT : Notification::STATUS_QUEUED,
                    'available_at' => $now,
                    'sent_at' => $databaseChannel ? $now : null,
                    'read_at' => null,
                    'last_error_code' => null,
                    'last_error_message' => null,
                ]);

                if ($databaseChannel) {
                    NotificationAttempt::query()->create([
                        'notification_id' => $notification->getKey(),
                        'attempt_number' => 1,
                        'provider' => 'database',
                        'status' => 'succeeded',
                        'provider_message_id' => null,
                        'request_metadata_json' => [
                            'notification_id' => $notification->getKey(),
                            'channel' => Notification::CHANNEL_DATABASE,
                        ],
                        'response_metadata_json' => ['stored' => true],
                        'error_code' => null,
                        'error_message' => null,
                        'started_at' => $now,
                        'finished_at' => $now,
                        'created_at' => $now,
                    ]);
                }

                return $notification;
            }, attempts: 3);
        } catch (QueryException $exception) {
            return Notification::query()->where('deduplication_key', $deduplicationKey)->firstOrFail();
        }
    }

    private function sanitizePayload(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            if (preg_match('/password|secret|token|authorization|cookie|account.?number|email|phone|ip.?address|user.?agent/i', (string) $key)) {
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitizePayload($value);
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
