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

<div class="overflow-hidden">
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

    <section aria-labelledby="hero-heading" class="relative">
        <div class="site-container grid items-center gap-9 py-8 sm:gap-12 sm:py-12 lg:grid-cols-[minmax(0,1fr)_minmax(25rem,.88fr)] lg:gap-16 lg:py-16">
            <div class="relative z-10 min-w-0">
                <p class="eyebrow"><span class="eyebrow-dot"></span> Temukan tempat untuk rencanamu</p>
                <h1 id="hero-heading" class="mt-4 max-w-2xl text-[2.55rem] font-extrabold leading-[1.08] tracking-[-0.065em] sm:mt-5 sm:text-6xl lg:text-[4.35rem]">
                    Ada rencana?
                    <span class="mt-1 block">Cari tempatnya.</span>
                </h1>
                <p class="mt-5 max-w-xl text-[0.975rem] leading-7 text-[var(--ink-soft)] sm:mt-6 sm:text-lg sm:leading-8">
                    Dari acara keluarga sampai ruang meeting, mulai pencarian dengan kebutuhan dan lokasi yang kamu punya.
                </p>

                <div id="jelajahi" class="mt-7 scroll-mt-28 rounded-2xl border border-[var(--border)] bg-white p-3 shadow-[var(--shadow-raised)] sm:mt-9 sm:p-4">
                    <form role="search" aria-label="Cari tempat" wire:submit="search">
                        <div class="grid gap-3 sm:grid-cols-2 sm:gap-4">
                            <div class="min-w-0 px-1 pt-1">
                                <label for="keyword" class="ui-label">Kebutuhan atau nama tempat</label>
                                <div class="flex min-h-12 items-center gap-3 rounded-xl border border-[var(--border)] bg-[var(--canvas)] px-3 focus-within:border-[var(--brand-forest)]">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="size-5 shrink-0 text-[var(--brand-forest)]">
                                        <circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.2 4.2"/>
                                    </svg>
                                    <input id="keyword" name="keyword" type="search" wire:model.live.debounce.400ms="keyword" placeholder="Mis. meeting atau pernikahan" autocomplete="off" class="w-full min-w-0 border-0 bg-transparent p-0 text-sm text-[var(--ink)] outline-none ring-0 placeholder:text-[var(--muted)] focus:ring-0">
                                </div>
                            </div>
                            <div class="min-w-0 px-1 pt-1">
                                <label for="location" class="ui-label">Lokasi</label>
                                <div class="flex min-h-12 items-center gap-3 rounded-xl border border-[var(--border)] bg-[var(--canvas)] px-3 focus-within:border-[var(--brand-forest)]">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5 shrink-0 text-[var(--brand-forest)]">
                                        <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>
                                    </svg>
                                    <input id="location" name="location" type="text" wire:model.live.debounce.400ms="location" placeholder="Kota atau daerah" autocomplete="address-level2" class="w-full min-w-0 border-0 bg-transparent p-0 text-sm text-[var(--ink)] outline-none ring-0 placeholder:text-[var(--muted)] focus:ring-0">
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 border-t border-[var(--border)] pt-3 sm:mt-4 sm:flex sm:items-center sm:justify-between sm:gap-4">
                            <p class="hidden px-1 text-xs leading-5 text-[var(--muted)] sm:block">Cari dengan kata kunci, lokasi, atau kategori.</p>
                            <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full sm:w-auto sm:min-w-36">
                                <span wire:loading.remove wire:target="search">Cari tempat</span>
                                <span wire:loading wire:target="search">Mencari...</span>
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M4 12h15m-6-6 6 6-6 6"/></svg>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <span class="mr-1 text-xs font-semibold text-[var(--muted)]">Contoh:</span>
                    @foreach ($popularSearches as $search)
                        <button type="button" wire:key="popular-search-{{ $loop->index }}" wire:click="choosePopularSearch('{{ $search }}')" aria-pressed="{{ $keyword === $search ? 'true' : 'false' }}" class="inline-flex min-h-9 items-center rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors {{ $keyword === $search ? 'border-[var(--brand-forest)] bg-[var(--brand-forest)] text-white' : 'border-[var(--border)] bg-white text-[var(--ink-soft)] hover:bg-[var(--surface-soft)]' }}">
                            {{ $search }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="relative mx-auto w-full max-w-[36rem] lg:ml-auto">
                <div class="relative overflow-hidden rounded-[1.4rem] border border-[var(--border)] bg-[var(--brand-cream)] p-2 sm:rounded-[1.75rem] sm:p-3">
                    <div class="relative aspect-[5/4.2] overflow-hidden rounded-[1rem] bg-[var(--surface-soft)] sm:aspect-[4/4.3]">
                        <img src="https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=1400&q=88" alt="Suasana ruang acara dengan meja dan kursi yang tertata" fetchpriority="high" decoding="async" class="absolute inset-0 size-full object-cover">
                        <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-t from-[#16271B]/75 via-transparent to-[#16271B]/10"></div>
                        <div class="absolute inset-x-4 bottom-4 text-white sm:inset-x-6 sm:bottom-6">
                            <span class="inline-flex items-center rounded-md bg-[var(--brand-lime)] px-2.5 py-1 text-[0.68rem] font-extrabold uppercase tracking-[0.07em] text-[var(--ink)]">Mulai dari kebutuhanmu</span>
                            <p class="mt-3 max-w-sm text-2xl font-bold leading-tight tracking-[-0.04em] sm:text-3xl">Ruang yang tepat membantu rencana berjalan lebih mudah.</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 px-2 pb-1 pt-3 text-xs text-[var(--ink-soft)] sm:px-3 sm:pt-4">
                        <span>Temukan opsi berdasarkan informasi venue.</span>
                        <a href="#kategori" class="inline-flex min-h-8 items-center gap-2 font-bold text-[var(--brand-forest)] underline-offset-4 hover:underline">
                            Lihat kategori
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m9 18 6-6-6-6"/></svg>
                        </a>
                    </div>
                </div>
                <div aria-hidden="true" class="absolute -bottom-3 -right-2 -z-10 hidden h-24 w-28 rounded-2xl bg-[var(--brand-lime)] sm:block"></div>
            </div>
        </div>
    </section>

    <section aria-label="Cara memulai pencarian" class="border-y border-[var(--border)] bg-white">
        <div class="site-container grid gap-5 py-5 sm:grid-cols-3 sm:gap-6 sm:py-6">
            <div class="flex items-start gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[var(--brand-lime)] text-sm font-extrabold">01</span>
                <div><p class="text-sm font-bold">Tentukan kebutuhan</p><p class="mt-1 text-xs leading-5 text-[var(--muted)]">Mulai dari acara, aktivitas, atau nama tempat.</p></div>
            </div>
            <div class="flex items-start gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[var(--brand-lime)] text-sm font-extrabold">02</span>
                <div><p class="text-sm font-bold">Persempit lokasi</p><p class="mt-1 text-xs leading-5 text-[var(--muted)]">Gunakan kota atau daerah yang kamu inginkan.</p></div>
            </div>
            <div class="flex items-start gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[var(--brand-lime)] text-sm font-extrabold">03</span>
                <div><p class="text-sm font-bold">Tinjau pilihan</p><p class="mt-1 text-xs leading-5 text-[var(--muted)]">Bandingkan informasi yang tersedia pada listing.</p></div>
            </div>
        </div>
    </section>

    <section id="kategori" aria-labelledby="category-heading" class="scroll-mt-24">
        <div class="site-container py-12 sm:py-16 lg:py-20">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <p class="eyebrow"><span class="eyebrow-dot"></span> Mulai dari jenis kegiatan</p>
                    <h2 id="category-heading" class="mt-3 text-3xl font-extrabold leading-tight tracking-[-0.05em] sm:text-4xl lg:text-5xl">Temukan kategori yang sesuai.</h2>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-[var(--ink-soft)] sm:text-base sm:leading-7">Pilih kategori untuk mempersempit daftar tempat. Kategori yang tersedia mengikuti data yang dipublikasikan.</p>
                </div>
                @if ($category !== '')
                    <button type="button" wire:click="resetSearch" class="btn btn-secondary w-full sm:w-auto">Hapus filter kategori</button>
                @endif
            </div>

            <div class="mt-6 grid grid-cols-2 gap-3 sm:mt-8 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4">
                @forelse ($this->categories as $item)
                    <button type="button" wire:key="category-{{ $item->id }}" wire:click="chooseCategory('{{ $item->slug }}')" aria-pressed="{{ $category === $item->slug ? 'true' : 'false' }}" class="category-tile group {{ $category === $item->slug ? 'ring-2 ring-[var(--brand-forest)] ring-offset-2' : '' }}">
                        <img src="{{ $categoryImages[$item->slug] ?? $defaultCategoryImage }}" alt="" loading="lazy" decoding="async">
                        <span class="relative w-full p-4 sm:p-5">
                            <span class="block text-[0.65rem] font-bold uppercase tracking-[0.09em] text-white/80">Kategori</span>
                            <span class="mt-1.5 flex items-end justify-between gap-2 text-left">
                                <span class="text-lg font-extrabold leading-tight tracking-[-0.035em] sm:text-xl">{{ $item->name }}</span>
                                <span class="mb-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg border border-white/60 bg-white/10 transition-colors group-hover:bg-[var(--brand-lime)] group-hover:text-[var(--ink)]">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M4 12h15m-6-6 6 6-6 6"/></svg>
                                </span>
                            </span>
                        </span>
                    </button>
                @empty
                    <div class="col-span-full rounded-xl border border-dashed border-[var(--border-strong)] bg-white p-5 text-sm leading-6 text-[var(--ink-soft)]">
                        Kategori belum tersedia. Kamu tetap bisa mencari berdasarkan nama tempat atau lokasi.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section id="hasil" aria-labelledby="results-heading" class="scroll-mt-24 border-y border-[var(--border)] bg-[var(--surface-soft)]">
        <div class="site-container py-12 sm:py-16 lg:py-20">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <p class="eyebrow"><span class="eyebrow-dot"></span> {{ $isFiltered ? 'Hasil pencarian' : 'Tempat yang dipublikasikan' }}</p>
                    <h2 id="results-heading" class="mt-3 text-3xl font-extrabold leading-tight tracking-[-0.05em] sm:text-4xl lg:text-5xl">
                        {{ $isFiltered ? 'Pilihan sesuai pencarianmu.' : 'Jelajahi tempat yang tersedia.' }}
                    </h2>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-[var(--ink-soft)] sm:text-base sm:leading-7">
                        Informasi di bawah berasal dari listing yang dipublikasikan oleh pemilik venue.
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
                            <span class="inline-flex max-w-full items-center rounded-md bg-[var(--brand-lime)] px-2.5 py-1 text-xs font-bold text-[var(--ink)]">{{ $venue->category?->name ?? 'Kategori belum tersedia' }}</span>
                            <span class="text-[0.68rem] font-semibold uppercase tracking-[0.07em] text-[var(--muted)]">Listing venue</span>
                        </div>
                        <h3 class="mt-4 break-words text-xl font-extrabold leading-tight tracking-[-0.04em] sm:text-2xl">{{ $venue->name }}</h3>
                        <p class="mt-2 text-sm font-semibold text-[var(--ink-soft)]">{{ $venue->business?->name ?? 'Nama usaha belum tersedia' }}</p>
                        <p class="mt-3 flex items-start gap-2 text-sm leading-6 text-[var(--muted)]">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0 text-[var(--brand-forest)]"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                            <span>{{ $venue->business?->address ?: 'Lokasi belum dicantumkan' }}</span>
                        </p>
                        @if ($venue->description)
                            <p class="mt-3 border-t border-[var(--border)] pt-3 text-sm leading-6 text-[var(--ink-soft)]">{{ \Illuminate\Support\Str::limit($venue->description, 170) }}</p>
                        @endif
                    </article>
                @empty
                    <div class="col-span-full rounded-2xl border border-dashed border-[var(--border-strong)] bg-white px-5 py-8 sm:px-8 sm:py-10">
                        <div class="mx-auto max-w-lg text-center">
                            <span class="mx-auto flex size-12 items-center justify-center rounded-xl bg-[var(--brand-lime)]">
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.2 4.2"/></svg>
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
        <div class="site-container py-12 sm:py-16 lg:py-20">
            <div class="relative overflow-hidden rounded-[1.5rem] bg-[var(--brand-forest)] px-5 py-8 text-white sm:px-8 sm:py-10 lg:px-12 lg:py-14">
                <div aria-hidden="true" class="absolute -right-16 -top-20 size-64 rounded-full border-[2.5rem] border-white/10 sm:-right-10 sm:-top-28 sm:size-80"></div>
                <div class="relative grid items-end gap-7 lg:grid-cols-[minmax(0,1fr)_auto] lg:gap-12">
                    <div class="max-w-2xl">
                        <p class="eyebrow !text-[var(--brand-lime)]"><span class="eyebrow-dot !bg-[var(--brand-lime)]"></span> Untuk pemilik tempat</p>
                        <h2 id="owner-heading" class="mt-3 text-3xl font-extrabold leading-tight tracking-[-0.05em] text-white sm:text-4xl lg:text-5xl">Punya tempat yang ingin kamu perkenalkan?</h2>
                        <p class="mt-4 max-w-xl text-sm leading-7 text-white/80 sm:text-base">Buat akun pemilik usaha untuk mulai menyiapkan kehadiran venue-mu di Adatempat. Informasi yang jelas membantu calon pelanggan memahami pilihan mereka.</p>
                    </div>
                    <a href="{{ route('register') }}" class="btn btn-primary w-full sm:w-fit">Daftar sebagai pemilik <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></a>
                </div>
            </div>
        </div>
    </section>
</div>