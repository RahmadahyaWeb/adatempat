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

<div class="overflow-hidden bg-[var(--bg-canvas)]">
    @php
        $categoryImages = [
            'acara' => 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=900&q=85',
            'meeting' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=900&q=85',
            'foto' => 'https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=900&q=85',
            'outdoor' => 'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=900&q=85',
        ];
        $defaultCategoryImage = 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=900&q=85';
    @endphp

    {{-- HERO --}}
    <section aria-labelledby="hero-heading" class="relative">
        <div class="site-container grid items-center gap-10 pb-12 pt-10 sm:pb-16 sm:pt-14 lg:grid-cols-[minmax(0,1fr)_minmax(24rem,.9fr)] lg:gap-16 lg:py-20">
            <div class="relative z-10">
                <p class="editorial-eyebrow">
                    <span class="size-2 rounded-full bg-[var(--accent-sage)]"></span>
                    Ruang untuk setiap rencana
                </p>

                <h1 id="hero-heading" class="mt-5 max-w-2xl font-editorial text-[3.5rem] leading-[.96] tracking-[-0.04em] sm:text-7xl lg:text-[5.5rem]">
                    Ada rencana.
                    <span class="mt-2 block italic text-[var(--accent-primary)]">Temukan tempatnya.</span>
                </h1>

                <p class="mt-6 max-w-xl text-base leading-7 text-[var(--text-secondary)] sm:text-lg sm:leading-8">
                    Cari tempat untuk pertemuan, perayaan, sesi foto, atau rencana berikutnya. Semua dimulai dari tempat yang tepat.
                </p>

                <div id="jelajahi" class="mt-8 scroll-mt-28 rounded-[1.5rem] border border-[var(--border-strong)] bg-[var(--bg-surface)] p-3 shadow-floating sm:mt-10 sm:p-4">
                    <form role="search" aria-label="Cari venue" wire:submit.prevent="search">
                        <div class="grid gap-1 sm:grid-cols-2 sm:gap-2">
                            <div class="min-w-0 rounded-xl px-3 py-2.5 transition-colors focus-within:bg-[var(--bg-canvas)]">
                                <label for="keyword" class="mb-2 flex items-center gap-2 text-xs font-semibold text-[var(--text-primary)]">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" class="size-4 text-[var(--accent-primary)]">
                                        <circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/>
                                    </svg>
                                    Kamu mau cari apa?
                                </label>
                                <input id="keyword" name="keyword" type="search" wire:model.live.debounce.400ms="keyword" placeholder="Pernikahan, meeting, studio..." autocomplete="off" class="w-full min-w-0 border-0 bg-transparent p-0 text-sm text-[var(--text-primary)] outline-none placeholder:text-[var(--text-muted)] focus:ring-0">
                            </div>

                            <div class="min-w-0 rounded-xl px-3 py-2.5 transition-colors focus-within:bg-[var(--bg-canvas)]">
                                <label for="location" class="mb-2 flex items-center gap-2 text-xs font-semibold text-[var(--text-primary)]">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="size-4 text-[var(--accent-primary)]">
                                        <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>
                                    </svg>
                                    Lokasi
                                </label>
                                <input id="location" name="location" type="text" wire:model.live.debounce.400ms="location" placeholder="Kota atau daerah" autocomplete="address-level2" class="w-full min-w-0 border-0 bg-transparent p-0 text-sm text-[var(--text-primary)] outline-none placeholder:text-[var(--text-muted)] focus:ring-0">
                            </div>
                        </div>

                        <div class="mt-2 flex flex-col gap-2 border-t border-[var(--border-subtle)] pt-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="px-3 text-xs leading-5 text-[var(--text-muted)]">Pilih kategori atau mulai dari kata kunci.</p>
                            <button type="submit" wire:loading.attr="disabled" class="ui-button ui-button-primary w-full sm:w-auto">
                                <span wire:loading.remove wire:target="search">Cari venue</span>
                                <span wire:loading wire:target="search">Mencari...</span>
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="size-4">
                                    <path d="M5 12h14m-7-7 7 7-7 7"/>
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-x-2 gap-y-2">
                    <span class="mr-1 text-xs text-[var(--text-muted)]">Coba cari</span>
                    @foreach ($popularSearches as $search)
                        <button type="button" wire:key="popular-search-{{ $loop->index }}" wire:click="choosePopularSearch('{{ $search }}')" class="inline-flex min-h-8 items-center rounded-full border px-3 py-1.5 text-xs font-medium transition-colors {{ $keyword === $search ? 'border-[var(--accent-primary)] bg-[#F6E7E0] text-[var(--accent-primary)]' : 'border-[var(--border-subtle)] text-[var(--text-secondary)] hover:border-[var(--border-strong)] hover:bg-[var(--bg-subtle)]' }}">
                            {{ $search }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- FOTO EDITORIAL: VISUAL SUASANA, BUKAN DATA VENUE --}}
            <div class="relative mx-auto w-full max-w-[38rem] lg:ml-auto">
                <div aria-hidden="true" class="absolute -right-5 -top-5 size-24 rounded-full border border-[var(--border-strong)] sm:-right-8 sm:-top-8 sm:size-36"></div>
                <div aria-hidden="true" class="absolute -bottom-5 -left-5 size-28 rounded-full bg-[#E9F3EC] sm:-bottom-7 sm:-left-8 sm:size-40"></div>

                <figure class="relative overflow-hidden rounded-[1.75rem] border border-[var(--border-subtle)] bg-[var(--bg-subtle)] p-2 shadow-card sm:rounded-[2rem] sm:p-3">
                    <div class="relative aspect-[4/4.3] overflow-hidden rounded-[1.25rem] bg-[var(--bg-muted)] sm:aspect-[4/4.2]">
                        <img src="https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=1400&q=90" alt="Suasana ruang acara dengan meja yang tertata untuk sebuah perayaan" fetchpriority="high" decoding="async" class="absolute inset-0 size-full object-cover">
                        <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-t from-[#181816]/65 via-transparent to-[#181816]/10"></div>
                        <div class="absolute inset-x-4 bottom-4 text-white sm:inset-x-6 sm:bottom-6">
                            <p class="font-metadata text-[10px] uppercase tracking-[0.16em] text-white/75 sm:text-xs">Sebuah ruang, sebuah cerita</p>
                            <p class="mt-2 max-w-sm font-editorial text-3xl leading-tight tracking-[-0.02em] sm:text-4xl">Mulai dari tempat yang membuat rencanamu terasa nyata.</p>
                        </div>
                    </div>
                    <figcaption class="flex flex-wrap items-center justify-between gap-2 px-3 py-3 text-xs text-[var(--text-muted)] sm:px-4">
                        <span>Temukan ruang yang sesuai dengan ceritamu.</span>
                        <a href="#kategori" class="inline-flex min-h-8 items-center gap-1.5 rounded-full px-2 font-semibold text-[var(--accent-primary)] transition-colors hover:bg-[var(--bg-surface)]">
                            Jelajahi kategori
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="size-3.5">
                                <path d="m9 18 6-6-6-6"/>
                            </svg>
                        </a>
                    </figcaption>
                </figure>
            </div>
        </div>
    </section>

    {{-- KATEGORI --}}
    <section id="kategori" aria-labelledby="category-heading" class="scroll-mt-24 border-y border-[var(--border-subtle)] bg-[var(--bg-subtle)]">
        <div class="site-container py-14 sm:py-18 lg:py-20">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <p class="editorial-eyebrow text-[var(--accent-primary)]">Mulai dari kebutuhanmu</p>
                    <h2 id="category-heading" class="mt-3 font-editorial text-4xl leading-tight tracking-[-0.035em] sm:text-5xl">
                        Tempat untuk <span class="italic text-[var(--accent-primary)]">setiap agenda.</span>
                    </h2>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-[var(--text-secondary)] sm:text-base sm:leading-7">
                        Pilih jenis tempat yang sesuai dengan rencanamu. Pilihan bisa berubah kapan saja.
                    </p>
                </div>
                @if ($category !== '')
                    <button type="button" wire:click="resetSearch" class="ui-button ui-button-secondary min-h-10 w-fit">
                        Hapus filter
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" class="size-4"><path d="m6 6 12 12M18 6 6 18"/></svg>
                    </button>
                @endif
            </div>

            <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4">
                @forelse ($this->categories as $item)
                    <button type="button" wire:key="category-{{ $item->id }}" wire:click="chooseCategory('{{ $item->slug }}')" aria-pressed="{{ $category === $item->slug ? 'true' : 'false' }}" class="group relative isolate flex min-h-52 flex-col overflow-hidden rounded-2xl border text-left transition duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent-primary)] sm:min-h-64 {{ $category === $item->slug ? 'border-[var(--accent-primary)] ring-2 ring-[var(--accent-primary)]/25' : 'border-[var(--border-subtle)] hover:-translate-y-0.5 hover:border-[var(--border-strong)] hover:shadow-floating' }}">
                        <img src="{{ $categoryImages[$item->slug] ?? $defaultCategoryImage }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-[1.04]">
                        <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-t from-[#181816]/85 via-[#181816]/15 to-transparent"></div>
                        <div class="relative mt-auto p-4 text-white sm:p-5">
                            <span class="font-metadata text-[10px] uppercase tracking-[0.15em] text-white/70">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }} / Kategori</span>
                            <span class="mt-2 flex items-end justify-between gap-2">
                                <span class="font-editorial text-2xl leading-tight sm:text-3xl">{{ $item->name }}</span>
                                <span class="mb-0.5 flex size-8 shrink-0 items-center justify-center rounded-full border border-white/50 bg-white/10 transition-colors group-hover:bg-white group-hover:text-[var(--text-primary)]">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M5 12h14m-7-7 7 7-7 7"/></svg>
                                </span>
                            </span>
                        </div>
                    </button>
                @empty
                    <div class="col-span-full rounded-2xl border border-dashed border-[var(--border-strong)] bg-[var(--bg-surface)] px-5 py-8 text-sm leading-6 text-[var(--text-secondary)]">
                        Kategori belum tersedia. Kamu tetap bisa mencari berdasarkan nama tempat atau lokasi.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- KATALOG --}}
    <section id="hasil" aria-labelledby="results-heading" class="scroll-mt-24">
        <div class="site-container py-14 sm:py-18 lg:py-20">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <p class="editorial-eyebrow text-[var(--accent-primary)]">{{ $hasSearched || $keyword !== '' || $location !== '' || $category !== '' ? 'Hasil pencarian' : 'Jelajahi Adatempat' }}</p>
                    <h2 id="results-heading" class="mt-3 font-editorial text-4xl leading-tight tracking-[-0.035em] sm:text-5xl">
                        {{ $hasSearched || $keyword !== '' || $location !== '' || $category !== '' ? 'Mendekati rencanamu.' : 'Tempat yang bisa kamu jelajahi.' }}
                    </h2>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-[var(--text-secondary)] sm:text-base sm:leading-7">
                        Informasi yang ditampilkan berasal dari venue yang sudah dipublikasikan.
                    </p>
                </div>
                @if ($keyword !== '' || $location !== '' || $category !== '')
                    <button type="button" wire:click="resetSearch" class="ui-button ui-button-secondary min-h-10 w-fit">
                        Bersihkan pencarian
                    </button>
                @endif
            </div>

            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($this->venues as $venue)
                    <article wire:key="venue-{{ $venue->id }}" class="rounded-2xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] p-5 transition-colors hover:border-[var(--border-strong)] sm:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <span class="inline-flex items-center rounded-full bg-[var(--bg-subtle)] px-3 py-1.5 text-xs font-medium text-[var(--text-secondary)]">{{ $venue->category?->name ?? 'Venue' }}</span>
                            <span class="font-metadata text-[10px] uppercase tracking-[0.12em] text-[var(--text-muted)]">Adatempat</span>
                        </div>
                        <h3 class="mt-5 font-editorial text-2xl leading-tight tracking-[-0.02em] sm:text-3xl">{{ $venue->name }}</h3>
                        <p class="mt-2 text-sm font-medium text-[var(--text-secondary)]">{{ $venue->business?->name ?? 'Pemilik usaha' }}</p>
                        @if ($venue->description)
                            <p class="mt-3 line-clamp-3 text-sm leading-6 text-[var(--text-secondary)]">{{ $venue->description }}</p>
                        @endif
                        @if ($venue->business?->address)
                            <div class="mt-5 flex items-start gap-2 border-t border-[var(--border-subtle)] pt-4 text-xs leading-5 text-[var(--text-secondary)]">
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0 text-[var(--accent-primary)]"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                <span>{{ $venue->business->address }}</span>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="col-span-full rounded-[1.5rem] border border-dashed border-[var(--border-strong)] bg-[var(--bg-surface)] px-5 py-10 text-center sm:px-8 sm:py-14">
                        <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-[var(--bg-subtle)] text-[var(--accent-primary)]">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                        </span>
                        @if ($keyword !== '' || $location !== '' || $category !== '')
                            <h3 class="mt-4 font-editorial text-2xl sm:text-3xl">Belum ketemu tempat yang cocok.</h3>
                            <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-[var(--text-secondary)]">Coba ganti kata kunci atau lokasi. Kamu juga bisa menghapus filter untuk melihat semua venue yang sudah dipublikasikan.</p>
                            <button type="button" wire:click="resetSearch" class="ui-button ui-button-primary mt-5">Lihat semua venue</button>
                        @else
                            <h3 class="mt-4 font-editorial text-2xl sm:text-3xl">Katalog sedang bertumbuh.</h3>
                            <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-[var(--text-secondary)]">Venue yang sudah dipublikasikan akan muncul di sini. Punya tempat sendiri? Kenalkan usahamu melalui Adatempat.</p>
                            <a href="{{ route('register') }}" class="ui-button ui-button-secondary mt-5">Daftarkan usaha</a>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- CARA KERJA --}}
    <section id="cara-kerja" aria-labelledby="steps-heading" class="border-y border-[var(--border-subtle)] bg-[var(--bg-subtle)]">
        <div class="site-container grid gap-10 py-14 sm:py-18 lg:grid-cols-[.8fr_1.2fr] lg:gap-16 lg:py-20">
            <div>
                <p class="editorial-eyebrow text-[var(--accent-primary)]">Alur yang sederhana</p>
                <h2 id="steps-heading" class="mt-3 max-w-md font-editorial text-4xl leading-tight tracking-[-0.035em] sm:text-5xl">Cari tempat tanpa ribet.</h2>
                <p class="mt-4 max-w-md text-sm leading-6 text-[var(--text-secondary)] sm:text-base sm:leading-7">Mulai dari kebutuhanmu, pahami pilihan yang tersedia, lalu lanjutkan rencana melalui satu alur yang jelas.</p>
                <a href="#jelajahi" class="ui-button ui-button-primary mt-6">Mulai menjelajah
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M5 12h14m-7-7 7 7-7 7"/></svg>
                </a>
            </div>
            <div class="divide-y divide-[var(--border-strong)]">
                <article class="grid gap-3 py-5 first:pt-0 sm:grid-cols-[3.5rem_1fr] sm:gap-5 sm:py-6">
                    <p class="font-metadata text-sm tracking-[0.12em] text-[var(--accent-primary)]">01</p>
                    <div><h3 class="font-editorial text-2xl sm:text-3xl">Tentukan kebutuhanmu</h3><p class="mt-2 max-w-xl text-sm leading-6 text-[var(--text-secondary)] sm:text-base">Mulai dengan jenis acara, kata kunci, atau area tempat yang kamu cari.</p></div>
                </article>
                <article class="grid gap-3 py-5 sm:grid-cols-[3.5rem_1fr] sm:gap-5 sm:py-6">
                    <p class="font-metadata text-sm tracking-[0.12em] text-[var(--accent-primary)]">02</p>
                    <div><h3 class="font-editorial text-2xl sm:text-3xl">Kenali pilihan yang tersedia</h3><p class="mt-2 max-w-xl text-sm leading-6 text-[var(--text-secondary)] sm:text-base">Periksa informasi tempat dan bandingkan detailnya sebelum menentukan pilihan.</p></div>
                </article>
                <article class="grid gap-3 py-5 last:pb-0 sm:grid-cols-[3.5rem_1fr] sm:gap-5 sm:py-6">
                    <p class="font-metadata text-sm tracking-[0.12em] text-[var(--accent-primary)]">03</p>
                    <div><h3 class="font-editorial text-2xl sm:text-3xl">Lanjutkan rencanamu</h3><p class="mt-2 max-w-xl text-sm leading-6 text-[var(--text-secondary)] sm:text-base">Setelah menemukan tempat yang cocok, lanjutkan ke proses berikutnya melalui Adatempat.</p></div>
                </article>
            </div>
        </div>
    </section>

    {{-- UNTUK OWNER --}}
    <section id="untuk-owner" aria-labelledby="owner-heading" class="scroll-mt-24">
        <div class="site-container py-14 sm:py-18 lg:py-20">
            <div class="relative overflow-hidden rounded-[1.75rem] border border-[#C8D9CA] bg-[#E9F3EC] px-5 py-8 sm:px-8 sm:py-10 lg:px-12 lg:py-14">
                <div aria-hidden="true" class="absolute -right-20 -top-24 size-64 rounded-full border border-[#C8D9CA] sm:-right-10 sm:-top-32 sm:size-80"></div>
                <div aria-hidden="true" class="absolute -right-8 -top-12 size-44 rounded-full border-[24px] border-[#D6E6D8] sm:size-56"></div>
                <div class="relative z-10 flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="editorial-eyebrow text-[#49634E]">Untuk pemilik usaha</p>
                        <h2 id="owner-heading" class="mt-3 font-editorial text-4xl leading-tight tracking-[-0.035em] text-[#203E2A] sm:text-5xl lg:text-6xl">Tempatmu punya cerita. Biar lebih banyak orang menemukannya.</h2>
                        <p class="mt-4 max-w-xl text-sm leading-6 text-[#49634E] sm:text-base sm:leading-7">Kenalkan usahamu, tampilkan venue, dan kelola pemesanan melalui Adatempat.</p>
                    </div>
                    <div class="relative z-10 flex shrink-0 flex-col items-start gap-2">
                        <a href="{{ route('register') }}" class="ui-button ui-button-primary min-h-12 px-6">Daftarkan usaha
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M5 12h14m-7-7 7 7-7 7"/></svg>
                        </a>
                        <p class="pl-2 text-xs leading-5 text-[#49634E]">Pilih jenis akun pemilik usaha saat mendaftar.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>