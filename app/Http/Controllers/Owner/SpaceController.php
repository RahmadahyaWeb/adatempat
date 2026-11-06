<?php

namespace App\Http\Controllers\Owner;

use App\Enums\CancellationPolicy;
use App\Enums\SpaceCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StoreSpaceRequest;
use App\Http\Requests\Owner\UpdateSpaceRequest;
use App\Models\Space;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SpaceController extends Controller
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getCategoryOptions(): array
    {
        return collect(SpaceCategory::cases())->map(fn (SpaceCategory $category): array => [
            'value' => $category->value,
            'label' => $category->label(),
        ])->values()->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getCancellationOptions(): array
    {
        return collect(CancellationPolicy::cases())->map(fn (CancellationPolicy $policy): array => [
            'value' => $policy->value,
            'label' => $policy->label(),
        ])->values()->all();
    }

    public function index(Request $request, Venue $venue): Response
    {
        Gate::authorize('viewAny', [Space::class, $venue]);

        /** @var LengthAwarePaginator<int, Space> $paginator */
        $paginator = $venue->spaces()
            ->withCount(['addOns', 'bookings'])
            ->latest()
            ->paginate(15);

        $spaces = $paginator->through(fn (Space $space): array => [
            'id' => $space->id,
            'name' => $space->name,
            'slug' => $space->slug,
            'category' => $space->category->value,
            'category_label' => $space->category->label(),
            'capacity' => $space->capacity,
            'base_price' => (float) $space->base_price,
            'instant_book' => $space->instant_book,
            'cancellation_policy' => $space->cancellation_policy->value,
            'cancellation_policy_label' => $space->cancellation_policy->label(),
            'cancellation_cutoff_hours' => $space->cancellation_cutoff_hours,
            'refund_percentage' => (float) $space->refund_percentage,
            'is_active' => $space->is_active,
            'add_ons_count' => (int) $space->add_ons_count,
            'bookings_count' => (int) $space->bookings_count,
        ]);

        return Inertia::render('owner/spaces/index', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
                'city' => $venue->city,
                'timezone' => $venue->timezone,
                'is_published' => $venue->is_published,
            ],
            'spaces' => $spaces,
        ]);
    }

    public function create(Venue $venue): Response
    {
        Gate::authorize('create', [Space::class, $venue]);

        return Inertia::render('owner/spaces/create', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
                'city' => $venue->city,
                'timezone' => $venue->timezone,
            ],
            'categories' => $this->getCategoryOptions(),
            'cancellationPolicies' => $this->getCancellationOptions(),
        ]);
    }

    public function store(StoreSpaceRequest $request, Venue $venue): RedirectResponse
    {
        Gate::authorize('create', [Space::class, $venue]);

        $baseSlug = Str::slug($request->string('name')->value());
        $slug = $baseSlug;
        $counter = 1;
        while (Space::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $validated = $request->validated();
        $validated['slug'] = $slug;
        $validated['is_active'] = $request->boolean('is_active', true);

        /** @var Space $space */
        $space = $venue->spaces()->create($validated);

        // Inisialisasi schedule version v1 dengan template 7 hari default
        $user = $request->user();
        if ($user !== null) {
            $version = $space->scheduleVersions()->create([
                'version' => 1,
                'effective_from' => today(),
                'created_by' => $user->id,
            ]);

            for ($day = 0; $day <= 6; $day++) {
                $version->schedules()->create([
                    'space_id' => $space->id,
                    'day_of_week' => $day,
                    'open_time' => '08:00',
                    'close_time' => '22:00',
                    'slot_duration_minutes' => 60,
                    'is_active' => true,
                ]);
            }
        }

        return redirect()
            ->route('owner.venues.spaces.index', $venue)
            ->with('success', "Space {$space->name} berhasil ditambahkan beserta jadwal operasional awal.");
    }

    public function edit(Venue $venue, Space $space): Response
    {
        abort_if($space->venue_id !== $venue->id, 404);
        Gate::authorize('update', $space);

        return Inertia::render('owner/spaces/edit', [
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
                'capacity' => $space->capacity,
                'base_price' => (float) $space->base_price,
                'instant_book' => $space->instant_book,
                'cancellation_policy' => $space->cancellation_policy->value,
                'cancellation_cutoff_hours' => $space->cancellation_cutoff_hours,
                'refund_percentage' => (float) $space->refund_percentage,
                'is_active' => $space->is_active,
            ],
            'categories' => $this->getCategoryOptions(),
            'cancellationPolicies' => $this->getCancellationOptions(),
        ]);
    }

    public function update(UpdateSpaceRequest $request, Venue $venue, Space $space): RedirectResponse
    {
        abort_if($space->venue_id !== $venue->id, 404);
        Gate::authorize('update', $space);

        $validated = $request->validated();

        if ($request->string('name')->value() !== $space->name) {
            $baseSlug = Str::slug($request->string('name')->value());
            $slug = $baseSlug;
            $counter = 1;
            while (Space::query()->where('slug', $slug)->where('id', '!=', $space->id)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }
            $validated['slug'] = $slug;
        }

        $space->update($validated);

        return redirect()
            ->route('owner.venues.spaces.index', $venue)
            ->with('success', "Data space {$space->name} berhasil diperbarui.");
    }

    public function destroy(Venue $venue, Space $space): RedirectResponse
    {
        abort_if($space->venue_id !== $venue->id, 404);
        Gate::authorize('delete', $space);

        if ($space->bookings()->exists()) {
            return back()->withErrors([
                'error' => 'Space tidak dapat dihapus karena sudah memiliki riwayat pemesanan. Nonaktifkan status space jika ingin menutup pemesanan.',
            ]);
        }

        $name = $space->name;
        $space->delete();

        return redirect()
            ->route('owner.venues.spaces.index', $venue)
            ->with('success', "Space {$name} berhasil dihapus.");
    }
}
