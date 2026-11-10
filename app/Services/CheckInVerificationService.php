<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CheckInStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\DuplicateCheckInException;
use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CheckInVerificationService
{
    /**
     * Verify ticket and check-in player at the front desk.
     *
     * @throws HttpException|DuplicateCheckInException
     */
    public function verifyAndCheckIn(User $operator, ?string $qrToken = null, ?string $bookingCode = null): Booking
    {
        if (empty($qrToken) && empty($bookingCode)) {
            throw new HttpException(422, 'Either QR token or booking code is required.');
        }

        return DB::transaction(function () use ($operator, $qrToken, $bookingCode): Booking {
            $query = Booking::query()->lockForUpdate();

            $booking = null;
            if (! empty($qrToken) && ! empty($bookingCode)) {
                $booking = $query->where('qr_token', $qrToken)->first();
                if ($booking && $booking->booking_code !== $bookingCode) {
                    throw new HttpException(422, 'Verification credentials mismatch.');
                }
            } elseif (! empty($qrToken)) {
                $booking = $query->where('qr_token', $qrToken)->first();
            } else {
                $booking = $query->where('booking_code', $bookingCode)->first();
            }

            if (! $booking) {
                throw new HttpException(404, 'Booking not found.');
            }

            // Gate 1: Tenant Matching (prevents cross-venue admissions)
            if (! $operator->can('checkIn', $booking)) {
                throw new HttpException(403, 'This ticket belongs to another venue.');
            }

            // Gate 2: Financial Status (only paid/rescheduled reservations)
            if (! in_array($booking->payment_status, [PaymentStatus::Paid, PaymentStatus::Rescheduled], true)) {
                throw new HttpException(422, 'Only paid bookings can be checked in.');
            }

            // Gate 3: Operational Date (slot date must match today)
            $today = Carbon::today()->toDateString();
            $matchesOperationalDate = $booking->items()
                ->whereHas('courtSlot', function ($slotQuery) use ($today) {
                    $slotQuery->whereDate('date', $today);
                })
                ->exists();

            if (! $matchesOperationalDate) {
                throw new HttpException(422, 'Check-in is only permitted on the scheduled operational date.');
            }

            // Duplicate Check-In Guard
            if ($booking->check_in_status === CheckInStatus::CheckedIn) {
                $booking->load('checkedInBy');
                $staffName = $booking->checkedInBy?->name ?? 'Front-desk Staff';
                $checkedInAt = $booking->checked_in_at?->toIso8601String() ?? now()->toIso8601String();

                throw new DuplicateCheckInException(
                    message: "This digital ticket has already been checked in at {$checkedInAt} by operator {$staffName}.",
                    checkedInAt: $checkedInAt,
                    checkedInBy: $booking->checked_in_by,
                    staffName: $staffName,
                );
            }

            // Valid check-in execution
            $now = Carbon::now();
            $booking->update([
                'check_in_status' => CheckInStatus::CheckedIn,
                'checked_in_at' => $now,
                'checked_in_by' => $operator->id,
            ]);

            return $booking->fresh(['venue', 'items.courtSlot.court', 'checkedInBy']);
        });
    }

    /**
     * Periodically transition elapsed paid/rescheduled bookings into completed status.
     */
    public function markElapsedBookingsCompleted(): int
    {
        $now = Carbon::now();
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');

        return DB::transaction(function () use ($today, $currentTime): int {
            $bookings = Booking::query()
                ->whereIn('payment_status', [PaymentStatus::Paid, PaymentStatus::Rescheduled])
                ->whereHas('items')
                ->whereDoesntHave('items.courtSlot', function ($query) use ($today, $currentTime) {
                    $query->where('date', '>', $today)
                        ->orWhere(function ($q) use ($today, $currentTime) {
                            $q->where('date', '=', $today)
                                ->where('end_time', '>', $currentTime);
                        });
                })
                ->lockForUpdate()
                ->get();

            $count = 0;
            foreach ($bookings as $booking) {
                $booking->update([
                    'payment_status' => PaymentStatus::Completed,
                ]);
                $count++;
            }

            return $count;
        });
    }
}
