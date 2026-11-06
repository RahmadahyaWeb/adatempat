<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\VenueApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Space;
use App\Models\SpaceAddOn;
use App\Services\Slots\SlotGeneratorService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class SpaceController extends Controller
{
    public function __construct(
        private readonly SlotGeneratorService $slotGenerator,
    ) {}

    public function show(Space $space): Response
    {
        $space->load('venue');

        abort_unless(
            $space->is_active
            && $space->venue->is_published
            && $space->venue->approval_status === VenueApprovalStatus::Approved,
            404,
        );

        $space->load([
            'amenities:id,name,icon',
            'addOns' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
        ]);

        $version = $space->scheduleVersions()
            ->where('effective_from', '<=', today())
            ->where(fn (Builder $query) => $query
                ->whereNull('effective_to')
                ->orWhere('effective_to', '>=', today()))
            ->orderByDesc('version')
            ->with([
                'schedules' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('open_time'),
            ])
            ->first();

        $timezone = $space->venue->timezone ?? config('app.timezone', 'Asia/Jakarta');
        $initialDate = CarbonImmutable::now($timezone)->toDateString();
        $initialSlots = Cache::remember(
            "slots:space:{$space->id}:date:{$initialDate}",
            60,
            fn (): array => $this->slotGenerator->generate($space, $initialDate),
        );

        return Inertia::render('public/spaces/show', [
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'slug' => $space->slug,
                'category' => $space->category->value,
                'category_label' => $space->category->label(),
                'capacity' => $space->capacity,
                'base_price' => (int) $space->base_price,
                'instant_book' => $space->instant_book,
                'cancellation_policy' => $space->cancellation_policy->value,
                'cancellation_policy_label' => $space->cancellation_policy->label(),
                'cancellation_cutoff_hours' => $space->cancellation_cutoff_hours,
                'refund_percentage' => (float) $space->refund_percentage,
                'amenities' => $space->amenities->map(fn ($amenity): array => [
                    'id' => $amenity->id,
                    'name' => $amenity->name,
                    'icon' => $amenity->icon,
                ])->values(),
                'add_ons' => $space->addOns->map(fn (SpaceAddOn $addOn): array => [
                    'id' => $addOn->id,
                    'name' => $addOn->name,
                    'price' => (int) $addOn->price,
                    'pricing_type' => $addOn->pricing_type->value,
                    'pricing_type_label' => $addOn->pricing_type->label(),
                    'stock_quantity' => $addOn->stock_quantity,
                ])->values(),
                'operating_hours' => $version
                    ? $version->schedules->map(fn (Schedule $schedule): array => [
                        'day_of_week' => $schedule->day_of_week,
                        'open_time' => $schedule->open_time,
                        'close_time' => $schedule->close_time,
                        'slot_duration_minutes' => $schedule->slot_duration_minutes,
                    ])->values()
                    : [],
            ],
            'venue' => [
                'id' => $space->venue->id,
                'name' => $space->venue->name,
                'slug' => $space->venue->slug,
                'city' => $space->venue->city,
                'address' => $space->venue->address,
                'timezone' => $space->venue->timezone,
                'contact_phone' => $space->venue->contact_phone,
            ],
            'initial_date' => $initialDate,
            'initial_slots' => $initialSlots,
        ]);
    }
}
