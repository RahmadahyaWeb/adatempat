<?php

use App\Models\Category;
use App\Models\Venue;
use App\Services\Booking\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $keyword = '';

    public string $location = '';

    public string $category = '';

    public string $minPrice = '';

    public string $maxPrice = '';

    public string $minCapacity = '';

    public array $selectedFacilities = [];

    public string $availabilityDate = '';

    public string $startTime = '';

    public string $endTime = '';

    public string $sort = 'newest';

    public bool $hasSearched = false;

    public array $popularSearches = ['Pernikahan', 'Meeting', 'Ulang tahun', 'Outdoor'];

    public function availableFacilities(): array
    {
        return [
            'Wi-Fi',
            'AC',
            'Parkir',
            'Toilet',
            'Mushola',
            'Akses kursi roda',
            'Ruang ganti',
        ];
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function venues(): Collection
    {
        $keyword = trim($this->keyword);
        $location = trim($this->location);
        $category = $this->category;
        $minPrice = ctype_digit($this->minPrice) ? (int) $this->minPrice : null;
        $maxPrice = ctype_digit($this->maxPrice) ? (int) $this->maxPrice : null;
        $minCapacity = ctype_digit($this->minCapacity) ? (int) $this->minCapacity : null;

        $venues = Venue::query()
            ->published()
            ->with([
                'business',
                'category',
                'media',
                'prices',
                'availabilityRules',
            ])
            ->when($keyword !== '', function (Builder $query) use ($keyword): void {
                $query->where(function (Builder $searchQuery) use ($keyword): void {
                    $searchQuery
                        ->where('venues.name', 'like', "%{$keyword}%")
                        ->orWhere('venues.description', 'like', "%{$keyword}%")
                        ->orWhereHas('business', function (Builder $businessQuery) use ($keyword): void {
                            $businessQuery
                                ->where('businesses.name', 'like', "%{$keyword}%")
                                ->orWhere('businesses.address', 'like', "%{$keyword}%");
                        })
                        ->orWhereHas('category', function (Builder $categoryQuery) use ($keyword): void {
                            $categoryQuery->where('categories.name', 'like', "%{$keyword}%");
                        });
                });
            })
            ->when($location !== '', function (Builder $query) use ($location): void {
                $query->whereHas('business', function (Builder $businessQuery) use ($location): void {
                    $businessQuery->where('businesses.address', 'like', "%{$location}%");
                });
            })
            ->when($category !== '', function (Builder $query) use ($category): void {
                $query->whereHas('category', function (Builder $categoryQuery) use ($category): void {
                    $categoryQuery
                        ->where('categories.slug', $category)
                        ->where('categories.status', Category::STATUS_ACTIVE);
                });
            })
            ->when($minCapacity !== null, fn (Builder $query) => $query->where('venues.capacity_persons', '>=', $minCapacity))
            ->when($minPrice !== null || $maxPrice !== null, function (Builder $query) use ($minPrice, $maxPrice): void {
                $query->whereHas('prices', function (Builder $priceQuery) use ($minPrice, $maxPrice): void {
                    $priceQuery->where('is_active', true)
                        ->when($minPrice !== null, fn (Builder $q) => $q->where('price_amount', '>=', $minPrice))
                        ->when($maxPrice !== null, fn (Builder $q) => $q->where('price_amount', '<=', $maxPrice));
                });
            });

        foreach ($this->selectedFacilities as $facility) {
            if (in_array($facility, $this->availableFacilities(), true)) {
                $venues->whereJsonContains('venues.facilities_json', $facility);
            }
        }

        $items = $venues
            ->orderByDesc('venues.published_at')
            ->orderByDesc('venues.id')
            ->limit(60)
            ->get();

        if ($this->hasCompleteAvailabilityFilter()) {
            $availability = app(AvailabilityService::class);

            $items = $items->filter(function (Venue $venue) use ($availability): bool {
                $timezone = $venue->business?->timezone ?: 'Asia/Jakarta';

                try {
                    $startsAtUtc = CarbonImmutable::parse(
                        $this->availabilityDate . ' ' . $this->startTime,
                        $timezone,
                    )->utc();

                    $endsAtUtc = CarbonImmutable::parse(
                        $this->availabilityDate . ' ' . $this->endTime,
                        $timezone,
                    )->utc();
                } catch (\Throwable) {
                    return false;
                }

                return $availability->isAvailable($venue, $startsAtUtc, $endsAtUtc);
            })->values();
        }

        $items = match ($this->sort) {
            'price_low' => $items->sortBy(fn (Venue $venue) => $this->displayPriceFor($venue) ?? PHP_INT_MAX)->values(),
            'price_high' => $items->sortByDesc(fn (Venue $venue) => $this->displayPriceFor($venue) ?? -1)->values(),
            'capacity_high' => $items->sortByDesc(fn (Venue $venue) => (int) ($venue->capacity_persons ?? 0))->values(),
            default => $items,
        };

        return $items->take(18)->values();
    }

    private function displayPriceFor(Venue $venue): ?int
    {
        $amounts = $venue->prices
            ->where('is_active', true)
            ->flatMap(fn ($price) => [$price->price_amount, $price->weekend_price_amount])
            ->filter(fn ($amount) => $amount !== null)
            ->map(fn ($amount) => (int) $amount);

        return $amounts->isEmpty() ? null : $amounts->min();
    }

    private function hasCompleteAvailabilityFilter(): bool
    {
        return $this->availabilityDate !== ''
            && $this->startTime !== ''
            && $this->endTime !== '';
    }

    private function validateDiscoveryFilters(): bool
    {
        $this->resetValidation();

        if ($this->minPrice !== '' && (! ctype_digit($this->minPrice) || (int) $this->minPrice < 0)) {
            $this->addError('minPrice', 'Harga minimum harus berupa angka yang valid.');
        }

        if ($this->maxPrice !== '' && (! ctype_digit($this->maxPrice) || (int) $this->maxPrice < 0)) {
            $this->addError('maxPrice', 'Harga maksimum harus berupa angka yang valid.');
        }

        if (
            ctype_digit($this->minPrice)
            && ctype_digit($this->maxPrice)
            && (int) $this->minPrice > (int) $this->maxPrice
        ) {
            $this->addError('maxPrice', 'Harga maksimum harus lebih besar atau sama dengan harga minimum.');
        }

        if ($this->minCapacity !== '' && (! ctype_digit($this->minCapacity) || (int) $this->minCapacity < 1)) {
            $this->addError('minCapacity', 'Kapasitas minimum harus minimal 1 orang.');
        }

        if (! in_array($this->sort, ['newest', 'price_low', 'price_high', 'capacity_high'], true)) {
            $this->addError('sort', 'Urutan hasil tidak valid.');
        }

        $timeParts = [
            $this->availabilityDate !== '',
            $this->startTime !== '',
            $this->endTime !== '',
        ];

        if (in_array(true, $timeParts, true) && in_array(false, $timeParts, true)) {
            $this->addError('availabilityDate', 'Tanggal, jam mulai, dan jam selesai harus diisi bersama.');
        }

        if ($this->hasCompleteAvailabilityFilter()) {
            try {
                $date = CarbonImmutable::createFromFormat('Y-m-d', $this->availabilityDate, 'Asia/Jakarta');
            } catch (\Throwable) {
                $date = null;
            }

            if ($date === null || $date->format('Y-m-d') !== $this->availabilityDate) {
                $this->addError('availabilityDate', 'Tanggal pencarian tidak valid.');
            } elseif ($date->startOfDay()->lessThan(CarbonImmutable::now('Asia/Jakarta')->startOfDay())) {
                $this->addError('availabilityDate', 'Tanggal pencarian tidak boleh di masa lalu.');
            }

            if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $this->startTime)) {
                $this->addError('startTime', 'Jam mulai tidak valid.');
            }

            if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $this->endTime)) {
                $this->addError('endTime', 'Jam selesai tidak valid.');
            }

            if (
                preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $this->startTime)
                && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $this->endTime)
                && $this->endTime <= $this->startTime
            ) {
                $this->addError('endTime', 'Jam selesai harus lebih akhir daripada jam mulai.');
            }
        }

        foreach ($this->selectedFacilities as $facility) {
            if (! in_array($facility, $this->availableFacilities(), true)) {
                $this->addError('selectedFacilities', 'Fasilitas yang dipilih tidak valid.');
                break;
            }
        }

        return $this->getErrorBag()->isEmpty();
    }


    public function search(): void
    {
        if (! $this->validateDiscoveryFilters()) {
            return;
        }

        $this->hasSearched = true;
        unset($this->venues);
        $this->scrollToResults();
    }

    public function applyDiscoveryFilters(): void
    {
        $this->search();
    }

    public function updatedSort(): void
    {
        if (! in_array($this->sort, ['newest', 'price_low', 'price_high', 'capacity_high'], true)) {
            $this->sort = 'newest';
        }

        $this->hasSearched = true;
        unset($this->venues);
    }


    private function scrollToResults(): void
    {
        $this->js(<<<'JS'
            const target = document.getElementById('hasil');

            if (target) {
                target.scrollIntoView({
                    behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                    block: 'start',
                });
            }
        JS);
    }


    public function chooseCategory(string $slug): void
    {
        $exists = Category::query()
            ->active()
            ->where('slug', $slug)
            ->exists();

        if (! $exists) {
            return;
        }

        $this->category = $this->category === $slug ? '' : $slug;
        $this->hasSearched = true;
        unset($this->venues);
        $this->scrollToResults();
    }

    public function choosePopularSearch(string $search): void
    {
        if (! in_array($search, $this->popularSearches, true)) {
            return;
        }

        $this->keyword = $search;
        $this->hasSearched = true;
        unset($this->venues);
        $this->scrollToResults();
    }

    public function resetSearch(): void
    {
        $this->reset([
            'keyword',
            'location',
            'category',
            'minPrice',
            'maxPrice',
            'minCapacity',
            'selectedFacilities',
            'availabilityDate',
            'startTime',
            'endTime',
            'sort',
        ]);

        $this->sort = 'newest';
        $this->hasSearched = false;
        $this->resetValidation();
        unset($this->venues);
    }
};
?>


<div>
    @php
        $categoryImages = [
            'acara' => 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=900&q=85',
            'meeting' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=900&q=85',
            'foto' => 'https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=900&q=85',
            'outdoor' => 'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=900&q=85',
        ];
        $defaultCategoryImage = 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=900&q=85';
        $isFiltered = $hasSearched
            || trim($keyword) !== ''
            || trim($location) !== ''
            || $category !== ''
            || $minPrice !== ''
            || $maxPrice !== ''
            || $minCapacity !== ''
            || count($selectedFacilities) > 0
            || $availabilityDate !== ''
            || $startTime !== ''
            || $endTime !== '';
        $resultCount = $this->venues->count();
        $selectedCategory = $category !== '' ? $this->categories->firstWhere('slug', $category) : null;
        $advancedFilterCount = collect([
            $category !== '',
            $minPrice !== '',
            $maxPrice !== '',
            $minCapacity !== '',
            count($selectedFacilities) > 0,
            $availabilityDate !== '' || $startTime !== '' || $endTime !== '',
        ])->filter()->count();
    @endphp

    <section aria-labelledby="hero-heading" class="hero-section">
        <div class="site-container hero-grid">
            <div class="hero-copy">
                <p class="eyebrow"><span class="eyebrow-dot"></span> Adatempat / Jelajahi venue</p>
                <h1 id="hero-heading" class="hero-title">
                    Temukan tempat
                    <span class="block"><span class="hero-title-accent">yang tepat.</span></span>
                </h1>
                <p class="hero-description">
                    Cari venue untuk acara, pertemuan, produksi foto, atau kebutuhan lainnya. Mulai dari kategori dan lokasi, lalu jelajahi pilihan yang tersedia.
                </p>

                <div id="jelajahi" class="search-panel scroll-mt-28">
                    <form role="search" aria-label="Cari tempat" wire:submit="search">
                        <div class="search-fields">
                            <div class="search-field">
                                <label for="keyword" class="ui-label">Kebutuhan atau nama tempat</label>
                                <div class="search-input-wrap">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="size-5 shrink-0 text-[var(--brand-forest)]"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.2 4.2"/></svg>
                                    <input id="keyword" name="keyword" type="search" wire:model.live.debounce.400ms="keyword" placeholder="Mis. meeting atau pernikahan" autocomplete="off" class="search-input">
                                </div>
                            </div>
                            <div class="search-field">
                                <label for="location" class="ui-label">Lokasi</label>
                                <div class="search-input-wrap">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5 shrink-0 text-[var(--brand-forest)]"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                    <input id="location" name="location" type="text" wire:model.live.debounce.400ms="location" placeholder="Kota atau daerah" autocomplete="address-level2" class="search-input">
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 sm:mt-4">
                            <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full sm:min-w-36">
                                <span wire:loading.remove wire:target="search">Cari tempat</span>
                                <span wire:loading wire:target="search">Mencari...</span>
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M4 12h15m-6-6 6 6-6 6"/></svg>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-2">
                    <span class="mr-1 text-xs font-semibold text-[var(--muted)]">Coba cari</span>
                    @foreach ($popularSearches as $search)
                        <button type="button" wire:key="popular-search-{{ $loop->index }}" wire:click="choosePopularSearch('{{ $search }}')" aria-pressed="{{ $keyword === $search ? 'true' : 'false' }}" class="inline-flex min-h-9 items-center rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors {{ $keyword === $search ? 'border-[var(--brand-forest)] bg-[var(--brand-forest)] text-white' : 'border-[var(--border)] bg-white text-[var(--ink-soft)] hover:bg-[var(--surface-soft)]' }}">
                            {{ $search }}
                        </button>
                    @endforeach
                </div>
            </div>

            <figure class="hero-photo-wrap">
                <div class="hero-photo">
                    <img src="https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=1400&q=88" alt="Contoh suasana ruang acara dengan meja dan kursi yang tertata" fetchpriority="high" decoding="async">
                </div>
                <figcaption class="hero-photo-caption">
                    <span><strong>Inspirasi suasana ruang</strong><br>Gunakan pencarian untuk menemukan listing venue.</span>
                    <span class="hidden text-right sm:block">01 / Jelajahi</span>
                </figcaption>
            </figure>
        </div>
    </section>

    <section id="kategori" aria-labelledby="category-heading" class="scroll-mt-24 bg-[var(--surface-soft)]">
        <div class="site-container py-10 sm:py-14 lg:py-16">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <p class="eyebrow"><span class="eyebrow-dot"></span> Mulai dari jenis kegiatan</p>
                    <h2 id="category-heading" class="mt-3 text-2xl font-extrabold leading-tight tracking-[-0.05em] sm:text-3xl lg:text-4xl">Tempat untuk berbagai kebutuhan.</h2>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-[var(--ink-soft)] sm:text-base">Pilih kategori untuk mempersempit daftar tempat yang sudah dipublikasikan.</p>
                </div>
                @if ($category !== '')
                    <button type="button" wire:click="resetSearch" class="btn btn-secondary w-full sm:w-auto">Hapus filter kategori</button>
                @endif
            </div>

            <div class="mt-6 grid grid-cols-2 gap-3 sm:mt-8 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4">
                @forelse ($this->categories as $item)
                    <button type="button" wire:key="category-{{ $item->id }}" wire:click="chooseCategory('{{ $item->slug }}')" aria-pressed="{{ $category === $item->slug ? 'true' : 'false' }}" class="category-tile group {{ $category === $item->slug ? 'ring-2 ring-[var(--brand-forest)] ring-offset-2' : '' }}">
                        <img src="{{ $categoryImages[$item->slug] ?? $defaultCategoryImage }}" alt="" loading="lazy" decoding="async">
                        <span class="relative w-full p-3.5 sm:p-5">
                            <span class="block text-[0.65rem] font-bold uppercase tracking-[0.08em] text-white/80">Jelajahi</span>
                            <span class="mt-1.5 flex items-end justify-between gap-2 text-left">
                                <span class="text-base font-extrabold leading-tight tracking-[-0.035em] sm:text-xl">{{ $item->name }}</span>
                                <span class="mb-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-[var(--brand-lime)] text-[var(--ink)]">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M4 12h15m-6-6 6 6-6 6"/></svg>
                                </span>
                            </span>
                        </span>
                    </button>
                @empty
                    <div class="col-span-full rounded-xl bg-white p-5 text-sm leading-6 text-[var(--ink-soft)]">
                        Kategori belum tersedia. Kamu tetap bisa mencari berdasarkan nama tempat atau lokasi.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section id="hasil" aria-labelledby="results-heading" class="scroll-mt-24">
        <div class="site-container py-10 sm:py-14 lg:py-16">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <p class="eyebrow"><span class="eyebrow-dot"></span> {{ $isFiltered ? 'Hasil pencarian' : 'Tempat yang dipublikasikan' }}</p>
                    <h2 id="results-heading" class="mt-3 text-2xl font-extrabold leading-tight tracking-[-0.05em] sm:text-3xl lg:text-4xl">
                        {{ $isFiltered ? 'Pilihan sesuai pencarianmu.' : 'Jelajahi tempat yang tersedia.' }}
                    </h2>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-[var(--ink-soft)] sm:text-base">
                        {{ $isFiltered ? $resultCount . ' tempat ditemukan berdasarkan pencarian aktif.' : 'Informasi yang ditampilkan berasal dari listing yang dipublikasikan oleh pemilik venue.' }}
                    </p>

                    @if ($isFiltered)
                        <div class="mt-4 flex flex-wrap items-center gap-2" aria-label="Filter pencarian aktif">
                            @if (trim($keyword) !== '')
                                <span class="inline-flex min-h-8 items-center rounded-lg bg-[var(--surface-soft)] px-3 py-1 text-xs font-bold text-[var(--ink-soft)]">
                                    {{ trim($keyword) }}
                                </span>
                            @endif
                            @if (trim($location) !== '')
                                <span class="inline-flex min-h-8 items-center rounded-lg bg-[var(--surface-soft)] px-3 py-1 text-xs font-bold text-[var(--ink-soft)]">
                                    {{ trim($location) }}
                                </span>
                            @endif
                            @if ($selectedCategory)
                                <span class="inline-flex min-h-8 items-center rounded-lg bg-[var(--surface-lime)] px-3 py-1 text-xs font-bold text-[var(--brand-forest-deep)]">
                                    {{ $selectedCategory->name }}
                                </span>
                            @endif
                            @if ($minPrice !== '' || $maxPrice !== '')
                                <span class="inline-flex min-h-8 items-center rounded-lg bg-[var(--surface-soft)] px-3 py-1 text-xs font-bold text-[var(--ink-soft)]">
                                    Harga {{ $minPrice !== '' ? 'Rp ' . number_format((int) $minPrice, 0, ',', '.') : 'bebas' }} — {{ $maxPrice !== '' ? 'Rp ' . number_format((int) $maxPrice, 0, ',', '.') : 'tanpa batas' }}
                                </span>
                            @endif
                            @if ($minCapacity !== '')
                                <span class="inline-flex min-h-8 items-center rounded-lg bg-[var(--surface-soft)] px-3 py-1 text-xs font-bold text-[var(--ink-soft)]">≥ {{ number_format((int) $minCapacity, 0, ',', '.') }} orang</span>
                            @endif
                            @foreach ($selectedFacilities as $facility)
                                <span class="inline-flex min-h-8 items-center rounded-lg bg-[var(--surface-soft)] px-3 py-1 text-xs font-bold text-[var(--ink-soft)]">{{ $facility }}</span>
                            @endforeach
                            @if ($availabilityDate !== '' && $startTime !== '' && $endTime !== '')
                                <span class="inline-flex min-h-8 items-center rounded-lg bg-[var(--surface-soft)] px-3 py-1 text-xs font-bold text-[var(--ink-soft)]">
                                    {{ CarbonCarbonImmutable::parse($availabilityDate)->translatedFormat('d M Y') }}, {{ $startTime }}–{{ $endTime }}
                                </span>
                            @endif
                            <a href="#jelajahi" class="inline-flex min-h-8 items-center px-1 text-xs font-bold text-[var(--brand-forest)] underline-offset-4 hover:underline">
                                Ubah pencarian
                            </a>
                        </div>
                    @endif
                </div>
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:min-w-52">
                    <label for="discovery-sort" class="ui-label !mb-0">Urutkan hasil</label>
                    <div class="ui-select-wrap">
                        <select id="discovery-sort" wire:model.live="sort" class="ui-input ui-select">
                            <option value="newest">Terbaru dipublikasikan</option>
                            <option value="price_low">Harga terendah</option>
                            <option value="price_high">Harga tertinggi</option>
                            <option value="capacity_high">Kapasitas terbesar</option>
                        </select>
                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="ui-select-chevron">
                            <path d="m6 8 4 4 4-4"/>
                        </svg>
                    </div>
                </div>
            </div>

            <details class="discovery-filters mt-6" @if ($advancedFilterCount > 0) open @endif>
                <summary>
                    <span>
                        Filter lainnya
                        @if ($advancedFilterCount > 0)
                            <strong>{{ $advancedFilterCount }}</strong>
                        @endif
                    </span>
                    <span class="discovery-filter-summary-hint">Harga, kapasitas, fasilitas, jadwal</span>
                </summary>

                <form wire:submit="applyDiscoveryFilters" class="discovery-filter-body">
                    <div class="discovery-filter-grid">
                        <div>
                            <label for="filter-category" class="ui-label">Kategori</label>
                            <select id="filter-category" wire:model="category" class="ui-input">
                                <option value="">Semua kategori</option>
                                @foreach ($this->categories as $item)
                                    <option value="{{ $item->slug }}">{{ $item->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="filter-capacity" class="ui-label">Kapasitas minimum</label>
                            <input id="filter-capacity" type="number" min="1" inputmode="numeric" wire:model="minCapacity" class="ui-input" placeholder="Mis. 50 orang">
                            @error('minCapacity') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="filter-min-price" class="ui-label">Harga minimum</label>
                            <input id="filter-min-price" type="number" min="0" step="1000" inputmode="numeric" wire:model="minPrice" class="ui-input" placeholder="Rp 0">
                            @error('minPrice') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="filter-max-price" class="ui-label">Harga maksimum</label>
                            <input id="filter-max-price" type="number" min="0" step="1000" inputmode="numeric" wire:model="maxPrice" class="ui-input" placeholder="Tanpa batas">
                            @error('maxPrice') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="filter-date" class="ui-label">Tanggal</label>
                            <input id="filter-date" type="date" min="{{ now('Asia/Jakarta')->format('Y-m-d') }}" wire:model="availabilityDate" class="ui-input">
                            @error('availabilityDate') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="filter-start-time" class="ui-label">Jam mulai</label>
                            <input id="filter-start-time" type="time" wire:model="startTime" class="ui-input">
                            @error('startTime') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="filter-end-time" class="ui-label">Jam selesai</label>
                            <input id="filter-end-time" type="time" wire:model="endTime" class="ui-input">
                            @error('endTime') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <fieldset class="discovery-facilities">
                        <legend>Fasilitas</legend>
                        <div class="discovery-facility-options">
                            @foreach ($this->availableFacilities() as $facility)
                                <label>
                                    <input type="checkbox" value="{{ $facility }}" wire:model="selectedFacilities">
                                    <span>{{ $facility }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('selectedFacilities') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    </fieldset>

                    <div class="discovery-filter-actions">
                        <button type="submit" wire:loading.attr="disabled" wire:target="applyDiscoveryFilters" class="btn btn-primary">
                            <span wire:loading.remove wire:target="applyDiscoveryFilters">Terapkan filter</span>
                            <span wire:loading wire:target="applyDiscoveryFilters">Memeriksa...</span>
                        </button>
                        @if ($isFiltered)
                            <button type="button" wire:click="resetSearch" class="btn btn-secondary">Bersihkan semua</button>
                        @endif
                    </div>
                </form>
            </details>

            <div class="discovery-results-status mt-6" wire:loading.flex wire:target="applyDiscoveryFilters,search,chooseCategory,choosePopularSearch,sort">
                <span class="discovery-loading-dot" aria-hidden="true"></span>
                Memperbarui pilihan tempat...
            </div>

            <div class="mt-6 grid gap-3 transition-opacity sm:mt-8 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3"
                 wire:loading.class="opacity-50"
                 wire:target="applyDiscoveryFilters,search,chooseCategory,choosePopularSearch,sort"
                 aria-live="polite">
                @forelse ($this->venues as $venue)
                    @php($venueUrl = $venue->business?->slug ? route('venue.public', ['businessSlug' => $venue->business->slug, 'venueSlug' => $venue->slug]) : null)
                    <a
                        wire:key="venue-{{ $venue->id }}"
                        href="{{ $venueUrl ?: '#' }}"
                        class="venue-card venue-card-link"
                        @if (! $venueUrl) aria-disabled="true" @endif
                    >
                        @php($coverMedia = $venue->media->firstWhere('is_cover', true) ?? $venue->media->first())
                        @if ($coverMedia)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk($coverMedia->disk)->url($coverMedia->file_path) }}" alt="{{ $coverMedia->alt_text }}" loading="lazy" decoding="async" class="venue-card-media">
                        @else
                            <div class="venue-card-media venue-card-media-empty" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="16" rx="2"/>
                                    <circle cx="8.5" cy="9" r="1.5"/>
                                    <path d="m21 15-5-5L5 20"/>
                                </svg>
                            </div>
                        @endif
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="inline-flex max-w-full items-center rounded-md bg-[var(--surface-lime)] px-2.5 py-1 text-xs font-bold text-[var(--brand-forest-deep)]">{{ $venue->category?->name ?? 'Kategori belum tersedia' }}</span>
                            @if ($venue->business?->verified_at)
                                <span class="venue-card-verified">Terverifikasi</span>
                            @endif
                        </div>
                        <h3 class="mt-4 break-words text-lg font-extrabold leading-tight tracking-[-0.04em] sm:text-xl">{{ $venue->name }}</h3>
                        <p class="mt-1.5 text-sm font-semibold text-[var(--ink-soft)]">{{ $venue->business?->name ?? 'Nama usaha belum tersedia' }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs font-semibold text-[var(--muted)]">
                            @if ($venue->capacity_persons)
                                <span>Hingga {{ number_format((int) $venue->capacity_persons, 0, ',', '.') }} orang</span>
                            @endif
                            @if (count($venue->facilities_json ?? []) > 0)
                                <span>{{ count($venue->facilities_json) }} fasilitas</span>
                            @endif
                        </div>
                        @php($activePrices = $venue->prices->where('is_active', true)->values())
                        @if ($activePrices->isNotEmpty())
                            @php($displayPrice = $activePrices->flatMap(fn ($price) => [$price->price_amount, $price->weekend_price_amount])->filter(fn ($amount) => $amount !== null)->min())
                            <p class="mt-2 text-sm font-extrabold text-[var(--brand-forest)]">
                                Mulai Rp {{ number_format((int) $displayPrice, 0, ',', '.') }}
                                / {{ match ($activePrices->first()->pricing_unit) { 'hourly' => 'jam', 'daily' => 'hari', default => 'sesi' } }}
                            </p>
                        @endif
                        <p class="mt-3 flex items-start gap-2 text-sm leading-6 text-[var(--muted)]">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0 text-[var(--brand-forest)]"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                            <span>{{ $venue->business?->address ?: 'Lokasi belum dicantumkan' }}</span>
                        </p>
                        @if ($venue->description)
                            <p class="mt-3 border-t border-[var(--border)] pt-3 text-sm leading-6 text-[var(--ink-soft)]">{{ \Illuminate\Support\Str::limit($venue->description, 170) }}</p>
                        @endif
                        <span class="venue-card-open">
                            Lihat detail
                            <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 10h11m-4-4 4 4-4 4"/>
                            </svg>
                        </span>
                    </a>
                @empty
                    <div class="col-span-full rounded-xl bg-[var(--surface-soft)] px-5 py-8 sm:px-8 sm:py-10">
                        <div class="mx-auto max-w-lg text-center">
                            <span class="mx-auto flex size-11 items-center justify-center rounded-lg bg-[var(--brand-lime)]">
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="size-5"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.2 4.2"/></svg>
                            </span>
                            <h3 class="mt-4 text-lg font-extrabold tracking-[-0.03em]">{{ $isFiltered ? 'Belum ada tempat yang cocok.' : 'Belum ada listing yang tersedia.' }}</h3>
                            <p class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ $isFiltered ? 'Coba ubah kata kunci, lokasi, atau kategori. Hasil hanya menampilkan venue yang telah dipublikasikan.' : 'Tempat yang dipublikasikan akan tampil di bagian ini.' }}</p>
                            @if ($isFiltered)
                                <button type="button" wire:click="resetSearch" class="btn btn-primary mt-5">Hapus semua filter</button>
                            @endif
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section id="untuk-pemilik" aria-labelledby="owner-heading" class="scroll-mt-24">
        <div class="site-container pb-12 sm:pb-16 lg:pb-20">
            <div class="grid gap-6 rounded-[1.25rem] bg-[var(--brand-forest)] px-5 py-7 text-white sm:px-8 sm:py-9 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center lg:gap-12 lg:px-10 lg:py-11">
                <div class="max-w-2xl">
                    <p class="eyebrow !text-[var(--brand-lime)]"><span class="eyebrow-dot !bg-[var(--brand-lime)]"></span> Untuk pemilik tempat</p>
                    <h2 id="owner-heading" class="mt-3 text-2xl font-extrabold leading-tight tracking-[-0.05em] text-white sm:text-3xl lg:text-4xl">Punya venue yang ingin dikenal lebih luas?</h2>
                    <p class="mt-3 max-w-xl text-sm leading-7 text-white/80 sm:text-base">Buat akun pemilik usaha untuk menampilkan tempat dan mulai menyiapkan profil venue-mu di Adatempat. Informasi yang jelas membantu calon pelanggan membandingkan pilihan.</p>
                </div>
                <a href="{{ route('register') }}" class="btn btn-primary w-full sm:w-fit">Daftar sebagai pemilik <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></a>
            </div>
        </div>
    </section>
</div>