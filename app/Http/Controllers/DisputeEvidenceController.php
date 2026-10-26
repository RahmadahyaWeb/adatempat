<?php

namespace App\Http\Controllers;

use App\Models\DisputeEvidence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
