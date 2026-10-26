<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayContract;
use App\Exceptions\Payments\PaymentGatewayException;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class PaymentService
{
    public function __construct(private readonly PaymentGatewayContract $gateway)
    {
    }

    /**
     * This method is intentionally called only after BookingEngineService commits its local
     * transaction. No gateway API call is made while venue/promotion row locks are held.
     */
    public function initializeBookingPayment(Payment $payment): Payment
    {
        $payment->refresh();

        if (
            $payment->payable_type !== Payment::TYPE_BOOKING
            || $payment->status !== Payment::STATUS_PENDING
        ) {
            return $payment;
        }

        $booking = $payment->booking()->firstOrFail();
        $hold = $booking->holds()
            ->where('status', BookingHold::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->firstOrFail();
        $deadline = CarbonImmutable::instance($hold->expires_at)->utc();

        if ($deadline->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
            return $this->expireBeforeGatewayCall($payment, $booking, $hold);
        }

        try {
            $charge = $this->gateway->createQrisCharge($payment, $deadline);
        } catch (PaymentGatewayException $exception) {
            if ($exception->isDefinitiveFailure()) {
                return $this->failDefinitively($payment, $booking, $hold, $exception->getMessage());
            }

            return $this->markVerifying($payment, $booking, $hold, $exception->getMessage());
        } catch (Throwable $exception) {
            return $this->markVerifying($payment, $booking, $hold, 'Gateway response is uncertain; retry/query using the same idempotency key.');
        }

        if (! $this->validChargeResponse($charge)) {
            return $this->markVerifying($payment, $booking, $hold, 'Gateway returned an incomplete or invalid charge response.');
        }

        if (CarbonImmutable::now('UTC')->greaterThanOrEqualTo($deadline)) {
            try {
                $status = $this->gateway->getChargeStatus($payment->fresh());
            } catch (Throwable $exception) {
                return $this->markReconciliationRequired($payment, $booking, $hold, 'Charge response arrived after the hold deadline and gateway status is uncertain.');
            }

            if (in_array($status['status'] ?? null, [Payment::STATUS_FAILED, Payment::STATUS_EXPIRED], true)) {
                return $this->failDefinitively($payment, $booking, $hold, 'Gateway confirmed the charge failed or expired after the hold deadline.');
            }

            return $this->markReconciliationRequired($payment, $booking, $hold, 'Charge response arrived after the hold deadline; do not expose QRIS before reconciliation.');
        }

        return DB::transaction(function () use ($payment, $booking, $hold, $charge, $deadline): Payment {
            $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $lockedBooking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $lockedHold = BookingHold::query()->whereKey($hold->getKey())->lockForUpdate()->firstOrFail();

            if (
                $lockedPayment->status !== Payment::STATUS_PENDING
                || $lockedBooking->status !== Booking::STATUS_PENDING_PAYMENT
                || $lockedHold->status !== BookingHold::STATUS_ACTIVE
                || $lockedHold->expires_at->lessThanOrEqualTo(now('UTC'))
            ) {
                $lockedPayment->update([
                    'status' => Payment::STATUS_RECONCILIATION_REQUIRED,
                    'qris_payload' => null,
                ]);
                $lockedBooking->update(['status' => Booking::STATUS_RECONCILIATION_REQUIRED]);
                $lockedHold->update(['status' => BookingHold::STATUS_RECONCILING]);

                return $lockedPayment->refresh();
            }

            $lockedPayment->update([
                'gateway_provider' => $charge['gateway_provider'],
                'gateway_transaction_id' => $charge['gateway_transaction_id'],
                'qris_payload' => $charge['qris_payload'],
                'gateway_fee_amount' => (int) $charge['gateway_fee_amount'],
                'expires_at' => $deadline,
                'status' => Payment::STATUS_PENDING,
            ]);

            AuditLog::query()->create([
                'actor_user_id' => null,
                'actor_role' => 'system',
                'action' => 'payment.qris_charge_created',
                'auditable_type' => Payment::class,
                'auditable_id' => $lockedPayment->getKey(),
                'reason' => 'QRIS charge initialized after checkout transaction committed.',
                'before_state_json' => ['status' => Payment::STATUS_PENDING],
                'after_state_json' => [
                    'status' => $lockedPayment->status,
                    'gateway_provider' => $lockedPayment->gateway_provider,
                    'gateway_transaction_id' => $lockedPayment->gateway_transaction_id,
                    'amount' => $lockedPayment->amount,
                    'expires_at' => $deadline->toISOString(),
                ],
                'ip_hash' => null,
                'created_at' => now(),
            ]);

            return $lockedPayment->refresh();
        }, attempts: 3);
    }

    /**
     * Initialize QRIS for a subscription invoice after the invoice/payment transaction commits.
     * The TTL is configurable because the production provider has not yet been selected.
     */
    public function initializeSubscriptionPayment(Payment $payment): Payment
    {
        $payment->refresh();

        if ($payment->payable_type !== Payment::TYPE_SUBSCRIPTION || $payment->status !== Payment::STATUS_PENDING) {
            return $payment;
        }

        $subscription = Subscription::query()->findOrFail($payment->payable_id);
        $invoice = Invoice::query()
            ->where('reference_id', $subscription->getKey())
            ->whereJsonContains('line_items_json->payment_idempotency_key', $payment->idempotency_key)
            ->whereIn('invoice_type', [Invoice::TYPE_SUBSCRIPTION, Invoice::TYPE_SUBSCRIPTION_UPGRADE])
            ->where('status', Invoice::STATUS_UNPAID)
            ->firstOrFail();
        $deadline = $payment->expires_at
            ? CarbonImmutable::instance($payment->expires_at)->utc()
            : CarbonImmutable::now('UTC')->addMinutes(60);

        if ($deadline->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
            return DB::transaction(function () use ($payment, $subscription, $invoice): Payment {
                $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
                $lockedInvoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
                $lockedSubscription = Subscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();

                if ($lockedPayment->status === Payment::STATUS_PENDING) {
                    $lockedPayment->update(['status' => Payment::STATUS_EXPIRED, 'qris_payload' => null]);
                    $lockedInvoice->update(['status' => Invoice::STATUS_VOID]);

                    if ($lockedSubscription->status === Subscription::STATUS_PENDING_PAYMENT) {
                        $lockedSubscription->update(['status' => Subscription::STATUS_CANCELLED]);
                    }
                }

                return $lockedPayment->refresh();
            }, attempts: 3);
        }

        try {
            $charge = $this->gateway->createQrisCharge($payment, $deadline);
        } catch (PaymentGatewayException $exception) {
            return $this->failSubscriptionCharge($payment, $invoice, $subscription, $exception->getMessage(), $exception->isDefinitiveFailure());
        } catch (Throwable $exception) {
            return $this->failSubscriptionCharge(
                $payment,
                $invoice,
                $subscription,
                'Gateway response is uncertain; retry/query using the same idempotency key.',
                false,
            );
        }

        if (! $this->validChargeResponse($charge)) {
            return $this->failSubscriptionCharge(
                $payment,
                $invoice,
                $subscription,
                'Gateway returned an incomplete or invalid subscription charge response.',
                false,
            );
        }

        if (CarbonImmutable::now('UTC')->greaterThanOrEqualTo($deadline)) {
            return $this->failSubscriptionCharge(
                $payment,
                $invoice,
                $subscription,
                'Subscription charge response arrived after its configured deadline; reconciliation is required.',
                false,
            );
        }

        return DB::transaction(function () use ($payment, $invoice, $subscription, $charge): Payment {
            $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $lockedInvoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            $lockedSubscription = Subscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();

            if (
                $lockedPayment->status !== Payment::STATUS_PENDING
                || $lockedInvoice->status !== Invoice::STATUS_UNPAID
                || ! in_array($lockedSubscription->status, [
                    Subscription::STATUS_PENDING_PAYMENT,
                    Subscription::STATUS_TRIALING,
                    Subscription::STATUS_ACTIVE,
                    Subscription::STATUS_GRACE_PERIOD,
                ], true)
            ) {
                return $lockedPayment;
            }

            $lockedPayment->update([
                'gateway_provider' => $charge['gateway_provider'],
                'gateway_transaction_id' => $charge['gateway_transaction_id'],
                'qris_payload' => $charge['qris_payload'],
                'gateway_fee_amount' => (int) $charge['gateway_fee_amount'],
                'status' => Payment::STATUS_PENDING,
            ]);

            AuditLog::query()->create([
                'actor_user_id' => null,
                'actor_role' => 'system',
                'action' => 'subscription.payment_qris_initialized',
                'auditable_type' => Subscription::class,
                'auditable_id' => $lockedSubscription->getKey(),
                'reason' => 'Subscription invoice QRIS charge initialized.',
                'before_state_json' => ['payment_status' => Payment::STATUS_PENDING],
                'after_state_json' => [
                    'payment_id' => $lockedPayment->getKey(),
                    'invoice_id' => $lockedInvoice->getKey(),
                    'gateway_provider' => $lockedPayment->gateway_provider,
                    'gateway_transaction_id' => $lockedPayment->gateway_transaction_id,
                    'expires_at' => $lockedPayment->expires_at?->toISOString(),
                ],
                'ip_hash' => null,
                'created_at' => now('UTC'),
            ]);

            return $lockedPayment->refresh();
        }, attempts: 3);
    }

    private function failSubscriptionCharge(
        Payment $payment,
        Invoice $invoice,
        Subscription $subscription,
        string $reason,
        bool $definitive,
    ): Payment {
        return DB::transaction(function () use ($payment, $invoice, $subscription, $reason, $definitive): Payment {
            $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $lockedInvoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            $lockedSubscription = Subscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== Payment::STATUS_PENDING) {
                return $lockedPayment;
            }

            $lockedPayment->update([
                'status' => $definitive ? Payment::STATUS_FAILED : Payment::STATUS_VERIFYING,
                'qris_payload' => null,
            ]);

            if ($definitive) {
                $lockedInvoice->update(['status' => Invoice::STATUS_VOID]);

                if ($lockedSubscription->status === Subscription::STATUS_PENDING_PAYMENT) {
                    $lockedSubscription->update(['status' => Subscription::STATUS_CANCELLED]);
                }
            }

            AuditLog::query()->create([
                'actor_user_id' => null,
                'actor_role' => 'system',
                'action' => $definitive ? 'subscription.payment_initialization_failed' : 'subscription.payment_requires_reconciliation',
                'auditable_type' => Subscription::class,
                'auditable_id' => $lockedSubscription->getKey(),
                'reason' => $reason,
                'before_state_json' => ['payment_status' => Payment::STATUS_PENDING],
                'after_state_json' => [
                    'payment_status' => $lockedPayment->status,
                    'invoice_status' => $lockedInvoice->status,
                    'subscription_status' => $lockedSubscription->status,
                ],
                'ip_hash' => null,
                'created_at' => now('UTC'),
            ]);

            return $lockedPayment->refresh();
        }, attempts: 3);
    }

    private function validChargeResponse(array $charge): bool
    {
        return filled($charge['gateway_provider'] ?? null)
            && filled($charge['gateway_transaction_id'] ?? null)
            && filled($charge['qris_payload'] ?? null)
            && is_numeric($charge['gateway_fee_amount'] ?? null)
            && (int) $charge['gateway_fee_amount'] >= 0
            && ($charge['status'] ?? null) === Payment::STATUS_PENDING;
    }

    private function expireBeforeGatewayCall(Payment $payment, Booking $booking, BookingHold $hold): Payment
    {
        return DB::transaction(function () use ($payment, $booking, $hold): Payment {
            $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $lockedBooking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $lockedHold = BookingHold::query()->whereKey($hold->getKey())->lockForUpdate()->firstOrFail();

            $lockedPayment->update(['status' => Payment::STATUS_EXPIRED, 'qris_payload' => null]);
            $lockedBooking->update([
                'status' => Booking::STATUS_EXPIRED,
                'slot_released_at' => now(),
            ]);
            $lockedHold->update(['status' => BookingHold::STATUS_RELEASED]);

            return $lockedPayment->refresh();
        }, attempts: 3);
    }

    private function failDefinitively(Payment $payment, Booking $booking, BookingHold $hold, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $booking, $hold, $reason): Payment {
            $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $lockedBooking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $lockedHold = BookingHold::query()->whereKey($hold->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== Payment::STATUS_PENDING && $lockedPayment->status !== Payment::STATUS_VERIFYING) {
                return $lockedPayment;
            }

            $lockedPayment->update(['status' => Payment::STATUS_FAILED, 'qris_payload' => null]);
            $lockedBooking->update([
                'status' => Booking::STATUS_EXPIRED,
                'slot_released_at' => now(),
                'cancellation_reason' => $reason,
            ]);
            $lockedHold->update(['status' => BookingHold::STATUS_RELEASED]);

            AuditLog::query()->create([
                'actor_user_id' => null,
                'actor_role' => 'system',
                'action' => 'payment.charge_creation_failed',
                'auditable_type' => Payment::class,
                'auditable_id' => $lockedPayment->getKey(),
                'reason' => $reason,
                'before_state_json' => ['status' => $payment->status],
                'after_state_json' => ['status' => Payment::STATUS_FAILED],
                'ip_hash' => null,
                'created_at' => now(),
            ]);

            return $lockedPayment->refresh();
        }, attempts: 3);
    }

    private function markVerifying(Payment $payment, Booking $booking, BookingHold $hold, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $booking, $hold, $reason): Payment {
            $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $lockedBooking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $lockedHold = BookingHold::query()->whereKey($hold->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status === Payment::STATUS_PENDING) {
                $lockedPayment->update(['status' => Payment::STATUS_VERIFYING, 'qris_payload' => null]);
                $lockedBooking->update(['status' => Booking::STATUS_PAYMENT_VERIFYING]);
            }

            AuditLog::query()->create([
                'actor_user_id' => null,
                'actor_role' => 'system',
                'action' => 'payment.charge_creation_uncertain',
                'auditable_type' => Payment::class,
                'auditable_id' => $lockedPayment->getKey(),
                'reason' => $reason,
                'before_state_json' => ['status' => $payment->status],
                'after_state_json' => ['status' => $lockedPayment->status],
                'ip_hash' => null,
                'created_at' => now(),
            ]);

            return $lockedPayment->refresh();
        }, attempts: 3);
    }

    private function markReconciliationRequired(Payment $payment, Booking $booking, BookingHold $hold, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $booking, $hold, $reason): Payment {
            $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $lockedBooking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $lockedHold = BookingHold::query()->whereKey($hold->getKey())->lockForUpdate()->firstOrFail();

            $lockedPayment->update([
                'status' => Payment::STATUS_RECONCILIATION_REQUIRED,
                'qris_payload' => null,
            ]);
            $lockedBooking->update(['status' => Booking::STATUS_RECONCILIATION_REQUIRED]);
            $lockedHold->update(['status' => BookingHold::STATUS_RECONCILING]);

            AuditLog::query()->create([
                'actor_user_id' => null,
                'actor_role' => 'system',
                'action' => 'payment.reconciliation_required',
                'auditable_type' => Payment::class,
                'auditable_id' => $lockedPayment->getKey(),
                'reason' => $reason,
                'before_state_json' => ['status' => $payment->status],
                'after_state_json' => ['status' => Payment::STATUS_RECONCILIATION_REQUIRED],
                'ip_hash' => null,
                'created_at' => now(),
            ]);

            return $lockedPayment->refresh();
        }, attempts: 3);
    }
}
