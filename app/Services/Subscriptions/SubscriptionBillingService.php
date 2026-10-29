<?php

namespace App\Services\Subscriptions;

use App\Models\AuditLog;
use App\Models\PaymentWebhookEvent;
use App\Models\Business;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\Finance\LedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionBillingService
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly LedgerService $ledger,
    )
    {
    }

    /**
     * A paid renewal belongs to this exact scheduled period, not an earlier cycle.
     */
    public function paidScheduledRenewalInvoice(Subscription $subscription): ?Invoice
    {
        if (! $subscription->scheduled_plan_id || ! $subscription->ends_at) {
            return null;
        }

        return Invoice::query()
            ->where('invoice_type', Invoice::TYPE_SUBSCRIPTION)
            ->where('reference_id', $subscription->getKey())
            ->where('recipient_user_id', $subscription->owner_id)
            ->where('status', Invoice::STATUS_PAID)
            ->whereJsonContains('line_items_json->action', 'renewal_downgrade')
            ->whereJsonContains('line_items_json->target_plan_id', (int) $subscription->scheduled_plan_id)
            ->whereJsonContains('line_items_json->scheduled_effective_at', $subscription->ends_at->copy()->utc()->toIso8601String())
            ->latest('id')
            ->first();
    }

    /**
     * @return array{scheduled:bool,subscription:Subscription,invoice:?Invoice,payment:?Payment}
     */
    public function checkoutPlan(
        User $owner,
        SubscriptionPlan $targetPlan,
        string $idempotencyKey,
        ?int $retainedBusinessId = null,
    ): array {
        if (! $owner->isOwner() || $owner->status !== 'active' || ! $owner->hasVerifiedEmail() || ! $targetPlan->is_active) {
            abort(403, 'Owner aktif terverifikasi dan paket aktif diperlukan.');
        }

        $idempotencyKey = trim($idempotencyKey);

        if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 100) {
            throw ValidationException::withMessages([
                'idempotencyKey' => 'Kunci checkout wajib diisi dan maksimal 100 karakter.',
            ]);
        }

        $paymentKey = 'subscription:' . $owner->getKey() . ':' . hash('sha256', $idempotencyKey);
        $existingPayment = Payment::query()->where('idempotency_key', $paymentKey)->first();

        if ($existingPayment) {
            $invoice = Invoice::query()
                ->where('reference_id', $existingPayment->payable_id)
                ->whereJsonContains('line_items_json->payment_idempotency_key', $paymentKey)
                ->first();

            return [
                'scheduled' => false,
                'subscription' => Subscription::query()->findOrFail($existingPayment->payable_id),
                'invoice' => $invoice,
                'payment' => $existingPayment,
            ];
        }

        $current = Subscription::query()
            ->where('owner_id', $owner->getKey())
            ->whereIn('status', [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE, Subscription::STATUS_GRACE_PERIOD])
            ->where('starts_at', '<=', now('UTC'))
            ->where(function ($query): void {
                $query->where(function ($active): void {
                    $active->whereIn('status', [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE])
                        ->where('ends_at', '>', now('UTC'));
                })->orWhere(function ($grace): void {
                    $grace->where('status', Subscription::STATUS_GRACE_PERIOD)
                        ->where('grace_ends_at', '>=', now('UTC'));
                });
            })
            ->with(['plan', 'scheduledPlan'])
            ->orderByDesc('ends_at')
            ->first();

        if (! $current) {
            $pending = Subscription::query()
                ->where('owner_id', $owner->getKey())
                ->where('status', Subscription::STATUS_PENDING_PAYMENT)
                ->exists();

            if ($pending) {
                throw ValidationException::withMessages([
                    'subscription' => 'Selesaikan atau rekonsiliasi pembayaran langganan yang masih pending sebelum memulai checkout baru.',
                ]);
            }

            return $this->createInitialPurchase($owner, $targetPlan, $paymentKey);
        }

        $openPayment = Payment::query()
            ->where('payable_type', Payment::TYPE_SUBSCRIPTION)
            ->where('payable_id', $current->getKey())
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_VERIFYING, Payment::STATUS_RECONCILIATION_REQUIRED])
            ->exists();

        if ($openPayment) {
            throw ValidationException::withMessages([
                'subscription' => 'Masih ada pembayaran langganan yang belum selesai atau memerlukan rekonsiliasi.',
            ]);
        }

        if ($current->scheduled_plan_id && $this->paidScheduledRenewalInvoice($current)) {
            throw ValidationException::withMessages([
                'subscription' => 'Perpanjangan Starter sudah lunas. Paket akan berubah otomatis saat periode Business berakhir.',
            ]);
        }

        if ($current->scheduled_plan_id && (int) $current->scheduled_plan_id === (int) $targetPlan->getKey()) {
            if ($retainedBusinessId !== null) {
                return $this->scheduleDowngrade($owner, $current, $targetPlan, $retainedBusinessId);
            }

            return $this->createRenewal($owner, $current, $targetPlan, $paymentKey, true);
        }

        $currentPrice = (int) $current->plan->monthly_price;
        $targetPrice = (int) $targetPlan->monthly_price;

        if ($targetPrice < $currentPrice) {
            return $this->scheduleDowngrade($owner, $current, $targetPlan, $retainedBusinessId);
        }

        if ($targetPrice === $currentPrice && (int) $targetPlan->getKey() !== (int) $current->plan_id) {
            throw ValidationException::withMessages([
                'plan' => 'Perubahan paket dengan harga sama tidak didukung oleh alur upgrade/downgrade.',
            ]);
        }

        if ($targetPrice === $currentPrice) {
            return $this->createRenewal($owner, $current, $targetPlan, $paymentKey, false);
        }

        return $this->createUpgrade($owner, $current, $targetPlan, $paymentKey);
    }

    /**
     * Process an already signature-verified, normalized subscription payment webhook.
     *
     * @param array{event_id:string,gateway_transaction_id:string,status:string,paid_at:?string,gateway_fee_amount:int,amount:int} $event
     */
    public function processWebhook(Payment $payment, PaymentWebhookEvent $eventRecord, array $event): string
    {
        $subscription = Subscription::query()->whereKey($payment->payable_id)->first();

        if (! $subscription) {
            $eventRecord->update([
                'processing_status' => PaymentWebhookEvent::STATUS_FAILED,
                'processing_notes' => 'Subscription payment has no matching subscription.',
                'processed_at' => now('UTC'),
            ]);

            return PaymentWebhookEvent::STATUS_FAILED;
        }

        $invoice = Invoice::query()
            ->where('reference_id', $subscription->getKey())
            ->whereJsonContains('line_items_json->payment_idempotency_key', $payment->idempotency_key)
            ->first();

        if (! $invoice) {
            $eventRecord->update([
                'payment_id' => $payment->getKey(),
                'processing_status' => PaymentWebhookEvent::STATUS_FAILED,
                'processing_notes' => 'Subscription invoice could not be resolved from the payment idempotency key.',
                'processed_at' => now('UTC'),
            ]);

            return PaymentWebhookEvent::STATUS_FAILED;
        }

        return DB::transaction(function () use ($payment, $eventRecord, $event, $subscription, $invoice): string {
            $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $lockedSubscription = Subscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();
            $lockedInvoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            $lockedEvent = PaymentWebhookEvent::query()->whereKey($eventRecord->getKey())->lockForUpdate()->firstOrFail();

            $lockedEvent->update(['payment_id' => $lockedPayment->getKey()]);

            if ($lockedPayment->status === Payment::STATUS_PAID && $lockedInvoice->status === Invoice::STATUS_PAID) {
                $lockedEvent->update([
                    'processing_status' => PaymentWebhookEvent::STATUS_PROCESSED,
                    'processing_notes' => 'Duplicate subscription paid event ignored; financial state is already final.',
                    'processed_at' => now('UTC'),
                ]);

                return PaymentWebhookEvent::STATUS_PROCESSED;
            }

            if (in_array($event['status'], [Payment::STATUS_FAILED, Payment::STATUS_EXPIRED], true)) {
                if (in_array($lockedPayment->status, [Payment::STATUS_PAID, Payment::STATUS_RECONCILIATION_REQUIRED], true)) {
                    $lockedEvent->update([
                        'processing_status' => PaymentWebhookEvent::STATUS_PROCESSED,
                        'processing_notes' => 'Conflicting non-paid subscription event did not downgrade an existing paid/reconciliation state.',
                        'processed_at' => now('UTC'),
                    ]);

                    return PaymentWebhookEvent::STATUS_PROCESSED;
                }

                $beforePaymentStatus = $lockedPayment->status;
                $lockedPayment->update([
                    'status' => $event['status'],
                    'qris_payload' => null,
                ]);
                $lockedInvoice->update(['status' => Invoice::STATUS_VOID]);

                if ($lockedSubscription->status === Subscription::STATUS_PENDING_PAYMENT) {
                    $lockedSubscription->update(['status' => Subscription::STATUS_CANCELLED]);
                }

                $this->audit($lockedSubscription->owner, $lockedSubscription, 'subscription.payment_failed', 'Subscription payment failed or expired before activation.', [
                    'payment_id' => $lockedPayment->getKey(),
                    'invoice_id' => $lockedInvoice->getKey(),
                    'before_payment_status' => $beforePaymentStatus,
                    'payment_status' => $lockedPayment->status,
                    'subscription_status' => $lockedSubscription->status,
                ]);

                $lockedEvent->update([
                    'processing_status' => PaymentWebhookEvent::STATUS_PROCESSED,
                    'processing_notes' => null,
                    'processed_at' => now('UTC'),
                ]);

                return PaymentWebhookEvent::STATUS_PROCESSED;
            }

            $paidAt = filled($event['paid_at'] ?? null)
                ? CarbonImmutable::parse($event['paid_at'])->utc()
                : null;
            $fee = (int) ($event['gateway_fee_amount'] ?? 0);
            $validState = in_array($lockedPayment->status, [Payment::STATUS_PENDING, Payment::STATUS_VERIFYING], true);
            $validAmount = (int) $event['amount'] === (int) $lockedInvoice->total_amount
                && (int) $event['amount'] === (int) $lockedPayment->amount;
            $validFee = $fee >= 0 && $fee <= (int) $lockedPayment->amount;
            $validTime = $paidAt !== null
                && ($lockedPayment->expires_at === null || $paidAt->lessThanOrEqualTo(CarbonImmutable::instance($lockedPayment->expires_at)->utc()));
            $lineItems = $lockedInvoice->line_items_json ?? [];
            $action = $lineItems['action'] ?? null;
            $targetPlanId = (int) ($lineItems['target_plan_id'] ?? 0);
            $targetPlan = SubscriptionPlan::query()->whereKey($targetPlanId)->where('is_active', true)->first();
            $validScheduledRenewal = $action !== 'renewal_downgrade'
                || (
                    $lockedSubscription->scheduled_plan_id !== null
                    && (int) $lockedSubscription->scheduled_plan_id === $targetPlanId
                    && (int) ($lineItems['retained_business_id'] ?? 0) === (int) ($lockedSubscription->retained_business_id ?? 0)
                    && ($lineItems['scheduled_effective_at'] ?? null) === $lockedSubscription->ends_at->copy()->utc()->toIso8601String()
                );

            if (! $validState || ! $validAmount || ! $validFee || ! $validTime || ! $validScheduledRenewal || ! $targetPlan || ! in_array($action, ['purchase', 'renewal', 'renewal_downgrade', 'upgrade'], true)) {
                $beforePaymentStatus = $lockedPayment->status;
                $lockedPayment->update(['status' => Payment::STATUS_RECONCILIATION_REQUIRED, 'qris_payload' => null]);

                $lockedEvent->update([
                    'processing_status' => PaymentWebhookEvent::STATUS_PROCESSED,
                    'processing_notes' => 'Subscription payment requires manual reconciliation: status, amount, fee, paid_at, invoice action, or target plan did not pass validation.',
                    'processed_at' => now('UTC'),
                ]);

                $this->audit($lockedSubscription->owner, $lockedSubscription, 'subscription.payment_reconciliation_required', 'Payment webhook did not meet the conditions for automatic subscription activation.', [
                    'payment_id' => $lockedPayment->getKey(),
                    'invoice_id' => $lockedInvoice->getKey(),
                    'before_payment_status' => $beforePaymentStatus,
                    'payment_status' => Payment::STATUS_RECONCILIATION_REQUIRED,
                    'event_amount' => (int) $event['amount'],
                    'invoice_amount' => (int) $lockedInvoice->total_amount,
                    'paid_at' => $paidAt?->toISOString(),
                ]);

                return PaymentWebhookEvent::STATUS_PROCESSED;
            }

            $now = CarbonImmutable::now('UTC');
            $beforeSubscriptionStatus = $lockedSubscription->status;
            $lockedPayment->update([
                'status' => Payment::STATUS_PAID,
                'paid_at' => $paidAt,
                'gateway_fee_amount' => $fee,
                'qris_payload' => null,
            ]);
            $lockedInvoice->update([
                'status' => Invoice::STATUS_PAID,
                'paid_at' => $paidAt,
            ]);

            if ($action === 'purchase') {
                $startsAt = $paidAt;
                $endsAt = $startsAt->addDays((int) $targetPlan->duration_days);
                $lockedSubscription->update([
                    'plan_id' => $targetPlan->getKey(),
                    'is_trial' => false,
                    'status' => Subscription::STATUS_ACTIVE,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'grace_ends_at' => $endsAt->addHours(24),
                    'suspended_at' => null,
                    'scheduled_plan_id' => null,
                    'retained_business_id' => null,
                ]);
            } elseif ($action === 'upgrade') {
                if (! in_array($lockedSubscription->status, [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE], true) || $lockedSubscription->ends_at->lessThanOrEqualTo($paidAt)) {
                    $lockedPayment->update(['status' => Payment::STATUS_RECONCILIATION_REQUIRED]);
                    $lockedInvoice->update(['status' => Invoice::STATUS_UNPAID, 'paid_at' => null]);
                    $lockedEvent->update([
                        'processing_status' => PaymentWebhookEvent::STATUS_PROCESSED,
                        'processing_notes' => 'Upgrade payment arrived outside the active period and requires manual reconciliation.',
                        'processed_at' => now('UTC'),
                    ]);

                    return PaymentWebhookEvent::STATUS_PROCESSED;
                }

                $lockedSubscription->update([
                    'plan_id' => $targetPlan->getKey(),
                    'is_trial' => false,
                    'status' => Subscription::STATUS_ACTIVE,
                    'grace_ends_at' => $lockedSubscription->ends_at->copy()->addHours(24),
                    'scheduled_plan_id' => null,
                    'retained_business_id' => null,
                ]);
            } elseif ($action === 'renewal_downgrade' && $lockedSubscription->ends_at->greaterThan($now)) {
                // The renewal is paid, but Business remains active until its existing period ends.
                // The lifecycle scheduler activates Starter and archives excess businesses then.
            } else {
                $periodStart = $lockedSubscription->ends_at->greaterThan($paidAt)
                    ? CarbonImmutable::instance($lockedSubscription->ends_at)->utc()
                    : $paidAt;
                $endsAt = $periodStart->addDays((int) $targetPlan->duration_days);
                $retainedBusinessId = $action === 'renewal_downgrade'
                    ? ($lineItems['retained_business_id'] ?? null)
                    : null;

                $lockedSubscription->update([
                    'plan_id' => $targetPlan->getKey(),
                    'is_trial' => false,
                    'status' => Subscription::STATUS_ACTIVE,
                    'starts_at' => $lockedSubscription->starts_at,
                    'ends_at' => $endsAt,
                    'grace_ends_at' => $endsAt->addHours(24),
                    'suspended_at' => null,
                    'scheduled_plan_id' => null,
                    'retained_business_id' => $retainedBusinessId,
                ]);

                if ($action === 'renewal_downgrade' && $retainedBusinessId) {
                    Business::query()
                        ->where('owner_id', $lockedSubscription->owner_id)
                        ->where('id', '!=', $retainedBusinessId)
                        ->whereNotIn('status', [Business::STATUS_SUSPENDED, Business::STATUS_REJECTED, Business::STATUS_ARCHIVED_BY_DOWNGRADE])
                        ->update(['status' => Business::STATUS_ARCHIVED_BY_DOWNGRADE]);
                }
            }

            $this->ledger->postBalancedGroup([
                [
                    'account_code' => 'customer_cash',
                    'entry_type' => 'debit',
                    'amount' => (int) $lockedPayment->amount,
                    'reference_type' => 'invoice',
                    'reference_id' => $lockedInvoice->getKey(),
                    'description' => 'Verified subscription invoice payment.',
                ],
                [
                    'account_code' => 'subscription_revenue',
                    'entry_type' => 'credit',
                    'amount' => (int) $lockedPayment->amount,
                    'reference_type' => 'invoice',
                    'reference_id' => $lockedInvoice->getKey(),
                    'description' => 'Recognize subscription invoice revenue.',
                ],
            ]);

            if ($fee > 0) {
                $this->ledger->postBalancedGroup([
                    [
                        'account_code' => 'gateway_fee_expense',
                        'entry_type' => 'debit',
                        'amount' => $fee,
                        'reference_type' => 'invoice',
                        'reference_id' => $lockedInvoice->getKey(),
                        'description' => 'Record subscription payment gateway fee; final provider settlement treatment remains configurable.',
                    ],
                    [
                        'account_code' => 'gateway_clearing',
                        'entry_type' => 'credit',
                        'amount' => $fee,
                        'reference_type' => 'invoice',
                        'reference_id' => $lockedInvoice->getKey(),
                        'description' => 'Record gateway fee payable/withheld on subscription payment.',
                    ],
                ]);
            }

            $this->audit($lockedSubscription->owner, $lockedSubscription, 'subscription.payment_confirmed', 'Subscription invoice payment confirmed by verified gateway webhook.', [
                'payment_id' => $lockedPayment->getKey(),
                'invoice_id' => $lockedInvoice->getKey(),
                'action' => $action,
                'before_status' => $beforeSubscriptionStatus,
                'status' => $lockedSubscription->status,
                'plan_id' => $lockedSubscription->plan_id,
                'starts_at' => $lockedSubscription->starts_at?->toISOString(),
                'ends_at' => $lockedSubscription->ends_at?->toISOString(),
            ]);

            $lockedEvent->update([
                'processing_status' => PaymentWebhookEvent::STATUS_PROCESSED,
                'processing_notes' => null,
                'processed_at' => now('UTC'),
            ]);

            return PaymentWebhookEvent::STATUS_PROCESSED;
        }, attempts: 3);
    }

    private function createInitialPurchase(User $owner, SubscriptionPlan $plan, string $paymentKey): array
    {
        $result = DB::transaction(function () use ($owner, $plan, $paymentKey): array {
            $lockedOwner = User::query()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail();
            $startsAt = now('UTC');
            $endsAt = $startsAt->copy()->addDays((int) $plan->duration_days);

            $subscription = Subscription::query()->create([
                'owner_id' => $lockedOwner->getKey(),
                'plan_id' => $plan->getKey(),
                'scheduled_plan_id' => null,
                'retained_business_id' => null,
                'is_trial' => false,
                'status' => Subscription::STATUS_PENDING_PAYMENT,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'grace_ends_at' => $endsAt->copy()->addHours(24),
                'suspended_at' => null,
                'reverified_by' => null,
            ]);

            $invoice = $this->createInvoice($lockedOwner, $subscription, $plan, Invoice::TYPE_SUBSCRIPTION, (int) $plan->monthly_price, [
                'action' => 'purchase',
                'target_plan_id' => $plan->getKey(),
                'duration_days' => (int) $plan->duration_days,
            ]);
            $payment = $this->createPayment($lockedOwner, $subscription, $invoice, $paymentKey);

            $this->audit($lockedOwner, $subscription, 'subscription.purchase_started', 'Initial subscription purchase checkout created.', [
                'plan_id' => $plan->getKey(),
                'invoice_id' => $invoice->getKey(),
                'payment_id' => $payment->getKey(),
                'amount' => $payment->amount,
            ]);

            return ['scheduled' => false, 'subscription' => $subscription, 'invoice' => $invoice, 'payment' => $payment];
        }, attempts: 3);

        $result['payment'] = $this->payments->initializeSubscriptionPayment($result['payment']);

        return $result;
    }

    private function createRenewal(User $owner, Subscription $subscription, SubscriptionPlan $plan, string $paymentKey, bool $applyScheduledDowngrade): array
    {
        $result = DB::transaction(function () use ($owner, $subscription, $plan, $paymentKey, $applyScheduledDowngrade): array {
            $locked = Subscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();

            if ($applyScheduledDowngrade) {
                if (! in_array($locked->status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_GRACE_PERIOD], true)
                    || (int) $locked->scheduled_plan_id !== (int) $plan->getKey()
                    || $this->paidScheduledRenewalInvoice($locked)
                    || Payment::query()
                        ->where('payable_type', Payment::TYPE_SUBSCRIPTION)
                        ->where('payable_id', $locked->getKey())
                        ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_VERIFYING, Payment::STATUS_RECONCILIATION_REQUIRED])
                        ->exists()) {
                    throw ValidationException::withMessages([
                        'subscription' => 'Perpanjangan sudah dibayar, sedang diproses, atau jadwal paket telah berubah.',
                    ]);
                }
            }

            $invoice = $this->createInvoice($owner, $locked, $plan, Invoice::TYPE_SUBSCRIPTION, (int) $plan->monthly_price, [
                'action' => $applyScheduledDowngrade ? 'renewal_downgrade' : 'renewal',
                'target_plan_id' => $plan->getKey(),
                'duration_days' => (int) $plan->duration_days,
                'scheduled_plan_id' => $applyScheduledDowngrade ? $plan->getKey() : null,
                'retained_business_id' => $applyScheduledDowngrade ? $locked->retained_business_id : null,
                'scheduled_effective_at' => $applyScheduledDowngrade ? $locked->ends_at->copy()->utc()->toIso8601String() : null,
            ]);
            $payment = $this->createPayment($owner, $locked, $invoice, $paymentKey);

            $this->audit($owner, $locked, 'subscription.renewal_started', 'Subscription renewal checkout created.', [
                'invoice_id' => $invoice->getKey(),
                'payment_id' => $payment->getKey(),
                'target_plan_id' => $plan->getKey(),
                'amount' => $payment->amount,
            ]);

            return ['scheduled' => false, 'subscription' => $locked, 'invoice' => $invoice, 'payment' => $payment];
        }, attempts: 3);

        $result['payment'] = $this->payments->initializeSubscriptionPayment($result['payment']);

        return $result;
    }

    private function createUpgrade(User $owner, Subscription $subscription, SubscriptionPlan $targetPlan, string $paymentKey): array
    {
        $remainingSeconds = max(0, now('UTC')->diffInSeconds($subscription->ends_at, false));
        $remainingDays = max(1, (int) ceil($remainingSeconds / 86400));
        $priceDifference = (int) $targetPlan->monthly_price - (int) $subscription->plan->monthly_price;
        $amount = intdiv(($remainingDays * $priceDifference) + 29, 30);

        if ($amount < 1) {
            $amount = 1;
        }

        $result = DB::transaction(function () use ($owner, $subscription, $targetPlan, $paymentKey, $remainingDays, $amount): array {
            $locked = Subscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();
            $invoice = $this->createInvoice($owner, $locked, $targetPlan, Invoice::TYPE_SUBSCRIPTION_UPGRADE, $amount, [
                'action' => 'upgrade',
                'current_plan_id' => $locked->plan_id,
                'target_plan_id' => $targetPlan->getKey(),
                'period_ends_at' => $locked->ends_at?->toISOString(),
                'remaining_days' => $remainingDays,
                'price_difference' => (int) $targetPlan->monthly_price - (int) $locked->plan->monthly_price,
                'proration_denominator_days' => 30,
            ]);
            $payment = $this->createPayment($owner, $locked, $invoice, $paymentKey);

            $this->audit($owner, $locked, 'subscription.upgrade_started', 'Prorated upgrade checkout created; plan remains unchanged until payment webhook confirms.', [
                'invoice_id' => $invoice->getKey(),
                'payment_id' => $payment->getKey(),
                'current_plan_id' => $locked->plan_id,
                'target_plan_id' => $targetPlan->getKey(),
                'remaining_days' => $remainingDays,
                'amount' => $amount,
            ]);

            return ['scheduled' => false, 'subscription' => $locked, 'invoice' => $invoice, 'payment' => $payment];
        }, attempts: 3);

        $result['payment'] = $this->payments->initializeSubscriptionPayment($result['payment']);

        return $result;
    }

    private function scheduleDowngrade(User $owner, Subscription $subscription, SubscriptionPlan $targetPlan, ?int $retainedBusinessId): array
    {
        if ($targetPlan->slug !== 'starter' || $subscription->plan->slug !== 'business') {
            throw ValidationException::withMessages([
                'plan' => 'Downgrade hanya mendukung Business ke Starter sesuai PRD.',
            ]);
        }

        $businesses = Business::query()
            ->where('owner_id', $owner->getKey())
            ->whereNotIn('status', [Business::STATUS_SUSPENDED, Business::STATUS_REJECTED])
            ->orderBy('id')
            ->get(['id', 'name']);

        if ($businesses->count() > 1) {
            if (! $retainedBusinessId || ! $businesses->contains('id', $retainedBusinessId)) {
                throw ValidationException::withMessages([
                    'retainedBusinessId' => 'Pilih tepat satu usaha yang akan dipertahankan pada paket Starter.',
                ]);
            }
        } else {
            $retainedBusinessId = $businesses->first()?->getKey();
        }

        $updated = DB::transaction(function () use ($owner, $subscription, $targetPlan, $retainedBusinessId): Subscription {
            $locked = Subscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== Subscription::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'subscription' => 'Downgrade hanya dapat dijadwalkan dari langganan Business yang aktif.',
                ]);
            }

            if ($this->paidScheduledRenewalInvoice($locked)) {
                throw ValidationException::withMessages([
                    'subscription' => 'Perpanjangan Starter sudah dibayar. Pilihan usaha tidak dapat diubah setelah pembayaran.',
                ]);
            }

            $before = [
                'scheduled_plan_id' => $locked->scheduled_plan_id,
                'retained_business_id' => $locked->retained_business_id,
            ];
            $locked->update([
                'scheduled_plan_id' => $targetPlan->getKey(),
                'retained_business_id' => $retainedBusinessId,
            ]);

            $this->audit($owner, $locked, 'subscription.downgrade_scheduled', 'Downgrade will take effect at the next paid subscription period.', [
                'before' => $before,
                'scheduled_plan_id' => $targetPlan->getKey(),
                'retained_business_id' => $retainedBusinessId,
            ]);

            return $locked->refresh();
        }, attempts: 3);

        return ['scheduled' => true, 'subscription' => $updated, 'invoice' => null, 'payment' => null];
    }

    private function createInvoice(User $owner, Subscription $subscription, SubscriptionPlan $plan, string $type, int $amount, array $lineItems): Invoice
    {
        return Invoice::query()->create([
            'invoice_number' => 'INV-' . now('UTC')->format('Ymd') . '-' . Str::upper(Str::random(10)),
            'invoice_type' => $type,
            'reference_id' => $subscription->getKey(),
            'recipient_user_id' => $owner->getKey(),
            'issuer_name' => config('services.platform.legal_name', 'Adatempat'),
            'subtotal_amount' => $amount,
            'discount_amount' => 0,
            'total_amount' => $amount,
            'status' => Invoice::STATUS_UNPAID,
            'line_items_json' => array_merge([
                'plan_name' => $plan->name,
                'plan_slug' => $plan->slug,
                'monthly_price' => (int) $plan->monthly_price,
            ], $lineItems),
            'issued_at' => now('UTC'),
            'paid_at' => null,
        ]);
    }

    private function createPayment(User $owner, Subscription $subscription, Invoice $invoice, string $paymentKey): Payment
    {
        $payment = Payment::query()->create([
            'payable_type' => Payment::TYPE_SUBSCRIPTION,
            'payable_id' => $subscription->getKey(),
            'payer_user_id' => $owner->getKey(),
            'gateway_provider' => 'adapter_pending',
            'gateway_transaction_id' => null,
            'idempotency_key' => $paymentKey,
            'qris_payload' => null,
            'amount' => (int) $invoice->total_amount,
            'gateway_fee_amount' => 0,
            'status' => Payment::STATUS_PENDING,
            'paid_at' => null,
            'expires_at' => now('UTC')->addMinutes(max(15, (int) config('services.payment_gateway.invoice_payment_ttl_minutes', 60))),
        ]);

        $lineItems = $invoice->line_items_json ?? [];
        $lineItems['payment_idempotency_key'] = $paymentKey;
        $invoice->update(['line_items_json' => $lineItems]);

        return $payment;
    }

    private function audit(User $owner, Subscription $subscription, string $action, string $reason, array $after): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $owner->getKey(),
            'actor_role' => $owner->role,
            'action' => $action,
            'auditable_type' => Subscription::class,
            'auditable_id' => $subscription->getKey(),
            'reason' => $reason,
            'before_state_json' => null,
            'after_state_json' => $after,
            'ip_hash' => request()->ip() ? hash('sha256', (string) request()->ip()) : null,
            'created_at' => now('UTC'),
        ]);
    }
}
