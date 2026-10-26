<?php

namespace App\Services\Disputes;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\DisputeMessage;
use App\Models\Payout;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DisputeService
{
    public function open(User $actor, Booking $booking, string $reasonType, string $description): Dispute
    {
        abort_unless(
            $actor->status === 'active'
            && $actor->email_verified_at !== null
            && ($actor->isCustomer() || $actor->isOwner()),
            403,
        );

        $description = trim($description);

        if (
            ! in_array($reasonType, [
                Dispute::REASON_SERVICE_NOT_PROVIDED,
                Dispute::REASON_VENUE_CONDITION,
                Dispute::REASON_BOOKING_MISMATCH,
                Dispute::REASON_PAYMENT_OR_REFUND,
                Dispute::REASON_OTHER,
            ], true)
            || $description === ''
            || mb_strlen($description) > 5000
        ) {
            throw ValidationException::withMessages([
                'dispute' => 'Kategori dan deskripsi sengketa wajib valid; deskripsi maksimal 5.000 karakter.',
            ]);
        }

        $initial = Booking::query()->with('venue.business')->whereKey($booking->getKey())->firstOrFail();
        $venueId = $initial->venue_id;

        return DB::transaction(function () use ($actor, $booking, $reasonType, $description, $venueId): Dispute {
            Venue::query()->whereKey($venueId)->lockForUpdate()->firstOrFail();
            $locked = Booking::query()->with('venue.business')->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== Booking::STATUS_AWAITING_COMPLETION || $locked->completion_window_ends_at === null || $locked->completion_window_ends_at->isPast()) {
                throw ValidationException::withMessages([
                    'booking' => 'Sengketa hanya dapat dibuka selama jendela konfirmasi penyelesaian 24 jam.',
                ]);
            }

            if ($actor->isCustomer()) {
                abort_unless((int) $locked->customer_id === (int) $actor->getKey(), 404);
                $againstUserId = (int) $locked->venue->business->owner_id;
            } else {
                abort_unless((int) $locked->venue->business->owner_id === (int) $actor->getKey(), 404);
                $againstUserId = (int) $locked->customer_id;
            }

            if (Dispute::query()->where('booking_id', $locked->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'booking' => 'Booking ini sudah memiliki catatan sengketa.',
                ]);
            }

            $payout = Payout::query()->where('booking_id', $locked->getKey())->lockForUpdate()->first();

            if ($payout && in_array($payout->status, [Payout::STATUS_PAID, Payout::STATUS_CANCELLED], true)) {
                throw ValidationException::withMessages([
                    'booking' => 'Payout sudah final; sengketa baru tidak dapat dibuka melalui alur standar.',
                ]);
            }

            $dispute = Dispute::query()->create([
                'reference_code' => 'DSP-' . now('UTC')->format('Ymd') . '-' . Str::upper(Str::random(8)),
                'booking_id' => $locked->getKey(),
                'opened_by' => $actor->getKey(),
                'against_user_id' => $againstUserId,
                'reason_type' => $reasonType,
                'description' => $description,
                'status' => Dispute::STATUS_AWAITING_RESPONSE,
                'response_deadline' => now('UTC')->addHours(48),
                'resolution_type' => null,
                'customer_refund_amount' => 0,
                'owner_payout_amount' => 0,
                'resolution_notes' => null,
                'resolved_by' => null,
                'resolved_at' => null,
            ]);

            DisputeMessage::query()->create([
                'dispute_id' => $dispute->getKey(),
                'sender_user_id' => $actor->getKey(),
                'message' => $description,
                'created_at' => now('UTC'),
            ]);

            $locked->update(['status' => Booking::STATUS_DISPUTED]);

            if ($payout) {
                $payout->update(['status' => Payout::STATUS_ON_HOLD]);
            }

            $this->audit($actor, $dispute, 'dispute.opened', 'Dispute opened and payout held pending Super Admin review.', [
                'booking_status' => Booking::STATUS_DISPUTED,
                'response_deadline' => $dispute->response_deadline?->toISOString(),
            ]);

            return $dispute->refresh();
        }, attempts: 3);
    }

    public function respond(User $actor, Dispute $dispute, string $message): DisputeMessage
    {
        $message = trim($message);

        if ($message === '' || mb_strlen($message) > 5000) {
            throw ValidationException::withMessages([
                'message' => 'Tanggapan wajib diisi dan maksimal 5.000 karakter.',
            ]);
        }

        return DB::transaction(function () use ($actor, $dispute, $message): DisputeMessage {
            $locked = Dispute::query()->whereKey($dispute->getKey())->lockForUpdate()->firstOrFail();

            abort_unless(
                in_array((int) $actor->getKey(), [(int) $locked->opened_by, (int) $locked->against_user_id], true)
                && $actor->status === 'active',
                404,
            );

            if (in_array($locked->status, [Dispute::STATUS_RESOLVED, Dispute::STATUS_REJECTED], true)) {
                throw ValidationException::withMessages([
                    'dispute' => 'Sengketa sudah ditutup dan tidak menerima tanggapan baru.',
                ]);
            }

            $entry = DisputeMessage::query()->create([
                'dispute_id' => $locked->getKey(),
                'sender_user_id' => $actor->getKey(),
                'message' => $message,
                'created_at' => now('UTC'),
            ]);

            $before = $locked->status;
            $locked->update(['status' => Dispute::STATUS_UNDER_REVIEW]);

            $this->audit($actor, $locked, 'dispute.response_added', 'A participant added a written response.', [
                'before_status' => $before,
                'status' => Dispute::STATUS_UNDER_REVIEW,
                'message_id' => $entry->getKey(),
            ]);

            return $entry;
        }, attempts: 3);
    }

    public function addEvidence(User $actor, Dispute $dispute, UploadedFile $file, ?string $caption = null): DisputeEvidence
    {
        abort_unless($actor->status === 'active', 403);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $mime = (string) $file->getMimeType();
        $size = (int) $file->getSize();

        if (! in_array($mime, $allowedMimes, true) || $size < 1 || $size > 10 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'evidenceFile' => 'Bukti harus berupa JPG, PNG, WEBP, atau PDF dengan ukuran maksimal 10 MB.',
            ]);
        }

        $locked = Dispute::query()->findOrFail($dispute->getKey());
        abort_unless(in_array((int) $actor->getKey(), [(int) $locked->opened_by, (int) $locked->against_user_id], true), 404);
        abort_if(in_array($locked->status, [Dispute::STATUS_RESOLVED, Dispute::STATUS_REJECTED], true), 403);

        $path = $file->store('disputes/' . $locked->getKey(), 'local');

        try {
            $evidence = DisputeEvidence::query()->create([
                'dispute_id' => $locked->getKey(),
                'uploaded_by' => $actor->getKey(),
                'file_path' => $path,
                'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 180),
                'mime_type' => $mime,
                'size_bytes' => $size,
                'caption' => filled($caption) ? mb_substr(trim((string) $caption), 0, 255) : null,
                'created_at' => now('UTC'),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        $this->audit($actor, $locked, 'dispute.evidence_added', 'Private evidence uploaded to the dispute.', [
            'evidence_id' => $evidence->getKey(),
            'mime_type' => $mime,
            'size_bytes' => $size,
        ]);

        return $evidence;
    }

    private function audit(User $actor, Dispute $dispute, string $action, string $reason, array $after): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $actor->getKey(),
            'actor_role' => $actor->role,
            'action' => $action,
            'auditable_type' => Dispute::class,
            'auditable_id' => $dispute->getKey(),
            'reason' => $reason,
            'before_state_json' => null,
            'after_state_json' => $after,
            'ip_hash' => request()->ip() ? hash('sha256', (string) request()->ip()) : null,
            'created_at' => now('UTC'),
        ]);
    }
}
