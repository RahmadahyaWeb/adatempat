<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Venue;
use App\Models\VenuePromotion;
use App\Services\Booking\AvailabilityService;
use App\Services\Subscriptions\SubscriptionEligibilityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

class PaymentWebhookProcessor
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly SubscriptionEligibilityService $subscriptions,
    ) {
    }

    /**
     * The caller must verify the signature and normalize provider-specific payloads first.
     *
     * @param array{event_id:string,gateway_transaction_id:string,status:string,paid_at:?string,gateway_fee_amount:int,amount:int} $event
     * @return array{status:string,duplicate:bool}
     */
    public function process(string $provider, array $event): array
    {
        $recordResult = $this->recordEvent($provider, $event);
        $eventRecord = $recordResult['event'];

        if ($recordResult['duplicate'] && ! $recordResult['retry']) {
            return ['status' => $eventRecord->processing_status, 'duplicate' => true];
        }

        try {
            $payment = Payment::query()
                ->where('gateway_provider', $provider)
                ->where('gateway_transaction_id', $event['gateway_transaction_id'])
                ->first();

            if (! $payment || $payment->payable_type !== Payment::TYPE_BOOKING) {
                $eventRecord->update([
                    'processing_status' => PaymentWebhookEvent::STATUS_FAILED,
                    'processing_notes' => 'No matching booking payment was found for this provider transaction.',
                    'processed_at' => now('UTC'),
                ]);

                return ['status' => PaymentWebhookEvent::STATUS_FAILED, 'duplicate' => false];
            }

            $booking = $payment->booking()->first();

            if (! $booking) {
                $eventRecord->update([
                    'payment_id' => $payment->getKey(),
                    'processing_status' => PaymentWebhookEvent::STATUS_FAILED,
                    'processing_notes' => 'Payment is not linked to an existing booking.',
                    'processed_at' => now('UTC'),
                ]);

                return ['status' => PaymentWebhookEvent::STATUS_FAILED, 'duplicate' => false];
            }

            $venueId = $booking->venue_id;
            $decision = DB::transaction(function () use ($provider, $event, $eventRecord, $payment, $booking, $venueId): string {
                Venue::query()->whereKey($venueId)->lockForUpdate()->firstOrFail();

                $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
                $lockedBooking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
                $lockedHold = $lockedBooking->holds()->orderByDesc('id')->lockForUpdate()->first();
                $lockedEvent = PaymentWebhookEvent::query()->whereKey($eventRecord->getKey())->lockForUpdate()->firstOrFail();

                $lockedEvent->update(['payment_id' => $lockedPayment->getKey()]);

                if ($lockedPayment->status === Payment::STATUS_PAID && $lockedBooking->status === Booking::STATUS_CONFIRMED) {
                    $this->markEventProcessed($lockedEvent, 'Duplicate paid state ignored; financial state is already final.');

                    return PaymentWebhookEvent::STATUS_PROCESSED;
                }

                if (! $lockedHold) {
                    $this->markReconciliation($lockedPayment, $lockedBooking, null, $lockedEvent, 'Booking payment has no associated hold record.');

                    return PaymentWebhookEvent::STATUS_PROCESSED;
                }

                if (in_array($event['status'], [Payment::STATUS_FAILED, Payment::STATUS_EXPIRED], true)) {
                    if ($lockedPayment->status === Payment::STATUS_PAID) {
                        $this->markReconciliation($lockedPayment, $lockedBooking, $lockedHold, $lockedEvent, 'A non-paid webhook conflicts with an already-paid payment.');
                        return PaymentWebhookEvent::STATUS_PROCESSED;
                    }

                    $paymentStatus = $event['status'] === Payment::STATUS_EXPIRED
                        ? Payment::STATUS_EXPIRED
                        : Payment::STATUS_FAILED;

                    $lockedPayment->update([
                        'status' => $paymentStatus,
                        'qris_payload' => null,
                    ]);
                    $lockedBooking->update([
                        'status' => Booking::STATUS_EXPIRED,
                        'slot_released_at' => now('UTC'),
                    ]);
                    $lockedHold->update(['status' => BookingHold::STATUS_RELEASED]);

                    AuditLog::query()->create([
                        'actor_user_id' => null,
                        'actor_role' => 'system',
                        'action' => 'payment.webhook_terminal_failure',
                        'auditable_type' => Payment::class,
                        'auditable_id' => $lockedPayment->getKey(),
                        'reason' => 'Gateway confirmed the payment failed or expired.',
                        'before_state_json' => ['status' => $lockedPayment->getOriginal('status')],
                        'after_state_json' => ['status' => $paymentStatus],
                        'ip_hash' => null,
                        'created_at' => now('UTC'),
                    ]);

                    $this->markEventProcessed($lockedEvent, null);

                    return PaymentWebhookEvent::STATUS_PROCESSED;
                }

                $paidAt = isset($event['paid_at']) && $event['paid_at'] !== null
                    ? CarbonImmutable::parse($event['paid_at'])->utc()
                    : null;

                if (
                    $paidAt === null
                    || (int) $event['amount'] !== (int) $lockedPayment->amount
                    || (int) $lockedBooking->snapshot_actual_paid_amount !== (int) $lockedPayment->amount
                ) {
                    $this->markReconciliation($lockedPayment, $lockedBooking, $lockedHold, $lockedEvent, 'Paid event is missing a verified timestamp or has an amount mismatch.');

                    return PaymentWebhookEvent::STATUS_PROCESSED;
                }

                $deadline = CarbonImmutable::instance($lockedHold->expires_at)->utc();
                $now = CarbonImmutable::now('UTC');

                if (
                    $paidAt->greaterThan($deadline)
                    || $now->greaterThanOrEqualTo($deadline)
                    || $lockedHold->status !== BookingHold::STATUS_ACTIVE
                    || ! in_array($lockedPayment->status, [Payment::STATUS_PENDING, Payment::STATUS_VERIFYING], true)
                    || ! in_array($lockedBooking->status, [Booking::STATUS_PENDING_PAYMENT, Booking::STATUS_PAYMENT_VERIFYING], true)
                ) {
                    $this->markReconciliation($lockedPayment, $lockedBooking, $lockedHold, $lockedEvent, 'Payment was reported after the hold deadline or the checkout state is no longer eligible for automatic confirmation.');

                    return PaymentWebhookEvent::STATUS_PROCESSED;
                }

                $venue = Venue::query()
                    ->with(['business', 'category', 'availabilityRules'])
                    ->whereKey($lockedBooking->venue_id)
                    ->firstOrFail();

                $subscription = $this->subscriptions->eligibleForBusiness($venue->business, $now);
                $available = $subscription !== null && $this->availability->isAvailable(
                    $venue,
                    CarbonImmutable::instance($lockedBooking->booking_start)->utc(),
                    CarbonImmutable::instance($lockedBooking->booking_end)->utc(),
                    $lockedHold->getKey(),
                    $lockedBooking->getKey(),
                );

                if (! $available) {
                    $this->markReconciliation($lockedPayment, $lockedBooking, $lockedHold, $lockedEvent, 'Slot or subscription eligibility changed before payment confirmation.');

                    return PaymentWebhookEvent::STATUS_PROCESSED;
                }

                $promotionIds = array_values(array_filter([
                    $lockedBooking->owner_promotion_id,
                    $lockedBooking->platform_promotion_id,
                ]));
                $promotions = VenuePromotion::query()
                    ->whereIn('id', $promotionIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($promotions->count() !== count(array_unique($promotionIds))) {
                    $this->markReconciliation($lockedPayment, $lockedBooking, $lockedHold, $lockedEvent, 'A promotion referenced by the booking no longer exists.');

                    return PaymentWebhookEvent::STATUS_PROCESSED;
                }

                foreach ($promotions as $promotion) {
                    if ($promotion->max_quota !== null && $promotion->used_count >= $promotion->max_quota) {
                        $this->markReconciliation($lockedPayment, $lockedBooking, $lockedHold, $lockedEvent, 'Promotion quota was consumed by another confirmed booking.');
                        return PaymentWebhookEvent::STATUS_PROCESSED;
                    }
                }

                foreach ($promotions as $promotion) {
                    $promotion->increment('used_count');
                }

                $fee = max(0, (int) $event['gateway_fee_amount']);
                $refundPolicy = is_array($venue->refund_policy_json) && $venue->refund_policy_json !== []
                    ? $venue->refund_policy_json
                    : [
                        'source' => 'platform_default',
                        'version' => 1,
                        'customer_cancellation' => [
                            'minimum_minutes_before_start' => 60,
                            'refund_percentage' => 100,
                        ],
                        'owner_cancellation' => ['refund_percentage' => 100],
                    ];

                $lockedPayment->update([
                    'status' => Payment::STATUS_PAID,
                    'paid_at' => $paidAt,
                    'gateway_fee_amount' => $fee,
                ]);
                $lockedBooking->update([
                    'status' => Booking::STATUS_CONFIRMED,
                    'confirmed_at' => $now,
                    'snapshot_actual_paid_amount' => (int) $lockedPayment->amount,
                    'snapshot_gateway_fee_amount' => $fee,
                    'snapshot_net_owner_entitlement' => max(
                        0,
                        (int) $lockedBooking->snapshot_base_amount
                            - (int) $lockedBooking->snapshot_owner_discount_amount
                            - $fee,
                    ),
                    'snapshot_refund_policy_json' => $refundPolicy,
                ]);
                $lockedHold->update(['status' => BookingHold::STATUS_CONVERTED]);

                AuditLog::query()->create([
                    'actor_user_id' => null,
                    'actor_role' => 'system',
                    'action' => 'booking.confirmed_after_payment',
                    'auditable_type' => Booking::class,
                    'auditable_id' => $lockedBooking->getKey(),
                    'reason' => 'Verified gateway paid event matched the active hold deadline and available slot.',
                    'before_state_json' => ['status' => $booking->status],
                    'after_state_json' => [
                        'status' => Booking::STATUS_CONFIRMED,
                        'actual_paid_amount' => (int) $lockedPayment->amount,
                        'gateway_fee_amount' => $fee,
                        'net_owner_entitlement' => $lockedBooking->snapshot_net_owner_entitlement,
                    ],
                    'ip_hash' => null,
                    'created_at' => now('UTC'),
                ]);

                $this->markEventProcessed($lockedEvent, null);

                return PaymentWebhookEvent::STATUS_PROCESSED;
            }, attempts: 3);

            return ['status' => $decision, 'duplicate' => false];
        } catch (Throwable $exception) {
            $eventRecord->refresh()->update([
                'processing_status' => PaymentWebhookEvent::STATUS_FAILED,
                'processing_notes' => 'Webhook processing failed; retry may safely re-enter idempotent processing.',
                'processed_at' => now('UTC'),
            ]);

            return ['status' => PaymentWebhookEvent::STATUS_FAILED, 'duplicate' => false];
        }
    }

    private function recordEvent(string $provider, array $event): array
    {
        try {
            $record = DB::transaction(function () use ($provider, $event): array {
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
                        'gateway_transaction_id' => $event['gateway_transaction_id'],
                        'status' => $event['status'],
                        'paid_at' => $event['paid_at'],
                        'amount' => $event['amount'],
                        'gateway_fee_amount' => $event['gateway_fee_amount'],
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

            return $record;
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

    private function markReconciliation(
        Payment $payment,
        Booking $booking,
        ?BookingHold $hold,
        PaymentWebhookEvent $event,
        string $reason,
    ): void {
        $payment->update([
            'status' => Payment::STATUS_RECONCILIATION_REQUIRED,
            'qris_payload' => null,
        ]);
        $booking->update(['status' => Booking::STATUS_RECONCILIATION_REQUIRED]);

        if ($hold && in_array($hold->status, [BookingHold::STATUS_ACTIVE, BookingHold::STATUS_EXPIRED, BookingHold::STATUS_RELEASED], true)) {
            $hold->update(['status' => BookingHold::STATUS_RECONCILING]);
        }

        AuditLog::query()->create([
            'actor_user_id' => null,
            'actor_role' => 'system',
            'action' => 'payment.reconciliation_required',
            'auditable_type' => Payment::class,
            'auditable_id' => $payment->getKey(),
            'reason' => $reason,
            'before_state_json' => ['status' => $payment->getOriginal('status')],
            'after_state_json' => ['status' => Payment::STATUS_RECONCILIATION_REQUIRED],
            'ip_hash' => null,
            'created_at' => now('UTC'),
        ]);

        $this->markEventProcessed($event, $reason);
    }

    private function markEventProcessed(PaymentWebhookEvent $event, ?string $notes): void
    {
        $event->update([
            'processing_status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'processing_notes' => $notes,
            'processed_at' => now('UTC'),
        ]);
    }
}
