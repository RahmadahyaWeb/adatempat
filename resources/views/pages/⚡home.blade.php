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

    public array $popularSearches = ['Venue pernikahan', 'Meeting', 'Ulang tahun', 'Outdoor'];

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

<div class="overflow-hidden bg-[var(--bg-canvas)] text-[var(--text-primary)]">
    {{-- HERO --}}
    <section aria-labelledby="hero-heading" class="relative border-b border-[var(--border-subtle)]">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 pb-14 pt-12 sm:px-8 sm:pb-18 sm:pt-16 lg:grid-cols-[minmax(0,1.04fr)_minmax(0,.96fr)] lg:gap-14 lg:px-10 lg:py-20">
            <div class="relative z-10">
                <p class="mb-6 inline-flex items-center gap-2.5 font-metadata text-[11px] font-medium uppercase tracking-[0.16em] text-[var(--text-secondary)] sm:text-xs">
                    <span class="inline-block size-2 rounded-full bg-[var(--accent-sage)]"></span>
                    Adatempat <span class="text-[var(--text-muted)]">/</span> Temukan tempatnya
                </p>

                <h1 id="hero-heading" class="max-w-2xl font-editorial text-[2.85rem] leading-[1.02] tracking-[-0.045em] text-[var(--text-primary)] sm:text-6xl lg:text-[4.5rem] xl:text-[5rem]">
                    Rencana besar,
                    <span class="mt-1 block italic text-[var(--accent-primary)]">butuh tempat yang pas.</span>
                </h1>

                <p class="mt-6 max-w-xl text-base leading-7 text-[var(--text-secondary)] sm:text-lg sm:leading-8">
                    Dari perayaan yang intim sampai pertemuan penting. Temukan tempat yang sesuai dengan kebutuhan, suasana, dan rencanamu.
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-[var(--text-muted)] sm:text-sm">
                    <span class="inline-flex items-center gap-2">
                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="size-4 text-[var(--accent-sage)]">
                            <path d="m4 10.5 3.7 3.7L16 5.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Jelajahi sesuai kebutuhan
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="size-4 text-[var(--accent-sage)]">
                            <path d="M10 17s6-4.1 6-9a6 6 0 1 0-12 0c0 4.9 6 9 6 9Z" stroke="currentColor" stroke-width="1.5"/>
                            <circle cx="10" cy="8" r="2" stroke="currentColor" stroke-width="1.5"/>
                        </svg>
                        Cari berdasarkan area
                    </span>
                </div>

                {{-- PENCARIAN --}}
                <div id="jelajahi" class="mt-9 scroll-mt-24 rounded-[1.5rem] border border-[var(--border-strong)] bg-[var(--bg-surface)] p-3 shadow-card sm:mt-10 sm:p-4">
                    <form role="search" aria-label="Cari venue" wire:submit.prevent="search">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="min-w-0 rounded-xl px-3 py-2.5 transition-colors focus-within:bg-[var(--bg-canvas)]">
                                <label for="keyword" class="mb-2 flex items-center gap-2 text-xs font-semibold text-[var(--text-primary)]">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" class="size-4 text-[var(--accent-primary)]">
                                        <circle cx="11" cy="11" r="7"/>
                                        <path d="m16 16 4 4"/>
                                    </svg>
                                    Mau cari tempat seperti apa?
                                </label>
                                <input
                                    id="keyword"
                                    name="keyword"
                                    type="search"
                                    wire:model.live.debounce.400ms="keyword"
                                    placeholder="Pernikahan, meeting, studio…"
                                    autocomplete="off"
                                    class="w-full min-w-0 border-0 bg-transparent p-0 text-sm text-[var(--text-primary)] outline-none placeholder:text-[var(--text-muted)] focus:ring-0"
                                >
                            </div>

                            <div class="min-w-0 rounded-xl px-3 py-2.5 transition-colors focus-within:bg-[var(--bg-canvas)]">
                                <label for="location" class="mb-2 flex items-center gap-2 text-xs font-semibold text-[var(--text-primary)]">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="size-4 text-[var(--accent-primary)]">
                                        <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/>
                                        <circle cx="12" cy="10" r="2.5"/>
                                    </svg>
                                    Di area mana?
                                </label>
                                <input
                                    id="location"
                                    name="location"
                                    type="text"
                                    wire:model.live.debounce.400ms="location"
                                    placeholder="Kota atau alamat"
                                    autocomplete="address-level2"
                                    class="w-full min-w-0 border-0 bg-transparent p-0 text-sm text-[var(--text-primary)] outline-none placeholder:text-[var(--text-muted)] focus:ring-0"
                                >
                            </div>
                        </div>

                        <div class="mt-2 flex flex-col gap-2 border-t border-[var(--border-subtle)] pt-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="px-3 text-xs leading-5 text-[var(--text-muted)]">
                                Mulai dari kata kunci atau lokasi. Kamu juga bisa pilih kategori di bawah.
                            </p>
                            <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-[var(--accent-primary)] px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-[var(--accent-primary-hover)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent-primary)]">
                                Cari venue
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4">
                                    <path d="M5 12h14m-7-7 7 7-7 7"/>
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-x-2 gap-y-2">
                    <span class="mr-1 text-xs font-medium text-[var(--text-muted)]">Ide pencarian</span>
                    @foreach ($popularSearches as $search)
                        <button
                            type="button"
                            wire:key="popular-search-{{ $loop->index }}"
                            wire:click="choosePopularSearch('{{ $search }}')"
                            class="inline-flex min-h-8 items-center rounded-full border px-3 py-1.5 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent-primary)] {{ $keyword === $search ? 'border-[var(--accent-primary)] bg-[#F6E7E0] text-[var(--accent-primary)]' : 'border-[var(--border-subtle)] bg-transparent text-[var(--text-secondary)] hover:border-[var(--border-strong)] hover:bg-[var(--bg-subtle)]' }}"
                        >
                            {{ $search === 'Venue pernikahan' ? 'Pernikahan' : $search }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- VISUAL EDITORIAL: ILUSTRASI ARSITEKTUR, BUKAN DATA VENUE PALSU --}}
            <div class="relative mx-auto w-full max-w-[34rem] lg:ml-auto">
                <div class="absolute -right-5 top-8 size-24 rounded-full border border-[var(--border-strong)] sm:-right-8 sm:size-36"></div>
                <div class="absolute -bottom-5 -left-5 size-28 rounded-full bg-[#F0D8CC] sm:-bottom-7 sm:-left-8 sm:size-40"></div>

                <div class="relative overflow-hidden rounded-[2rem] border border-[var(--border-subtle)] bg-[#E9F3EC] p-5 sm:p-7 lg:p-8">
                    <div class="relative z-10 flex items-start justify-between gap-4">
                        <div>
                            <p class="font-metadata text-[10px] uppercase tracking-[0.18em] text-[#49634E] sm:text-xs">Sebuah tempat, banyak cerita</p>
                            <h2 class="mt-3 max-w-[17rem] font-editorial text-3xl leading-tight tracking-[-0.03em] text-[#203E2A] sm:text-4xl">
                                Momenmu layak punya ruang.
                            </h2>
                        </div>
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-full border border-[#B9CCBC] bg-[#F8F6F0] text-[#2E6F40]">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                                <path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-7h6v7"/>
                            </svg>
                        </span>
                    </div>

                    <div class="relative mt-4 min-h-[18rem] sm:min-h-[21rem]">
                        <svg aria-hidden="true" viewBox="0 0 520 360" fill="none" class="absolute inset-x-0 bottom-0 h-auto w-full">
                            <circle cx="393" cy="82" r="48" fill="#F0C6B4"/>
                            <circle cx="393" cy="82" r="63" stroke="#D9A795" stroke-width="1.5"/>
                            <path d="M36 321H489" stroke="#9CB7A0" stroke-width="2" stroke-linecap="round"/>
                            <path d="M74 321V172L161 111L248 172V321H74Z" fill="#F9F6EF" stroke="#6C9272" stroke-width="2.5" stroke-linejoin="round"/>
                            <path d="M103 321V192H219V321" fill="#E9D8C9"/>
                            <path d="M118 209H151V251H118V209ZM171 209H204V251H171V209Z" fill="#B5CDB9"/>
                            <path d="M137 321V274H185V321" fill="#D46242"/>
                            <path d="M238 321V140L326 81L414 140V321H238Z" fill="#D46242" stroke="#BC5032" stroke-width="2.5" stroke-linejoin="round"/>
                            <path d="M263 164H389V321H263V164Z" fill="#F5E8DE"/>
                            <path d="M279 183H309V221H279V183ZM343 183H373V221H343V183ZM279 240H309V278H279V240ZM343 240H373V278H343V240Z" fill="#B7CCB7"/>
                            <path d="M316 265H338V321H316V265Z" fill="#2E6F40"/>
                            <path d="M54 321V245C54 222 37 204 15 204C-7 204-24 222-24 245V321" fill="#9DB89D"/>
                            <path d="M466 321V242C466 221 483 204 504 204C525 204 542 221 542 242V321" fill="#9DB89D"/>
                            <path d="M42 274H64M42 289H64M456 274H478M456 289H478" stroke="#6C9272" stroke-width="2" stroke-linecap="round"/>
                            <path d="M29 321V248M478 321V248" stroke="#6C9272" stroke-width="2"/>
                        </svg>

                        <div class="absolute left-0 top-8 rounded-2xl border border-[#D7E4D8] bg-[#F9F6EF]/95 p-3 shadow-card sm:left-1 sm:p-4">
                            <p class="font-metadata text-[10px] uppercase tracking-[0.12em] text-[var(--text-muted)]">01 / Mulai</p>
                            <p class="mt-1 text-sm font-semibold text-[#203E2A]">Kenali kebutuhanmu</p>
                        </div>

                        <div class="absolute bottom-3 right-0 w-44 rounded-2xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] p-3 shadow-card sm:bottom-4 sm:w-52 sm:p-4">
                            <p class="font-metadata text-[10px] uppercase tracking-[0.12em] text-[var(--text-muted)]">Temukan</p>
                            <div class="mt-2 flex items-center gap-2.5">
                                <span class="flex size-8 items-center justify-center rounded-full bg-[#F6E7E0] text-[var(--accent-primary)]">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="size-4">
                                        <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/>
                                        <circle cx="12" cy="10" r="2.5"/>
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-xs font-semibold text-[var(--text-primary)]">Lokasi yang pas</p>
                                    <p class="mt-0.5 text-[11px] text-[var(--text-muted)]">Sesuai rencanamu</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="relative z-10 flex flex-wrap items-center justify-between gap-3 border-t border-[#C8D9CA] pt-4">
                        <span class="font-metadata text-[10px] uppercase tracking-[0.14em] text-[#49634E]">Cari · Bandingkan · Rencanakan</span>
                        <a href="#kategori" class="inline-flex min-h-9 items-center gap-1.5 rounded-full px-3 text-xs font-semibold text-[#203E2A] transition-colors hover:bg-white/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2E6F40]">
                            Lihat kategori
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-3.5">
                                <path d="m9 18 6-6-6-6"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- KATEGORI --}}
    <section id="kategori" aria-labelledby="category-heading" class="scroll-mt-24 bg-[var(--bg-subtle)]">
        <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-18 lg:px-10 lg:py-20">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <p class="font-metadata text-[11px] uppercase tracking-[0.16em] text-[var(--accent-primary)] sm:text-xs">Mulai dari kebutuhanmu</p>
                    <h2 id="category-heading" class="mt-3 font-editorial text-3xl leading-tight tracking-[-0.035em] sm:text-4xl lg:text-5xl">
                        Setiap rencana punya <span class="italic text-[var(--accent-primary)]">jenis ruangnya.</span>
                    </h2>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-[var(--text-secondary)] sm:text-base sm:leading-7">
                        Pilih kategori untuk melihat tempat yang sesuai. Kamu bisa mengganti pilihan kapan saja.
                    </p>
                </div>

                @if ($category !== '')
                    <button type="button" wire:click="resetSearch" class="inline-flex min-h-10 w-fit items-center gap-2 rounded-full border border-[var(--border-strong)] bg-[var(--bg-surface)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] transition-colors hover:text-[var(--accent-primary)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent-primary)]">
                        Hapus filter
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" class="size-3.5">
                            <path d="m6 6 12 12M18 6 6 18"/>
                        </svg>
                    </button>
                @endif
            </div>

            <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4">
                @forelse ($this->categories as $item)
                    <button
                        type="button"
                        wire:key="category-{{ $item->id }}"
                        wire:click="chooseCategory('{{ $item->slug }}')"
                        aria-pressed="{{ $category === $item->slug ? 'true' : 'false' }}"
                        class="group relative flex min-h-40 flex-col items-start justify-between overflow-hidden rounded-2xl border p-4 text-left transition duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent-primary)] sm:min-h-48 sm:p-5 {{ $category === $item->slug ? 'border-[var(--accent-primary)] bg-[#F6E7E0]' : 'border-[var(--border-subtle)] bg-[var(--bg-surface)] hover:-translate-y-0.5 hover:border-[var(--border-strong)] hover:shadow-card' }}"
                    >
                        <span class="flex size-11 items-center justify-center rounded-xl transition-colors {{ $category === $item->slug ? 'bg-[var(--accent-primary)] text-white' : 'bg-[var(--bg-subtle)] text-[var(--accent-primary)] group-hover:bg-[#F6E7E0]' }}">
                            @if ($item->slug === 'acara')
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                                    <rect x="3" y="5" width="18" height="16" rx="2"/>
                                    <path d="M16 3v4M8 3v4M3 10h18"/>
                                </svg>
                            @elseif ($item->slug === 'meeting')
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                                    <rect x="3" y="7" width="18" height="14" rx="2"/>
                                    <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18"/>
                                </svg>
                            @elseif ($item->slug === 'foto')
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                                    <path d="M4 7h3l2-3h6l2 3h3a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z"/>
                                    <circle cx="12" cy="13" r="3.5"/>
                                </svg>
                            @elseif ($item->slug === 'outdoor')
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                                    <path d="m12 3 6 9h-4l4 6H6l4-6H6l6-9Z"/>
                                    <path d="M12 18v3"/>
                                </svg>
                            @else
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                                    <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/>
                                    <circle cx="12" cy="10" r="2.5"/>
                                </svg>
                            @endif
                        </span>

                        <span class="mt-7 flex w-full items-end justify-between gap-3">
                            <span>
                                <span class="block font-editorial text-2xl leading-tight tracking-[-0.025em] text-[var(--text-primary)] sm:text-[1.7rem]">{{ $item->name }}</span>
                                <span class="mt-1.5 block text-xs leading-5 text-[var(--text-muted)]">{{ $category === $item->slug ? 'Kategori dipilih' : 'Jelajahi kategori' }}</span>
                            </span>
                            <span class="mb-1 flex size-8 shrink-0 items-center justify-center rounded-full border border-[var(--border-subtle)] text-[var(--text-secondary)] transition-all group-hover:translate-x-0.5 group-hover:border-[var(--accent-primary)] group-hover:text-[var(--accent-primary)] {{ $category === $item->slug ? 'border-[var(--accent-primary)] bg-[var(--accent-primary)] text-white' : '' }}">
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="size-4">
                                    <path d="M5 12h14m-7-7 7 7-7 7"/>
                                </svg>
                            </span>
                        </span>
                    </button>
                @empty
                    <p class="col-span-full rounded-2xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] p-6 text-sm leading-6 text-[var(--text-secondary)]">
                        Kategori belum tersedia. Kamu masih bisa mencari berdasarkan nama tempat atau area.
                    </p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- HASIL PENCARIAN / KATALOG PUBLIK --}}
    <section id="hasil" aria-labelledby="results-heading" class="scroll-mt-24">
        <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-18 lg:px-10 lg:py-20">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <p class="font-metadata text-[11px] uppercase tracking-[0.16em] text-[var(--accent-primary)] sm:text-xs">
                        {{ $hasSearched || $keyword !== '' || $location !== '' || $category !== '' ? 'Hasil pencarian' : 'Jelajahi Adatempat' }}
                    </p>
                    <h2 id="results-heading" class="mt-3 font-editorial text-3xl leading-tight tracking-[-0.035em] sm:text-4xl lg:text-5xl">
                        {{ $hasSearched || $keyword !== '' || $location !== '' || $category !== '' ? 'Tempat yang mendekati rencanamu.' : 'Tempat yang bisa kamu jelajahi.' }}
                    </h2>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-[var(--text-secondary)] sm:text-base sm:leading-7">
                        Informasi yang tampil di sini berasal dari venue yang sudah dipublikasikan.
                    </p>
                </div>

                @if ($keyword !== '' || $location !== '' || $category !== '')
                    <button type="button" wire:click="resetSearch" class="inline-flex min-h-10 w-fit items-center gap-2 rounded-full px-3 py-2 text-sm font-semibold text-[var(--accent-primary)] transition-colors hover:bg-[var(--bg-subtle)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent-primary)]">
                        Bersihkan pencarian
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" class="size-4">
                            <path d="m6 6 12 12M18 6 6 18"/>
                        </svg>
                    </button>
                @endif
            </div>

            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($this->venues as $venue)
                    <article wire:key="venue-{{ $venue->id }}" class="group overflow-hidden rounded-2xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] transition duration-200 hover:-translate-y-0.5 hover:border-[var(--border-strong)] hover:shadow-card">
                        <div class="relative flex min-h-36 items-end overflow-hidden bg-[var(--bg-subtle)] p-5 sm:min-h-40">
                            <div aria-hidden="true" class="absolute -right-8 -top-10 size-36 rounded-full border-[18px] border-[#D7E4D8] sm:size-44"></div>
                            <div aria-hidden="true" class="absolute bottom-0 right-20 h-20 w-12 rounded-t-full bg-[#E9D1C5]"></div>
                            <div aria-hidden="true" class="absolute bottom-0 right-12 h-28 w-16 rounded-t-full border border-[#A7BDA9]"></div>
                            <div class="relative z-10">
                                <p class="font-metadata text-[10px] uppercase tracking-[0.16em] text-[var(--text-muted)]">Adatempat <span class="px-1">/</span> Venue</p>
                                <p class="mt-2 font-editorial text-2xl leading-tight text-[var(--text-primary)]">{{ $venue->category?->name ?? 'Tempat' }}</p>
                            </div>
                        </div>

                        <div class="p-5 sm:p-6">
                            <div class="flex items-center gap-2 text-xs text-[var(--text-muted)]">
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="size-4 text-[var(--accent-sage)]">
                                    <path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-7h6v7"/>
                                </svg>
                                <span class="truncate">{{ $venue->business?->name ?? 'Usaha' }}</span>
                            </div>

                            <h3 class="mt-3 font-editorial text-2xl leading-tight tracking-[-0.02em] text-[var(--text-primary)]">{{ $venue->name }}</h3>

                            @if ($venue->description)
                                <p class="mt-2 line-clamp-2 text-sm leading-6 text-[var(--text-secondary)]">{{ $venue->description }}</p>
                            @else
                                <p class="mt-2 text-sm leading-6 text-[var(--text-muted)]">Detail tempat akan tersedia dari pemilik usaha.</p>
                            @endif

                            @if ($venue->business?->address)
                                <div class="mt-5 flex items-start gap-2 border-t border-[var(--border-subtle)] pt-4 text-xs leading-5 text-[var(--text-secondary)]">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0 text-[var(--accent-primary)]">
                                        <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/>
                                        <circle cx="12" cy="10" r="2.5"/>
                                    </svg>
                                    <span>{{ $venue->business->address }}</span>
                                </div>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="col-span-full rounded-[1.5rem] border border-dashed border-[var(--border-strong)] bg-[var(--bg-surface)] px-5 py-10 text-center sm:px-8 sm:py-14">
                        <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-[var(--bg-subtle)] text-[var(--accent-primary)]">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                                <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/>
                                <circle cx="12" cy="10" r="2.5"/>
                            </svg>
                        </span>

                        @if ($keyword !== '' || $location !== '' || $category !== '')
                            <h3 class="mt-4 font-editorial text-2xl tracking-[-0.02em]">Belum ketemu tempat yang cocok.</h3>
                            <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-[var(--text-secondary)]">
                                Coba ganti kata kunci atau area pencarian. Kamu juga bisa menghapus filter untuk melihat semua venue yang tersedia.
                            </p>
                            <button type="button" wire:click="resetSearch" class="mt-5 inline-flex min-h-10 items-center justify-center rounded-full bg-[var(--accent-primary)] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[var(--accent-primary-hover)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent-primary)]">
                                Lihat semua venue
                            </button>
                        @else
                            <h3 class="mt-4 font-editorial text-2xl tracking-[-0.02em]">Katalognya sedang bertumbuh.</h3>
                            <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-[var(--text-secondary)]">
                                Venue yang sudah dipublikasikan akan muncul di sini. Sementara itu, kamu bisa menjelajahi kategori atau mendaftarkan usaha jika punya tempat untuk dibagikan.
                            </p>
                            <a href="{{ route('register') }}" class="mt-5 inline-flex min-h-10 items-center justify-center rounded-full border border-[var(--border-strong)] px-4 py-2 text-sm font-semibold text-[var(--text-primary)] transition-colors hover:bg-[var(--bg-subtle)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent-primary)]">
                                Daftarkan usaha
                            </a>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ALUR PENGGUNA --}}
    <section aria-labelledby="steps-heading" class="border-y border-[var(--border-subtle)] bg-[var(--bg-subtle)]">
        <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-18 lg:px-10 lg:py-20">
            <div class="grid gap-8 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16">
                <div>
                    <p class="font-metadata text-[11px] uppercase tracking-[0.16em] text-[var(--accent-primary)] sm:text-xs">Sesederhana itu</p>
                    <h2 id="steps-heading" class="mt-3 max-w-md font-editorial text-3xl leading-tight tracking-[-0.035em] sm:text-4xl lg:text-5xl">
                        Dari punya ide, sampai menemukan tempat.
                    </h2>
                    <p class="mt-4 max-w-md text-sm leading-6 text-[var(--text-secondary)] sm:text-base sm:leading-7">
                        Rencana terasa lebih mudah ketika informasi tempat ada dalam satu alur yang jelas.
                    </p>
                    <a href="#jelajahi" class="mt-6 inline-flex min-h-11 items-center gap-2 rounded-full bg-[var(--accent-primary)] px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-[var(--accent-primary-hover)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent-primary)]">
                        Mulai menjelajah
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4">
                            <path d="M5 12h14m-7-7 7 7-7 7"/>
                        </svg>
                    </a>
                </div>

                <div class="divide-y divide-[var(--border-strong)]">
                    <article class="grid gap-3 py-5 first:pt-0 sm:grid-cols-[4rem_1fr] sm:gap-5 sm:py-6">
                        <p class="font-metadata text-sm tracking-[0.12em] text-[var(--accent-primary)]">01</p>
                        <div>
                            <h3 class="font-editorial text-2xl tracking-[-0.02em] sm:text-3xl">Tentukan kebutuhanmu</h3>
                            <p class="mt-2 max-w-xl text-sm leading-6 text-[var(--text-secondary)] sm:text-base sm:leading-7">Mulai dengan jenis acara, kata kunci, atau area tempat yang kamu cari.</p>
                        </div>
                    </article>

                    <article class="grid gap-3 py-5 sm:grid-cols-[4rem_1fr] sm:gap-5 sm:py-6">
                        <p class="font-metadata text-sm tracking-[0.12em] text-[var(--accent-primary)]">02</p>
                        <div>
                            <h3 class="font-editorial text-2xl tracking-[-0.02em] sm:text-3xl">Kenali pilihan yang ada</h3>
                            <p class="mt-2 max-w-xl text-sm leading-6 text-[var(--text-secondary)] sm:text-base sm:leading-7">Lihat informasi venue dan bandingkan detailnya sebelum menentukan pilihan.</p>
                        </div>
                    </article>

                    <article class="grid gap-3 py-5 last:pb-0 sm:grid-cols-[4rem_1fr] sm:gap-5 sm:py-6">
                        <p class="font-metadata text-sm tracking-[0.12em] text-[var(--accent-primary)]">03</p>
                        <div>
                            <h3 class="font-editorial text-2xl tracking-[-0.02em] sm:text-3xl">Lanjutkan rencanamu</h3>
                            <p class="mt-2 max-w-xl text-sm leading-6 text-[var(--text-secondary)] sm:text-base sm:leading-7">Saat menemukan tempat yang sesuai, lanjutkan ke proses pemesanan melalui Adatempat.</p>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA PEMILIK USAHA --}}
    <section aria-labelledby="owner-heading" class="bg-[var(--bg-canvas)]">
        <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-18 lg:px-10 lg:py-20">
            <div class="relative overflow-hidden rounded-[1.75rem] border border-[#C8D9CA] bg-[#E9F3EC] px-5 py-8 sm:px-8 sm:py-10 lg:px-12 lg:py-14">
                <div aria-hidden="true" class="absolute -right-16 -top-24 size-64 rounded-full border-[1px] border-[#C8D9CA] sm:-right-10 sm:-top-32 sm:size-80"></div>
                <div aria-hidden="true" class="absolute -right-7 -top-12 size-44 rounded-full border-[24px] border-[#D6E6D8] sm:size-56"></div>

                <div class="relative z-10 flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="font-metadata text-[11px] uppercase tracking-[0.16em] text-[#49634E] sm:text-xs">Untuk pemilik usaha</p>
                        <h2 id="owner-heading" class="mt-3 font-editorial text-3xl leading-tight tracking-[-0.035em] text-[#203E2A] sm:text-4xl lg:text-5xl">
                            Punya tempat yang ingin dikenal lebih luas?
                        </h2>
                        <p class="mt-4 max-w-xl text-sm leading-6 text-[#49634E] sm:text-base sm:leading-7">
                            Kenalkan usahamu, tampilkan venue, dan mulai bangun kehadiran online lewat Adatempat.
                        </p>
                    </div>

                    <div class="relative z-10 flex shrink-0 flex-col items-start gap-2">
                        <a href="{{ route('register') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-[var(--accent-primary)] px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-[var(--accent-primary-hover)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent-primary)]">
                            Daftarkan usaha
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4">
                                <path d="M5 12h14m-7-7 7 7-7 7"/>
                            </svg>
                        </a>
                        <p class="pl-2 text-xs leading-5 text-[#49634E]">Pilih jenis akun pemilik usaha saat mendaftar.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
