<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VenueCoverController extends Controller
{
    public function show(Request $request, Venue $venue): StreamedResponse
    {
        $user = $request->user();
        $isAuthorizedUser = $user !== null && (
            $user->role === UserRole::Superadmin ||
            ($venue->business !== null && $venue->business->user_id === $user->id)
        );

        if (! $request->hasValidSignature() && ! $isAuthorizedUser) {
            abort(403, 'Tautan foto cover tidak valid atau telah kedaluwarsa.');
        }

        if (! $venue->cover_photo_path || ! Storage::disk('local')->exists($venue->cover_photo_path)) {
            abort(404, 'Foto cover tidak ditemukan.');
        }

        return Storage::disk('local')->response($venue->cover_photo_path);
    }
}
