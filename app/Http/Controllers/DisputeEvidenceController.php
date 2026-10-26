<?php

namespace App\Http\Controllers;

use App\Models\DisputeEvidence;
use App\Services\Booking\DisputeEvidenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DisputeEvidenceController
{
    public function download(Request $request, DisputeEvidence $evidence, DisputeEvidenceService $service)
    {
        $actor = $request->user();
        $dispute = $evidence->dispute()->with('booking.venue.business')->firstOrFail();
        if ($actor->isSuperAdmin()) {
            abort_unless($actor->status === 'active', 403);
            $id = $request->session()->get('admin_mfa_verified_user_id');
            $at = $request->session()->get('admin_mfa_verified_at');
            abort_unless(is_scalar($id) && (string) $id === (string) $actor->getKey()
                && is_numeric($at) && (int) $at <= now()->timestamp
                && (int) $at >= now()->timestamp - 600, 403);
        } else {
            $service->participant($actor, $dispute);
        }
        abort_unless(Storage::disk('local')->exists($evidence->file_path), 404);
        return Storage::disk('local')->download($evidence->file_path, $evidence->original_name, [
            'Content-Type' => $evidence->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
