<?php

namespace App\Http\Controllers\Owner;

use App\Enums\AddOnPricingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StoreAddOnRequest;
use App\Http\Requests\Owner\UpdateAddOnRequest;
use App\Models\Space;
use App\Models\SpaceAddOn;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SpaceAddOnController extends Controller
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getPricingTypeOptions(): array
    {
        return collect(AddOnPricingType::cases())->map(fn (AddOnPricingType $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
        ])->values()->all();
    }

    public function index(Request $request, Venue $venue, Space $space): Response
    {
        abort_if($space->venue_id !== $venue->id, 404);
        Gate::authorize('manageAddOns', $space);

        $addOns = $space->addOns()
            ->latest()
            ->get()
            ->map(fn (SpaceAddOn $addOn): array => [
                'id' => $addOn->id,
                'name' => $addOn->name,
                'price' => (float) $addOn->price,
                'pricing_type' => $addOn->pricing_type->value,
                'pricing_type_label' => $addOn->pricing_type->label(),
                'stock_quantity' => (int) $addOn->stock_quantity,
                'is_active' => (bool) $addOn->is_active,
            ])
            ->values()
            ->all();

        return Inertia::render('owner/spaces/add-ons', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
                'city' => $venue->city,
                'timezone' => $venue->timezone,
            ],
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'slug' => $space->slug,
                'category' => $space->category->value,
                'category_label' => $space->category->label(),
                'base_price' => (float) $space->base_price,
            ],
            'addOns' => $addOns,
            'pricingTypes' => $this->getPricingTypeOptions(),
        ]);
    }

    public function store(StoreAddOnRequest $request, Venue $venue, Space $space): RedirectResponse
    {
        abort_if($space->venue_id !== $venue->id, 404);
        Gate::authorize('manageAddOns', $space);

        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);

        /** @var SpaceAddOn $addOn */
        $addOn = $space->addOns()->create($validated);

        return redirect()
            ->route('owner.venues.spaces.add-ons.index', [$venue, $space])
            ->with('success', "Add-on {$addOn->name} berhasil ditambahkan.");
    }

    public function update(UpdateAddOnRequest $request, Venue $venue, Space $space, SpaceAddOn $addOn): RedirectResponse
    {
        abort_if($space->venue_id !== $venue->id, 404);
        abort_if($addOn->space_id !== $space->id, 404);
        Gate::authorize('manageAddOns', $space);

        $addOn->update($request->validated());

        return redirect()
            ->route('owner.venues.spaces.add-ons.index', [$venue, $space])
            ->with('success', "Add-on {$addOn->name} berhasil diperbarui.");
    }

    public function destroy(Venue $venue, Space $space, SpaceAddOn $addOn): RedirectResponse
    {
        abort_if($space->venue_id !== $venue->id, 404);
        abort_if($addOn->space_id !== $space->id, 404);
        Gate::authorize('manageAddOns', $space);

        $hasBookings = DB::table('booking_add_ons')->where('space_add_on_id', $addOn->id)->exists();
        if ($hasBookings) {
            return back()->withErrors([
                'error' => 'Add-on tidak dapat dihapus karena sudah tercatat dalam riwayat pemesanan pelanggan. Silakan ubah status menjadi nonaktif jika tidak ingin menawarkan add-on ini lagi.',
            ]);
        }

        $name = $addOn->name;
        $addOn->delete();

        return redirect()
            ->route('owner.venues.spaces.add-ons.index', [$venue, $space])
            ->with('success', "Add-on {$name} berhasil dihapus.");
    }
}
