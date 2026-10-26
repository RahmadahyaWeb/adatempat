<?php

namespace App\Services\Booking;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingCompletionService
{
    public function startByOwner(User $owner, Booking $booking): Booking
    {
        $initial = Booking::query()->with('venue.business')->whereKey($booking->getKey())->firstOrFail();
        $venueId = $initial->venue_id;

        return DB::transaction(function () use ($owner, $booking, $venueId): Booking {
            Venue::query()->whereKey($venueId)->lockForUpdate()->firstOrFail();
            $locked = Booking::query()->with('venue.business')->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($owner->isOwner() && $owner->status === 'active' && $owner->email_verified_at !== null && (int) $locked->venue->business->owner_id === (int) $owner->getKey(), 404);

            if ($locked->status !== Booking::STATUS_CONFIRMED) {
                throw ValidationException::withMessages([
                    'booking' => 'Hanya booking terkonfirmasi yang dapat dimulai.',
                ]);
            }

            if (CarbonImmutable::now('UTC')->lessThan(CarbonImmutable::instance($locked->booking_start)->utc())) {
                throw ValidationException::withMessages([
                    'booking' => 'Booking belum mencapai waktu mulai.',
                ]);
            }

            $before = $locked->status;
            $locked->update(['status' => Booking::STATUS_IN_PROGRESS]);

            $this->audit($owner, $locked, 'booking.started', $before, Booking::STATUS_IN_PROGRESS, 'Owner menandai booking mulai.');

            return $locked->refresh();
        }, attempts: 3);
    }

    public function requestCompletionByOwner(User $owner, Booking $booking): Booking
    {
        $initial = Booking::query()->with('venue.business')->whereKey($booking->getKey())->firstOrFail();
        $venueId = $initial->venue_id;

        return DB::transaction(function () use ($owner, $booking, $venueId): Booking {
            Venue::query()->whereKey($venueId)->lockForUpdate()->firstOrFail();
            $locked = Booking::query()->with('venue.business')->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($owner->isOwner() && (int) $locked->venue->business->owner_id === (int) $owner->getKey(), 404);

            if ($locked->status !== Booking::STATUS_IN_PROGRESS) {
                throw ValidationException::withMessages([
                    'booking' => 'Hanya booking yang sedang berlangsung dapat diajukan untuk penyelesaian.',
                ]);
            }

            if (CarbonImmutable::now('UTC')->lessThan(CarbonImmutable::instance($locked->booking_end)->utc())) {
                throw ValidationException::withMessages([
                    'booking' => 'Booking belum mencapai waktu selesai yang dijadwalkan.',
                ]);
            }

            $now = CarbonImmutable::now('UTC');
            $before = $locked->status;
            $deadline = CarbonImmutable::instance($locked->booking_end)->utc()->addHours(24);
            $locked->update([
                'status' => Booking::STATUS_AWAITING_COMPLETION,
                'completion_window_ends_at' => $deadline,
            ]);

            $this->audit($owner, $locked, 'booking.awaiting_completion', $before, Booking::STATUS_AWAITING_COMPLETION, 'Owner mengajukan penyelesaian; jendela konfirmasi pelanggan 24 jam.');

            return $locked->refresh();
        }, attempts: 3);
    }

    public function confirmCompletionByCustomer(User $customer, Booking $booking): Booking
    {
        $initial = Booking::query()->with('venue')->whereKey($booking->getKey())->firstOrFail();
        $venueId = $initial->venue_id;

        return DB::transaction(function () use ($customer, $booking, $venueId): Booking {
            Venue::query()->whereKey($venueId)->lockForUpdate()->firstOrFail();
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($customer->isCustomer() && $customer->status === 'active' && $customer->email_verified_at !== null && (int) $locked->customer_id === (int) $customer->getKey(), 404);

            if (
                $locked->status !== Booking::STATUS_AWAITING_COMPLETION
                || $locked->completion_window_ends_at === null
            ) {
                throw ValidationException::withMessages([
                    'booking' => 'Booking tidak sedang menunggu konfirmasi penyelesaian.',
                ]);
            }

            $now = CarbonImmutable::now('UTC');

            if ($now->greaterThan($locked->completion_window_ends_at)) {
                throw ValidationException::withMessages([
                    'booking' => 'Jendela konfirmasi telah berakhir; booking menunggu penyelesaian otomatis.',
                ]);
            }

            $before = $locked->status;
            $locked->update([
                'status' => Booking::STATUS_COMPLETED,
                'completed_at' => $now,
                'completion_window_ends_at' => CarbonImmutable::instance($locked->booking_end)->utc()->addHours(24),
            ]);

            $this->audit($customer, $locked, 'booking.completed_by_customer', $before, Booking::STATUS_COMPLETED, 'Pelanggan mengonfirmasi penyelesaian booking.');

            return $locked->refresh();
        }, attempts: 3);
    }

    public function advanceEndedBookings(int $limit = 100): int
    {
        $bookingIds = Booking::query()
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_IN_PROGRESS])
            ->where('booking_end', '<=', now('UTC'))
            ->orderBy('booking_end')
            ->limit(max(1, min($limit, 500)))
            ->pluck('id');

        $advanced = 0;

        foreach ($bookingIds as $bookingId) {
            $initial = Booking::query()->find($bookingId);

            if (! $initial) {
                continue;
            }

            $didAdvance = DB::transaction(function () use ($initial): bool {
                Venue::query()->whereKey($initial->venue_id)->lockForUpdate()->firstOrFail();
                $booking = Booking::query()->whereKey($initial->getKey())->lockForUpdate()->firstOrFail();

                if (
                    ! in_array($booking->status, [Booking::STATUS_CONFIRMED, Booking::STATUS_IN_PROGRESS], true)
                    || $booking->booking_end->isFuture()
                ) {
                    return false;
                }

                $before = $booking->status;
                $deadline = CarbonImmutable::instance($booking->booking_end)->utc()->addHours(24);
                $booking->update([
                    'status' => Booking::STATUS_AWAITING_COMPLETION,
                    'completion_window_ends_at' => $deadline,
                ]);

                $this->audit(null, $booking, 'booking.awaiting_completion_auto', $before, Booking::STATUS_AWAITING_COMPLETION, 'Waktu booking berakhir; jendela konfirmasi 24 jam dimulai dari booking_end.');

                return true;
            }, attempts: 3);

            if ($didAdvance) {
                $advanced++;
            }
        }

        return $advanced;
    }

    public function autoCompleteExpired(int $limit = 100): int
    {
        $bookingIds = Booking::query()
            ->where('status', Booking::STATUS_AWAITING_COMPLETION)
            ->whereNotNull('completion_window_ends_at')
            ->where('completion_window_ends_at', '<=', now('UTC'))
            ->orderBy('completion_window_ends_at')
            ->limit(max(1, min($limit, 500)))
            ->pluck('id');

        $completed = 0;

        foreach ($bookingIds as $bookingId) {
            $initial = Booking::query()->find($bookingId);

            if (! $initial) {
                continue;
            }

            $didComplete = DB::transaction(function () use ($initial): bool {
                Venue::query()->whereKey($initial->venue_id)->lockForUpdate()->firstOrFail();
                $booking = Booking::query()->whereKey($initial->getKey())->lockForUpdate()->firstOrFail();

                if (
                    $booking->status !== Booking::STATUS_AWAITING_COMPLETION
                    || $booking->completion_window_ends_at === null
                    || $booking->completion_window_ends_at->isFuture()
                ) {
                    return false;
                }

                $before = $booking->status;
                $booking->update([
                    'status' => Booking::STATUS_COMPLETED,
                    'completed_at' => $booking->completion_window_ends_at,
                ]);

                $this->audit(null, $booking, 'booking.auto_completed', $before, Booking::STATUS_COMPLETED, 'Jendela konfirmasi pelanggan 24 jam telah berakhir.');

                return true;
            }, attempts: 3);

            if ($didComplete) {
                $completed++;
            }
        }

        return $completed;
    }

    private function audit(
        ?User $actor,
        Booking $booking,
        string $action,
        string $before,
        string $after,
        string $reason,
    ): void {
        AuditLog::query()->create([
            'actor_user_id' => $actor?->getKey(),
            'actor_role' => $actor?->role ?? 'system',
            'action' => $action,
            'auditable_type' => Booking::class,
            'auditable_id' => $booking->getKey(),
            'reason' => $reason,
            'before_state_json' => ['status' => $before],
            'after_state_json' => [
                'status' => $after,
                'completed_at' => $booking->completed_at?->toISOString(),
                'completion_window_ends_at' => $booking->completion_window_ends_at?->toISOString(),
            ],
            'ip_hash' => request()->ip() ? hash('sha256', (string) request()->ip()) : null,
            'created_at' => now('UTC'),
        ]);
    }
}
