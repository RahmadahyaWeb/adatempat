<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\AuditLog;
use App\Models\BookingHold;
use App\Models\Payment;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePromotion;
use App\Services\Subscriptions\SubscriptionEligibilityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingEngineService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PricingAndPromoService $pricing,
        private readonly SubscriptionEligibilityService $subscriptions,
    ) {
    }

    /**
     * Creates a local checkout intent. Paid checkout returns a pending payment without QRIS
     * until a PaymentGatewayContract is connected in the next implementation step.
     *
     * @param array<int, int|string> $sessionPriceIds
     * @return array{booking:Booking, hold:BookingHold, payment:?Payment, quote:array, idempotent_replay:bool}
     */
    public function createCheckout(
        User $customer,
        Venue $venue,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        int $termsAcceptanceId,
        string $idempotencyKey,
        array $sessionPriceIds = [],
    ): array {
        if (! $customer->isCustomer() || $customer->status !== 'active' || $customer->email_verified_at === null) {
            abort(403, 'Hanya pelanggan aktif dengan email terverifikasi yang dapat membuat booking.');
        }

        $idempotencyKey = trim($idempotencyKey);

        if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 100) {
            throw ValidationException::withMessages([
                'idempotencyKey' => 'Kunci idempotensi wajib diisi dan maksimal 100 karakter.',
            ]);
        }

        $startsAtUtc = $startsAtUtc->utc();
        $endsAtUtc = $endsAtUtc->utc();

        $acceptance = TermsAcceptance::query()
            ->whereKey($termsAcceptanceId)
            ->where('user_id', $customer->getKey())
            ->where('context', 'booking')
            ->whereHas('legalDocument', fn ($query) => $query->published())
            ->first();

        if (! $acceptance) {
            throw ValidationException::withMessages([
                'termsAcceptanceId' => 'Persetujuan syarat booking yang masih berlaku wajib tersedia.',
            ]);
        }

        try {
            return DB::transaction(function () use (
                $customer,
                $venue,
                $startsAtUtc,
                $endsAtUtc,
                $acceptance,
                $idempotencyKey,
                $sessionPriceIds,
            ): array {
                $lockedVenue = Venue::query()
                    ->whereKey($venue->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $existingHold = BookingHold::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existingHold) {
                    return $this->replayOrReject(
                        $existingHold,
                        $customer,
                        $lockedVenue,
                        $startsAtUtc,
                        $endsAtUtc,
                    );
                }

                $lockedVenue->load(['business', 'category', 'availabilityRules', 'prices']);

                if ((int) $lockedVenue->business->owner_id === (int) $customer->getKey()) {
                    throw ValidationException::withMessages([
                        'venue' => 'Owner tidak dapat membuat booking pada venue miliknya sendiri.',
                    ]);
                }

                $this->subscriptions->assertBusinessBookable($lockedVenue->business);
                $this->availability->assertAvailable($lockedVenue, $startsAtUtc, $endsAtUtc);

                $quote = $this->pricing->quote(
                    $lockedVenue,
                    $startsAtUtc,
                    $endsAtUtc,
                    $sessionPriceIds,
                    true,
                );

                $isFree = $quote['actual_paid_amount'] === 0;
                $now = CarbonImmutable::now('UTC');
                $holdExpiresAt = $isFree ? $now : $now->addMinutes(15);
                $referenceCode = 'ADT-' . $now->format('Ymd') . '-' . Str::upper(Str::random(8));

                $booking = Booking::query()->create([
                    'reference_code' => $referenceCode,
                    'venue_id' => $lockedVenue->getKey(),
                    'business_id' => $lockedVenue->business_id,
                    'customer_id' => $customer->getKey(),
                    'pricing_unit' => $quote['pricing_unit'],
                    'duration_units' => $quote['duration_units'],
                    'booking_start' => $startsAtUtc,
                    'booking_end' => $endsAtUtc,
                    'payment_required' => ! $isFree,
                    'status' => $isFree ? Booking::STATUS_CONFIRMED : Booking::STATUS_PENDING_PAYMENT,
                    'owner_promotion_id' => $quote['owner_promotion_id'],
                    'platform_promotion_id' => $quote['platform_promotion_id'],
                    'snapshot_base_amount' => $quote['base_amount'],
                    'snapshot_owner_discount_amount' => $quote['owner_discount_amount'],
                    'snapshot_platform_subsidy_amount' => $quote['platform_subsidy_amount'],
                    'snapshot_actual_paid_amount' => $quote['actual_paid_amount'],
                    'snapshot_gateway_fee_amount' => 0,
                    'snapshot_net_owner_entitlement' => $quote['owner_entitlement_before_gateway_fee'],
                    'snapshot_refund_policy_json' => $this->refundPolicySnapshot($lockedVenue),
                    'terms_acceptance_id' => $acceptance->getKey(),
                    'confirmed_at' => $isFree ? $now : null,
                ]);

                AuditLog::query()->create([
                    'actor_user_id' => $customer->getKey(),
                    'actor_role' => $customer->role,
                    'action' => $isFree ? 'booking.confirmed_zero_amount' : 'booking.checkout_created',
                    'auditable_type' => Booking::class,
                    'auditable_id' => $booking->getKey(),
                    'reason' => $isFree
                        ? 'Booking terkonfirmasi tanpa pembayaran karena total akhir Rp0.'
                        : 'Checkout booking dibuat dengan hold dan pembayaran pending.',
                    'before_state_json' => null,
                    'after_state_json' => [
                        'reference_code' => $booking->reference_code,
                        'status' => $booking->status,
                        'payment_required' => $booking->payment_required,
                        'actual_paid_amount' => $booking->snapshot_actual_paid_amount,
                    ],
                    'ip_hash' => request()->ip() ? hash('sha256', (string) request()->ip()) : null,
                    'created_at' => now(),
                ]);

                $hold = BookingHold::query()->create([
                    'venue_id' => $lockedVenue->getKey(),
                    'customer_id' => $customer->getKey(),
                    'booking_id' => $booking->getKey(),
                    'starts_at' => $startsAtUtc,
                    'ends_at' => $endsAtUtc,
                    'expires_at' => $holdExpiresAt,
                    'idempotency_key' => $idempotencyKey,
                    'status' => $isFree ? BookingHold::STATUS_CONVERTED : BookingHold::STATUS_ACTIVE,
                ]);

                $payment = null;

                if ($isFree) {
                    $this->consumePromotionQuotas($quote['promotion_quota_to_consume']);
                } else {
                    $payment = Payment::query()->create([
                        'payable_type' => Payment::TYPE_BOOKING,
                        'payable_id' => $booking->getKey(),
                        'payer_user_id' => $customer->getKey(),
                        'gateway_provider' => 'adapter_pending',
                        'gateway_transaction_id' => null,
                        'idempotency_key' => 'booking:' . hash('sha256', $idempotencyKey),
                        'qris_payload' => null,
                        'amount' => $quote['actual_paid_amount'],
                        'gateway_fee_amount' => 0,
                        'status' => Payment::STATUS_PENDING,
                        'paid_at' => null,
                        'expires_at' => $holdExpiresAt,
                    ]);
                }

                return [
                    'booking' => $booking,
                    'hold' => $hold,
                    'payment' => $payment,
                    'quote' => $quote,
                    'idempotent_replay' => false,
                ];
            }, attempts: 3);
        } catch (QueryException $exception) {
            $existingHold = BookingHold::query()
                ->where('idempotency_key', $idempotencyKey)
                ->with('booking.payments')
                ->first();

            if ($existingHold) {
                return $this->replayOrReject(
                    $existingHold,
                    $customer,
                    $venue,
                    $startsAtUtc,
                    $endsAtUtc,
                );
            }

            throw $exception;
        }
    }

    private function replayOrReject(
        BookingHold $hold,
        User $customer,
        Venue $venue,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
    ): array {
        $hold->loadMissing('booking.payments');
        $booking = $hold->booking;

        if (
            ! $booking
            || (int) $hold->customer_id !== (int) $customer->getKey()
            || (int) $hold->venue_id !== (int) $venue->getKey()
            || ! $hold->starts_at->equalTo($startsAtUtc)
            || ! $hold->ends_at->equalTo($endsAtUtc)
        ) {
            throw ValidationException::withMessages([
                'idempotencyKey' => 'Kunci idempotensi sudah digunakan untuk permintaan booking yang berbeda.',
            ]);
        }

        return [
            'booking' => $booking,
            'hold' => $hold,
            'payment' => $booking->payments->first(),
            'quote' => [
                'pricing_unit' => $booking->pricing_unit,
                'duration_units' => $booking->duration_units,
                'base_amount' => $booking->snapshot_base_amount,
                'owner_promotion_id' => $booking->owner_promotion_id,
                'owner_discount_amount' => $booking->snapshot_owner_discount_amount,
                'platform_promotion_id' => $booking->platform_promotion_id,
                'platform_subsidy_amount' => $booking->snapshot_platform_subsidy_amount,
                'actual_paid_amount' => $booking->snapshot_actual_paid_amount,
                'owner_entitlement_before_gateway_fee' => $booking->snapshot_net_owner_entitlement,
                'promotion_quota_to_consume' => array_values(array_filter([
                    $booking->owner_promotion_id,
                    $booking->platform_promotion_id,
                ])),
            ],
            'idempotent_replay' => true,
        ];
    }

    private function consumePromotionQuotas(array $promotionIds): void
    {
        if ($promotionIds === []) {
            return;
        }

        $promotions = VenuePromotion::query()
            ->whereIn('id', $promotionIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($promotions->count() !== count(array_unique($promotionIds))) {
            throw ValidationException::withMessages([
                'promotion' => 'Promosi tidak lagi tersedia. Silakan perbarui ringkasan harga.',
            ]);
        }

        foreach ($promotions as $promotion) {
            if (
                $promotion->status !== VenuePromotion::STATUS_ACTIVE
                || $promotion->starts_at->isFuture()
                || ! $promotion->ends_at->isFuture()
                || ($promotion->max_quota !== null && $promotion->used_count >= $promotion->max_quota)
            ) {
                throw ValidationException::withMessages([
                    'promotion' => 'Kuota atau masa aktif promosi berubah. Silakan ulangi checkout.',
                ]);
            }
        }

        foreach ($promotions as $promotion) {
            $promotion->increment('used_count');
        }
    }

    private function refundPolicySnapshot(Venue $venue): array
    {
        if (is_array($venue->refund_policy_json) && $venue->refund_policy_json !== []) {
            return $venue->refund_policy_json;
        }

        return [
            'source' => 'platform_default',
            'version' => 1,
            'customer_cancellation' => [
                'minimum_minutes_before_start' => 60,
                'refund_percentage' => 100,
            ],
            'owner_cancellation' => [
                'refund_percentage' => 100,
            ],
        ];
    }
}
