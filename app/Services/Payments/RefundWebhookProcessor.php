<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Refund;
use App\Models\Venue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

class RefundWebhookProcessor
{
    public function __construct(private readonly RefundService $refunds)
    {
    }

    /**
     * The provider signature must already be verified and the payload normalized.
     *
     * @param array{event_id:string,gateway_refund_id:string,status:string} $event
     * @return array{status:string,duplicate:bool}
     */
    public function process(string $provider, array $event): array
    {
        $record = $this->recordEvent($provider, $event);
        $eventRecord = $record['event'];

        if ($record['duplicate'] && ! $record['retry']) {
            return ['status' => $eventRecord->processing_status, 'duplicate' => true];
        }

        try {
            $refund = Refund::query()
                ->where('gateway_refund_id', $event['gateway_refund_id'])
                ->whereHas('payment', fn ($query) => $query->where('gateway_provider', $provider))
                ->first();

            if (! $refund) {
                $eventRecord->update([
                    'processing_status' => PaymentWebhookEvent::STATUS_FAILED,
                    'processing_notes' => 'No matching refund was found for this provider refund ID.',
                    'processed_at' => now('UTC'),
                ]);

                return ['status' => PaymentWebhookEvent::STATUS_FAILED, 'duplicate' => false];
            }

            $eventRecord->update(['payment_id' => $refund->payment_id]);

            if ($event['status'] === Refund::STATUS_SUCCEEDED) {
                $this->refunds->markSucceeded($refund, $event['gateway_refund_id']);
                $eventRecord->update([
                    'processing_status' => PaymentWebhookEvent::STATUS_PROCESSED,
                    'processing_notes' => null,
                    'processed_at' => now('UTC'),
                ]);

                return ['status' => PaymentWebhookEvent::STATUS_PROCESSED, 'duplicate' => false];
            }

            $booking = $refund->booking()->first();
            $venueId = $booking?->venue_id;

            DB::transaction(function () use ($provider, $event, $eventRecord, $refund, $booking, $venueId): void {
                if ($venueId !== null) {
                    Venue::query()->whereKey($venueId)->lockForUpdate()->firstOrFail();
                }

                $payment = Payment::query()->whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();
                $lockedRefund = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();

                if ($lockedRefund->status === Refund::STATUS_SUCCEEDED) {
                    $eventRecord->update([
                        'processing_status' => PaymentWebhookEvent::STATUS_PROCESSED,
                        'processing_notes' => 'A conflicting non-success refund event was ignored after successful settlement.',
                        'processed_at' => now('UTC'),
                    ]);

                    return;
                }

                $before = $lockedRefund->status;
                $next = $event['status'] === Refund::STATUS_FAILED
                    ? Refund::STATUS_FAILED
                    : Refund::STATUS_PROCESSING;

                $lockedRefund->update(['status' => $next]);

                AuditLog::query()->create([
                    'actor_user_id' => null,
                    'actor_role' => 'system',
                    'action' => $next === Refund::STATUS_FAILED ? 'refund.webhook_failed' : 'refund.webhook_processing',
                    'auditable_type' => Refund::class,
                    'auditable_id' => $lockedRefund->getKey(),
                    'reason' => $next === Refund::STATUS_FAILED
                        ? 'Gateway confirmed the refund failed; the obligation remains open for retry or manual review.'
                        : 'Gateway reports the refund is still processing.',
                    'before_state_json' => ['status' => $before, 'payment_id' => $payment->getKey()],
                    'after_state_json' => ['status' => $next, 'payment_id' => $payment->getKey()],
                    'ip_hash' => null,
                    'created_at' => now('UTC'),
                ]);

                $eventRecord->update([
                    'processing_status' => PaymentWebhookEvent::STATUS_PROCESSED,
                    'processing_notes' => null,
                    'processed_at' => now('UTC'),
                ]);
            }, attempts: 3);

            return ['status' => PaymentWebhookEvent::STATUS_PROCESSED, 'duplicate' => false];
        } catch (Throwable $exception) {
            $eventRecord->refresh()->update([
                'processing_status' => PaymentWebhookEvent::STATUS_FAILED,
                'processing_notes' => 'Refund webhook processing failed; retry may safely re-enter idempotent processing.',
                'processed_at' => now('UTC'),
            ]);

            return ['status' => PaymentWebhookEvent::STATUS_FAILED, 'duplicate' => false];
        }
    }

    private function recordEvent(string $provider, array $event): array
    {
        try {
            return DB::transaction(function () use ($provider, $event): array {
                $existing = PaymentWebhookEvent::query()
                    ->where('gateway_provider', $provider)
                    ->where('gateway_event_id', $event['event_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $retry = $existing->processing_status === PaymentWebhookEvent::STATUS_FAILED;
                    $existing->update([
                        'duplicate_delivery_count' => $existing->duplicate_delivery_count + 1,
                        'last_duplicate_received_at' => now('UTC'),
                        'processing_status' => $retry ? PaymentWebhookEvent::STATUS_RECEIVED : $existing->processing_status,
                        'processing_notes' => $retry ? null : $existing->processing_notes,
                    ]);

                    return ['event' => $existing->refresh(), 'duplicate' => true, 'retry' => $retry];
                }

                $created = PaymentWebhookEvent::query()->create([
                    'gateway_provider' => $provider,
                    'gateway_event_id' => $event['event_id'],
                    'payment_id' => null,
                    'signature_valid' => true,
                    'payload_json' => [
                        'event_type' => 'refund',
                        'gateway_refund_id' => $event['gateway_refund_id'],
                        'status' => $event['status'],
                    ],
                    'processing_status' => PaymentWebhookEvent::STATUS_RECEIVED,
                    'duplicate_delivery_count' => 0,
                    'last_duplicate_received_at' => null,
                    'processing_notes' => null,
                    'received_at' => now('UTC'),
                    'processed_at' => null,
                ]);

                return ['event' => $created, 'duplicate' => false, 'retry' => false];
            });
        } catch (QueryException $exception) {
            $existing = PaymentWebhookEvent::query()
                ->where('gateway_provider', $provider)
                ->where('gateway_event_id', $event['event_id'])
                ->firstOrFail();

            $retry = $existing->processing_status === PaymentWebhookEvent::STATUS_FAILED;
            $existing->update([
                'duplicate_delivery_count' => $existing->duplicate_delivery_count + 1,
                'last_duplicate_received_at' => now('UTC'),
                'processing_status' => $retry ? PaymentWebhookEvent::STATUS_RECEIVED : $existing->processing_status,
                'processing_notes' => $retry ? null : $existing->processing_notes,
            ]);

            return ['event' => $existing->refresh(), 'duplicate' => true, 'retry' => $retry];
        }
    }
}
