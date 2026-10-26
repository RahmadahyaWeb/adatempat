<?php

namespace App\Services\Booking;

use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\DisputeMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DisputeEvidenceService
{
    public function participant(User $actor, Dispute $dispute): void
    {
        $dispute->loadMissing('booking.venue.business');
        abort_unless(
            $actor->status === 'active' && $actor->hasVerifiedEmail()
            && (
                ($actor->isCustomer() && (int) $dispute->booking->customer_id === (int) $actor->getKey())
                || ($actor->isOwner() && (int) $dispute->booking->venue->business->owner_id === (int) $actor->getKey())
            ), 404
        );
    }

    public function respond(User $actor, Dispute $dispute, string $message): DisputeMessage
    {
        $message = trim($message);
        if (mb_strlen($message) < 20 || mb_strlen($message) > 5000) {
            throw ValidationException::withMessages(['message' => 'Tanggapan harus berisi 20 sampai 5.000 karakter.']);
        }
        return DB::transaction(function () use ($actor, $dispute, $message): DisputeMessage {
            $locked = Dispute::query()->with('booking.venue.business')->lockForUpdate()->findOrFail($dispute->getKey());
            $this->participant($actor, $locked);
            abort_unless(in_array($locked->status, Dispute::OPEN_STATUSES, true), 403);
            $entry = DisputeMessage::query()->create([
                'dispute_id' => $locked->getKey(),
                'sender_user_id' => $actor->getKey(),
                'message' => $message,
                'created_at' => now('UTC'),
            ]);
            $this->audit($actor, $locked, 'booking.dispute_message_added', ['message_id' => $entry->getKey()]);
            return $entry;
        }, 3);
    }

    public function upload(User $actor, Dispute $dispute, UploadedFile $file, ?string $caption = null): DisputeEvidence
    {
        $locked = Dispute::query()->with('booking.venue.business')->findOrFail($dispute->getKey());
        $this->participant($actor, $locked);
        abort_unless(in_array($locked->status, Dispute::OPEN_STATUSES, true), 403);
        $mime = (string) $file->getMimeType();
        $size = (int) $file->getSize();
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true) || $size < 1 || $size > 10485760 || !$file->isValid()) {
            throw ValidationException::withMessages(['evidenceFile' => 'File harus JPG, PNG, WEBP atau PDF, maksimal 10 MB.']);
        }
        $path = $file->store('disputes/' . $locked->getKey(), 'local');
        if (!$path) {
            throw ValidationException::withMessages(['evidenceFile' => 'Penyimpanan bukti gagal.']);
        }
        try {
            $evidence = DisputeEvidence::query()->create([
                'dispute_id' => $locked->getKey(),
                'uploaded_by' => $actor->getKey(),
                'file_path' => $path,
                'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 180),
                'mime_type' => $mime,
                'size_bytes' => $size,
                'caption' => $caption !== null ? mb_substr(trim($caption), 0, 255) : null,
                'created_at' => now('UTC'),
            ]);
            $this->audit($actor, $locked, 'booking.dispute_evidence_added', ['evidence_id' => $evidence->getKey()]);
            return $evidence;
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    private function audit(User $actor, Dispute $dispute, string $action, array $after): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $actor->getKey(),
            'actor_role' => $actor->role,
            'action' => $action,
            'auditable_type' => Dispute::class,
            'auditable_id' => $dispute->getKey(),
            'reason' => 'Private dispute case activity.',
            'before_state_json' => null,
            'after_state_json' => $after,
            'ip_hash' => request()->ip() ? hash('sha256', (string) request()->ip()) : null,
            'created_at' => now('UTC'),
        ]);
    }
}
