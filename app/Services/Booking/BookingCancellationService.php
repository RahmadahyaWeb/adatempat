<?php

namespace App\Services\Booking;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payments\RefundService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingCancellationService
{
    public function __construct(private readonly RefundService $refunds)
    {
    }

    public function cancelByCustomer(User $customer, Booking $booking, ?string $reason = null): array
    {
        if (! $customer->isCustomer() || $customer->status !== 'active') {
            abort(403, 'Hanya pelanggan aktif yang dapat membatalkan booking.');
        }

        $initial = Booking::query()->with('venue')->whereKey($booking->getKey())->firstOrFail();
        $venueId = $initial->venue_id;

        $result = DB::transaction(function () use ($customer, $booking, $reason, $venueId): array {
            Venue::query()->whereKey($venueId)->lockForUpdate()->firstOrFail();

            $payment = Payment::query()
                ->where('payable_type', Payment::TYPE_BOOKING)
                ->where('payable_id', $booking->getKey())
                ->lockForUpdate()
                ->first();

            $lockedBooking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if ((int) $lockedBooking->customer_id !== (int) $customer->getKey()) {
                abort(404);
            }

            if ($lockedBooking->status !== Booking::STATUS_CONFIRMED) {
                throw ValidationException::withMessages([
                    'booking' => 'Pelanggan hanya dapat membatalkan booking yang sudah terkonfirmasi.',
                ]);
            }

            $now = CarbonImmutable::now('UTC');
            $startsAt = CarbonImmutable::instance($lockedBooking->booking_start)->utc();

            if ($startsAt->lessThan($now->addMinutes(60))) {
                throw ValidationException::withMessages([
                    'booking' => 'Pembatalan pelanggan hanya diizinkan minimal 60 menit sebelum waktu mulai.',
                ]);
            }

            $actualPaid = (int) $lockedBooking->snapshot_actual_paid_amount;
            $refundAmount = 0;
            $refund = null;

            if ($actualPaid > 0) {
                if (! $payment || $payment->status !== Payment::STATUS_PAID || (int) $payment->amount !== $actualPaid) {
                    throw ValidationException::withMessages([
                        'payment' => 'Status pembayaran belum dapat diverifikasi untuk pembatalan.',
                    ]);
                }

                $percentage = $this->customerRefundPercentage(
                    $lockedBooking->snapshot_refund_policy_json ?? [],
                    (int) $now->diffInMinutes($startsAt),
                );
                $refundAmount = $this->percentageAmount($actualPaid, $percentage);

                if ($refundAmount > 0) {
                    $refund = $this->refunds->createRequestedForBooking(
                        $lockedBooking,
                        $payment,
                        $refundAmount,
                        'customer_cancellation',
                        filled($reason) ? trim((string) $reason) : 'Pelanggan membatalkan booking.',
                        'refund:booking:' . $lockedBooking->getKey() . ':customer_cancellation',
                    );
                }
            }

            $lockedBooking->update([
                'status' => Booking::STATUS_CANCELLED_BY_CUSTOMER,
                'cancellation_reason' => filled($reason) ? trim((string) $reason) : null,
                'cancelled_by' => $customer->getKey(),
                'cancelled_at' => $now,
                'slot_released_at' => $refundAmount > 0 ? null : $now,
            ]);

            \App\Models\Payout::query()
                ->where('booking_id', $lockedBooking->getKey())
                ->whereIn('status', [
                    \App\Models\Payout::STATUS_HELD,
                    \App\Models\Payout::STATUS_ELIGIBLE,
                    \App\Models\Payout::STATUS_PROCESSING,
                ])
                ->update(['status' => \App\Models\Payout::STATUS_ON_HOLD]);

            $this->auditCancellation(
                $customer,
                $lockedBooking,
                'booking.cancelled_by_customer',
                'Customer cancellation recorded using the immutable refund policy snapshot.',
                $refundAmount,
            );

            return [
                'booking' => $lockedBooking->refresh(),
                'refund' => $refund,
                'refund_amount' => $refundAmount,
            ];
        }, attempts: 3);

        if ($result['refund'] instanceof Refund) {
            $result['refund'] = $this->refunds->submit($result['refund']);
        }

        return $result;
    }

    public function cancelByOwner(User $owner, Booking $booking, string $reason): array
    {
        if (! $owner->isOwner() || $owner->status !== 'active') {
            abort(403, 'Hanya Owner aktif yang dapat membatalkan booking venue miliknya.');
        }

        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan pembatalan Owner wajib diisi dan maksimal 2.000 karakter.',
            ]);
        }

        $initial = Booking::query()->with('venue.business')->whereKey($booking->getKey())->firstOrFail();
        $venueId = $initial->venue_id;

        $result = DB::transaction(function () use ($owner, $booking, $reason, $venueId): array {
            Venue::query()->whereKey($venueId)->lockForUpdate()->firstOrFail();

            $payment = Payment::query()
                ->where('payable_type', Payment::TYPE_BOOKING)
                ->where('payable_id', $booking->getKey())
                ->lockForUpdate()
                ->first();

            $lockedBooking = Booking::query()->with('venue.business')->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if ((int) $lockedBooking->venue->business->owner_id !== (int) $owner->getKey()) {
                abort(404);
            }

            if (! in_array($lockedBooking->status, [
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_IN_PROGRESS,
                Booking::STATUS_AWAITING_COMPLETION,
            ], true)) {
                throw ValidationException::withMessages([
                    'booking' => 'Status booking tidak mengizinkan pembatalan rutin oleh Owner.',
                ]);
            }

            $now = CarbonImmutable::now('UTC');
            $actualPaid = (int) $lockedBooking->snapshot_actual_paid_amount;
            $refund = null;

            if ($actualPaid > 0) {
                if (! $payment || $payment->status !== Payment::STATUS_PAID || (int) $payment->amount !== $actualPaid) {
                    throw ValidationException::withMessages([
                        'payment' => 'Status pembayaran belum dapat diverifikasi untuk pembatalan Owner.',
                    ]);
                }

                $refund = $this->refunds->createRequestedForBooking(
                    $lockedBooking,
                    $payment,
                    $actualPaid,
                    'owner_cancellation',
                    $reason,
                    'refund:booking:' . $lockedBooking->getKey() . ':owner_cancellation',
                );
            }

            $lockedBooking->update([
                'status' => Booking::STATUS_CANCELLED_BY_OWNER,
                'cancellation_reason' => $reason,
                'cancelled_by' => $owner->getKey(),
                'cancelled_at' => $now,
                'slot_released_at' => $now,
            ]);

            \App\Models\Payout::query()
                ->where('booking_id', $lockedBooking->getKey())
                ->whereNotIn('status', [\App\Models\Payout::STATUS_PAID, \App\Models\Payout::STATUS_CANCELLED])
                ->update(['status' => \App\Models\Payout::STATUS_CANCELLED]);

            $this->auditCancellation(
                $owner,
                $lockedBooking,
                'booking.cancelled_by_owner',
                'Owner cancellation releases the slot immediately and initiates a 100% refund when payment was collected.',
                $actualPaid,
            );

            return [
                'booking' => $lockedBooking->refresh(),
                'refund' => $refund,
                'refund_amount' => $actualPaid,
            ];
        }, attempts: 3);

        if ($result['refund'] instanceof Refund) {
            $result['refund'] = $this->refunds->submit($result['refund']);
        }

        return $result;
    }

    private function customerRefundPercentage(array $policy, int $minutesBeforeStart): int
    {
        $customerPolicy = $policy['customer_cancellation'] ?? null;

        if (is_array($customerPolicy) && isset($customerPolicy['refund_percentage'])) {
            return max(0, min(100, (int) $customerPolicy['refund_percentage']));
        }

        $rules = is_array($customerPolicy) && is_array($customerPolicy['rules'] ?? null)
            ? $customerPolicy['rules']
            : (is_array($policy['rules'] ?? null) ? $policy['rules'] : []);

        if ($rules !== []) {
            usort($rules, fn (array $a, array $b): int => (int) ($b['minimum_minutes_before_start'] ?? 0) <=> (int) ($a['minimum_minutes_before_start'] ?? 0));

            foreach ($rules as $rule) {
                if ($minutesBeforeStart >= (int) ($rule['minimum_minutes_before_start'] ?? 0)) {
                    return max(0, min(100, (int) ($rule['refund_percentage'] ?? 0)));
                }
            }

            return 0;
        }

        return 100;
    }

    private function percentageAmount(int $amount, int $percentage): int
    {
        $percentage = max(0, min(100, $percentage));

        return intdiv($amount, 100) * $percentage
            + intdiv(($amount % 100) * $percentage, 100);
    }

    private function auditCancellation(
        User $actor,
        Booking $booking,
        string $action,
        string $reason,
        int $refundAmount,
    ): void {
        AuditLog::query()->create([
            'actor_user_id' => $actor->getKey(),
            'actor_role' => $actor->role,
            'action' => $action,
            'auditable_type' => Booking::class,
            'auditable_id' => $booking->getKey(),
            'reason' => $reason,
            'before_state_json' => null,
            'after_state_json' => [
                'status' => $booking->status,
                'refund_amount' => $refundAmount,
                'slot_released_at' => $booking->slot_released_at?->toISOString(),
            ],
            'ip_hash' => request()->ip() ? hash('sha256', (string) request()->ip()) : null,
            'created_at' => now('UTC'),
        ]);
    }
}
