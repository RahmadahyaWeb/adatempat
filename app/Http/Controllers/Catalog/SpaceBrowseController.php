<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\SpaceCategory;
use App\Enums\VenueApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\Space;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SpaceBrowseController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', Rule::enum(SpaceCategory::class)],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', Rule::in(['price_asc', 'price_desc'])],
        ]);
        $q = isset($filters['q']) && trim($filters['q']) !== '' ? trim($filters['q']) : null;
        $city = $filters['city'] ?? null;
        $category = $filters['category'] ?? null;
        $date = $filters['date'] ?? null;
        $minPrice = isset($filters['min_price']) ? (int) $filters['min_price'] : null;
        $maxPrice = isset($filters['max_price']) ? (int) $filters['max_price'] : null;
        $sort = $filters['sort'] ?? 'price_asc';
        $spaces = $this->publishedSpaces()
            ->with([
                'venue:id,name,slug,city,timezone,cover_photo_path',
                'amenities:id,name,icon',
            ])
            ->when($q, function (Builder $query, string $q): void {
                $query->where(function (Builder $query) use ($q): void {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhereHas('venue', fn (Builder $query) => $query->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($city, fn (Builder $query, string $city) => $query
                ->whereHas('venue', fn (Builder $query) => $query->where('city', $city)))
            ->when($category, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($minPrice !== null, fn (Builder $query) => $query->where('base_price', '>=', $minPrice))
            ->when($maxPrice !== null, fn (Builder $query) => $query->where('base_price', '<=', $maxPrice))
            ->when($date, function (Builder $query, string $date): void {
                // shortcut: filter tanggal memakai weekday schedule aktif; ketersediaan slot di 1.4.
                $query->whereHas('schedules', fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->where('day_of_week', CarbonImmutable::parse($date)->dayOfWeek));
            })
            ->orderBy('base_price', $sort === 'price_desc' ? 'desc' : 'asc')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Space $space): array => [
                ...$space->toArray(),
                'category_label' => $space->category->label(),
                'base_price' => (int) $space->base_price,
            ]);

        return Inertia::render('public/spaces/index', [
            'spaces' => $spaces,
            'filters' => [
                'q' => $q,
                'city' => $city,
                'category' => $category,
                'date' => $date,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
            ],
            'cities' => $this->cities(),
            'categories' => collect(SpaceCategory::cases())
                ->map(fn (SpaceCategory $category): array => [
                    'value' => $category->value,
                    'label' => $category->label(),
                ])
                ->all(),
            'priceRange' => [
                'min' => (int) $this->publishedSpaces()->min('base_price'),
                'max' => (int) $this->publishedSpaces()->max('base_price'),
            ],
            'sort' => $sort,
        ]);
    }

    /**
     * @return Builder<Space>
     */
    private function publishedSpaces(): Builder
    {
        return Space::query()
            ->where('is_active', true)
            ->whereHas('venue', fn (Builder $query) => $query
                ->where('approval_status', VenueApprovalStatus::Approved)
                ->where('is_published', true));
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
}
