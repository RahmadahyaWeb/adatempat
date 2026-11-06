<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\VenueApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\Space;
use App\Models\Venue;
use Inertia\Inertia;
use Inertia\Response;

class VenueController extends Controller
{
    public function show(Venue $venue): Response
    {
        abort_unless(
            $venue->is_published && $venue->approval_status === VenueApprovalStatus::Approved,
            404,
        );

        $venue->load([
            'spaces' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('base_price'),
            'spaces.amenities:id,name,icon',
        ]);

        return Inertia::render('public/venues/show', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
                'city' => $venue->city,
                'address' => $venue->address,
                'timezone' => $venue->timezone,
                'cover_photo_path' => $venue->cover_photo_path,
                'contact_phone' => $venue->contact_phone,
                'spaces' => $venue->spaces->map(fn (Space $space): array => [
                    'id' => $space->id,
                    'name' => $space->name,
                    'slug' => $space->slug,
                    'category' => $space->category->value,
                    'category_label' => $space->category->label(),
                    'capacity' => $space->capacity,
                    'base_price' => (int) $space->base_price,
                    'amenities' => $space->amenities->map(fn ($amenity): array => [
                        'id' => $amenity->id,
                        'name' => $amenity->name,
                        'icon' => $amenity->icon,
                    ])->values(),
                ])->values(),
            ],
        ]);
    }
}
