<?php

namespace App\Services\Booking;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Payout;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DisputeService
{
    public function open(User $actor, Booking $booking, string $category, string $description): Dispute
    {
        if (
            ! in_array($actor->role, [User::ROLE_CUSTOMER, User::ROLE_OWNER], true)
            || $actor->status !== 'active'
            || ! $actor->hasVerifiedEmail()
        ) {
            abort(403);
        }

        $category = trim($category);
        $description = trim($description);

        if (
            $category === ''
            || mb_strlen($category) > 80
            || $description === ''
            || mb_strlen($description) < 20
            || mb_strlen($description) > 5000
        ) {
            throw ValidationException::withMessages([
                'category' => 'Kategori wajib diisi dan maksimal 80 karakter.',
                'description' => 'Jelaskan masalah minimal 20 dan maksimal 5.000 karakter.',
            ]);
        }

        $initial = Booking::query()->whereKey($booking->getKey())->firstOrFail();
        $venueId = $initial->venue_id;

        return DB::transaction(function () use ($actor, $booking, $category, $description, $venueId): Dispute {
            Venue::query()->whereKey($venueId)->lockForUpdate()->firstOrFail();
            $locked = Booking::query()->with('venue.business')->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if (
                $actor->isCustomer()
                && (int) $locked->customer_id !== (int) $actor->getKey()
            ) {
                abort(404);
            }

            if (
                $actor->isOwner()
                && (int) $locked->venue->business->owner_id !== (int) $actor->getKey()
            ) {
                abort(404);
            }

            $now = CarbonImmutable::now('UTC');
            $startsAt = CarbonImmutable::instance($locked->booking_start)->utc();
            $endsAt = CarbonImmutable::instance($locked->booking_end)->utc();
            $disputeDeadline = $endsAt->addHours(24);

            if (
                ! in_array($locked->status, [
                    Booking::STATUS_CONFIRMED,
                    Booking::STATUS_IN_PROGRESS,
                    Booking::STATUS_AWAITING_COMPLETION,
                    Booking::STATUS_COMPLETED,
                ], true)
                || $now->lessThan($endsAt)
                || $now->greaterThan($disputeDeadline)
                || $startsAt->greaterThan($endsAt)
            ) {
                throw ValidationException::withMessages([
                    'booking' => 'Sengketa hanya dapat dibuka selama 24 jam setelah waktu booking berakhir.',
                ]);
            }

            if (Dispute::query()->where('booking_id', $locked->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'booking' => 'Booking ini sudah memiliki sengketa.',
                ]);
            }

            $dispute = Dispute::query()->create([
                'reference_code' => 'DSP-' . now('UTC')->format('Ymd') . '-' . Str::upper(Str::random(8)),
                'booking_id' => $locked->getKey(),
                'opened_by_user_id' => $actor->getKey(),
                'opened_by_role' => $actor->role,
                'category' => $category,
                'description' => $description,
                'owner_response_deadline_at' => $actor->isCustomer() ? $now->addHours(48) : $now,
                'owner_responded_at' => null,
                'status' => Dispute::STATUS_OPEN,
                'resolution_notes' => null,
                'refund_amount_awarded' => 0,
                'payout_amount_awarded' => 0,
                'resolved_by' => null,
                'resolved_at' => null,
            ]);

            $before = $locked->status;
            $locked->update(['status' => Booking::STATUS_DISPUTED]);

            Payout::query()
                ->where('booking_id', $locked->getKey())
                ->whereIn('status', [Payout::STATUS_HELD, Payout::STATUS_ELIGIBLE, Payout::STATUS_PROCESSING])
                ->update(['status' => Payout::STATUS_ON_HOLD]);

            AuditLog::query()->create([
                'actor_user_id' => $actor->getKey(),
                'actor_role' => $actor->role,
                'action' => 'booking.dispute_opened',
                'auditable_type' => Dispute::class,
                'auditable_id' => $dispute->getKey(),
                'reason' => 'A booking problem was reported within 24 hours after the scheduled end; payout is placed on hold.',
                'before_state_json' => ['booking_status' => $before],
                'after_state_json' => [
                    'booking_status' => Booking::STATUS_DISPUTED,
                    'dispute_status' => Dispute::STATUS_OPEN,
                    'opened_by_role' => $actor->role,
                    'owner_response_deadline_at' => $dispute->owner_response_deadline_at?->toISOString(),
                ],
                'ip_hash' => request()->ip() ? hash('sha256', (string) request()->ip()) : null,
                'created_at' => now('UTC'),
            ]);

            return $dispute;
        }, attempts: 3);
    }

    public function submitOwnerResponse(User $owner, Dispute $dispute, string $response): Dispute
    {
        if (! $owner->isOwner() || $owner->status !== 'active' || ! $owner->hasVerifiedEmail()) {
            abort(403);
        }

        $response = trim($response);

        if ($response === '' || mb_strlen($response) < 20 || mb_strlen($response) > 5000) {
            throw ValidationException::withMessages([
                'response' => 'Tanggapan wajib berisi 20 hingga 5.000 karakter.',
            ]);
        }

        $initial = Dispute::query()->with('booking')->whereKey($dispute->getKey())->firstOrFail();
        $venueId = $initial->booking->venue_id;

        return DB::transaction(function () use ($owner, $dispute, $response, $venueId): Dispute {
            Venue::query()->whereKey($venueId)->lockForUpdate()->firstOrFail();
            $locked = Dispute::query()->with('booking.venue.business')->whereKey($dispute->getKey())->lockForUpdate()->firstOrFail();

            abort_unless((int) $locked->booking->venue->business->owner_id === (int) $owner->getKey(), 404);

            if (
                $locked->opened_by_role !== User::ROLE_CUSTOMER
                || ! in_array($locked->status, Dispute::OPEN_STATUSES, true)
                || $locked->owner_responded_at !== null
            ) {
                throw ValidationException::withMessages([
                    'dispute' => 'Sengketa ini tidak menerima tanggapan Owner baru.',
                ]);
            }

            if (CarbonImmutable::now('UTC')->greaterThan($locked->owner_response_deadline_at)) {
                throw ValidationException::withMessages([
                    'dispute' => 'Batas waktu tanggapan 48 jam telah berakhir; sengketa tetap dapat diperiksa Super Admin.',
                ]);
            }

            $locked->update([
                'owner_responded_at' => now('UTC'),
                'status' => Dispute::STATUS_UNDER_REVIEW,
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $owner->getKey(),
                'actor_role' => $owner->role,
                'action' => 'booking.dispute_owner_response_submitted',
                'auditable_type' => Dispute::class,
                'auditable_id' => $locked->getKey(),
                'reason' => $response,
                'before_state_json' => ['status' => Dispute::STATUS_OPEN],
                'after_state_json' => [
                    'status' => Dispute::STATUS_UNDER_REVIEW,
                    'owner_responded_at' => $locked->owner_responded_at?->toISOString(),
                ],
                'ip_hash' => request()->ip() ? hash('sha256', (string) request()->ip()) : null,
                'created_at' => now('UTC'),
            ]);

            return $locked->refresh();
        }, attempts: 3);
    }
}
