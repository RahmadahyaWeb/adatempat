<?php

namespace App\Http\Controllers;

use App\Models\DisputeEvidence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DisputeEvidenceController
{
    public function download(Request $request, DisputeEvidence $evidence)
    {
        $user = $request->user();
        $dispute = $evidence->dispute()->with('booking.venue.business')->firstOrFail();
        $isParticipant = in_array((int) $user->getKey(), [
            (int) $dispute->opened_by,
            (int) $dispute->against_user_id,
        ], true);
        $isAdmin = $user->isSuperAdmin();

        abort_unless($isParticipant || $isAdmin, 404);

        if ($isAdmin) {
            $verifiedUserId = $request->session()->get('admin_mfa_verified_user_id');
            $verifiedAt = $request->session()->get('admin_mfa_verified_at');
            $now = now()->timestamp;

            abort_unless(
                is_scalar($verifiedUserId)
                && (string) $verifiedUserId === (string) $user->getKey()
                && is_numeric($verifiedAt)
                && (int) $verifiedAt <= $now
                && (int) $verifiedAt >= $now - 600,
                403,
            );
        }
        abort_unless(Storage::disk('local')->exists($evidence->file_path), 404);

        return Storage::disk('local')->download(
            $evidence->file_path,
            $evidence->original_name,
            [
                'Content-Type' => $evidence->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}
