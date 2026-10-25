<?php

use App\Models\Category;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $keyword = '';

    public string $location = '';

    public string $category = '';

    public bool $hasSearched = false;

    public array $popularSearches = ['Pernikahan', 'Meeting', 'Ulang tahun', 'Outdoor'];

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

        return Venue::query()
            ->published()
            ->with(['business', 'category'])
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
            ->orderByDesc('venues.published_at')
            ->orderByDesc('venues.id')
            ->limit(6)
            ->get();
    }

    public function search(): void
    {
        $this->hasSearched = true;
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
    }

    public function choosePopularSearch(string $search): void
    {
        if (! in_array($search, $this->popularSearches, true)) {
            return;
        }

        $this->keyword = $search;
        $this->hasSearched = true;
    }

    public function resetSearch(): void
    {
        $this->reset(['keyword', 'location', 'category']);
        $this->hasSearched = false;
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
        $isFiltered = $hasSearched || trim($keyword) !== '' || trim($location) !== '' || $category !== '';
    @endphp

    <section aria-labelledby="hero-heading" class="hero-section">
        <div class="site-container hero-grid">
            <div class="hero-copy">
                <p class="eyebrow"><span class="eyebrow-dot"></span> Temukan tempat untuk rencanamu</p>
                <h1 id="hero-heading" class="hero-title">
                    Ada rencana?
                    <span class="block">Cari <span class="hero-title-accent">tempatnya.</span></span>
                </h1>
                <p class="hero-description">
                    Dari acara keluarga sampai ruang meeting, mulai pencarian dengan kebutuhan dan lokasi yang kamu punya.
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
                    <h2 id="category-heading" class="mt-3 text-2xl font-extrabold leading-tight tracking-[-0.05em] sm:text-3xl lg:text-4xl">Tempat untuk berbagai rencana.</h2>
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
                        Informasi yang ditampilkan berasal dari listing yang dipublikasikan oleh pemilik venue.
                    </p>
                </div>
                @if (trim($keyword) !== '' || trim($location) !== '' || $category !== '')
                    <button type="button" wire:click="resetSearch" class="btn btn-secondary w-full sm:w-auto">Bersihkan pencarian</button>
                @endif
            </div>

            <div class="mt-6 grid gap-3 sm:mt-8 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                @forelse ($this->venues as $venue)
                    <article wire:key="venue-{{ $venue->id }}" class="venue-card">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="inline-flex max-w-full items-center rounded-md bg-[var(--surface-lime)] px-2.5 py-1 text-xs font-bold text-[var(--brand-forest-deep)]">{{ $venue->category?->name ?? 'Kategori belum tersedia' }}</span>
                            <span class="text-[0.68rem] font-semibold uppercase tracking-[0.06em] text-[var(--muted)]">Listing venue</span>
                        </div>
                        <h3 class="mt-4 break-words text-lg font-extrabold leading-tight tracking-[-0.04em] sm:text-xl">{{ $venue->name }}</h3>
                        <p class="mt-1.5 text-sm font-semibold text-[var(--ink-soft)]">{{ $venue->business?->name ?? 'Nama usaha belum tersedia' }}</p>
                        <p class="mt-3 flex items-start gap-2 text-sm leading-6 text-[var(--muted)]">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0 text-[var(--brand-forest)]"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                            <span>{{ $venue->business?->address ?: 'Lokasi belum dicantumkan' }}</span>
                        </p>
                        @if ($venue->description)
                            <p class="mt-3 border-t border-[var(--border)] pt-3 text-sm leading-6 text-[var(--ink-soft)]">{{ \Illuminate\Support\Str::limit($venue->description, 170) }}</p>
                        @endif
                    </article>
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
                    <h2 id="owner-heading" class="mt-3 text-2xl font-extrabold leading-tight tracking-[-0.05em] text-white sm:text-3xl lg:text-4xl">Punya tempat untuk diperkenalkan?</h2>
                    <p class="mt-3 max-w-xl text-sm leading-7 text-white/80 sm:text-base">Buat akun pemilik usaha untuk mulai menyiapkan profil tempatmu. Informasi yang jelas membantu calon pelanggan memahami pilihan mereka.</p>
                </div>
                <a href="{{ route('register') }}" class="btn btn-primary w-full sm:w-fit">Daftar sebagai pemilik <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></a>
            </div>
        </div>
    </section>
</div>