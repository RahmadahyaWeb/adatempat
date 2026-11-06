<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\SpaceCategory;
use App\Enums\VenueApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\Space;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        $venues = Venue::query()
            ->where('approval_status', VenueApprovalStatus::Approved)
            ->where('is_published', true)
            ->withCount(['spaces' => fn (Builder $query) => $query->where('is_active', true)])
            ->withMin(
                ['spaces' => fn (Builder $query) => $query->where('is_active', true)],
                'base_price',
            )
            ->with([
                'spaces' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('base_price')
                    ->select(['id', 'venue_id', 'name', 'slug', 'category', 'base_price']),
            ])
            ->orderBy('name')
            ->limit(6)
            ->get()
            ->map(fn (Venue $venue): array => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
                'city' => $venue->city,
                'address' => $venue->address,
                'cover_photo_path' => $venue->cover_photo_path,
                'spaces_count' => (int) $venue->getAttribute('spaces_count'),
                'starting_price' => (int) ($venue->getAttribute('spaces_min_base_price') ?? 0),
                'spaces' => $venue->spaces->map(fn (Space $space): array => [
                    'id' => $space->id,
                    'name' => $space->name,
                    'slug' => $space->slug,
                    'category' => $space->category->value,
                    'category_label' => $space->category->label(),
                    'base_price' => (int) $space->base_price,
                ])->values(),
            ]);

        return Inertia::render('public/home', [
            'venues' => $venues,
            'cities' => $this->cities(),
            'categories' => $this->categories(),
        ]);
    }

    /**
     * @return Collection<int, string>
     */
    private function cities(): Collection
    {
        return Venue::query()
            ->where('approval_status', VenueApprovalStatus::Approved)
            ->where('is_published', true)
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->map(fn ($city): string => (string) $city)
            ->values();
    }

    /**
     * @return array<int, array{value: string, label: string, count: int}>
     */
    private function categories(): array
    {
        $counts = Space::query()
            ->where('is_active', true)
            ->whereHas('venue', fn (Builder $query) => $query
                ->where('approval_status', VenueApprovalStatus::Approved)
                ->where('is_published', true))
            ->selectRaw('category, COUNT(*) as spaces_count')
            ->groupBy('category')
            ->pluck('spaces_count', 'category');

        return collect(SpaceCategory::cases())
            ->filter(fn (SpaceCategory $category): bool => $counts->has($category->value))
            ->map(fn (SpaceCategory $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
                'count' => (int) $counts->get($category->value),
            ])
            ->values()
            ->all();
    }
}
