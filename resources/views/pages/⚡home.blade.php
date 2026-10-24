<?php

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $keyword = '';

    public string $location = '';

    public string $category = '';

    public array $popularSearches = ['Venue pernikahan', 'Meeting', 'Ulang tahun', 'Outdoor'];

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->active()->orderBy('name')->get();
    }

    public function chooseCategory(string $slug): void
    {
        $exists = Category::query()->active()->where('slug', $slug)->exists();

        if (!$exists) {
            return;
        }

        $this->category = $this->category === $slug ? '' : $slug;
    }

    public function choosePopularSearch(string $search): void
    {
        if (!in_array($search, $this->popularSearches, true)) {
            return;
        }

        $this->keyword = $search;
    }
};
?>

<div class="overflow-hidden bg-white text-ink">

    {{-- HERO --}}
    <section class="relative">
        <div class="mx-auto max-w-7xl px-5 pb-12 pt-10 sm:px-8 sm:pb-16 sm:pt-14 lg:px-10 lg:pb-20 lg:pt-16">
            <div class="grid items-center gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:gap-12">

                {{-- Headline dan pencarian --}}
                <div class="relative z-10">
                    <div
                        class="mb-6 inline-flex items-center gap-2 rounded-full border border-border bg-surface-subtle px-3.5 py-2">
                        <span class="size-2 rounded-full bg-brand-primary"></span>
                        <span class="text-xs font-semibold tracking-wide text-text-secondary sm:text-sm">
                            TEMUKAN TEMPAT YANG TEPAT
                        </span>
                    </div>

                    <h1
                        class="max-w-2xl text-4xl font-bold leading-[1.12] tracking-tight text-ink sm:text-5xl lg:text-6xl xl:text-7xl">
                        Setiap momen,
                        <span class="text-brand-primary">punya tempatnya.</span>
                    </h1>

                    <p class="mt-6 max-w-xl text-base leading-7 text-text-secondary sm:text-lg sm:leading-8">
                        Dari perayaan spesial hingga pertemuan penting.
                        Temukan venue yang sesuai dengan rencana dan kebutuhanmu.
                    </p>

                    {{-- Form pencarian --}}
                    <div id="jelajahi"
                        class="mt-8 scroll-mt-24 rounded-panel border border-border bg-surface p-2 shadow-card sm:mt-10">
                        <form role="search" aria-label="Cari venue" wire:submit.prevent>
                            <div class="flex items-center gap-3 rounded-control px-3 py-2.5 sm:px-4">
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand-primary">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                        class="size-5">
                                        <circle cx="11" cy="11" r="7" />
                                        <path d="m16 16 4 4" />
                                    </svg>
                                </span>

                                <div class="min-w-0 flex-1">
                                    <label for="keyword" class="mb-1 block text-xs font-semibold text-ink">
                                        Kamu sedang mencari apa?
                                    </label>

                                    <input id="keyword" name="keyword" type="search"
                                        wire:model.live.debounce.400ms="keyword"
                                        placeholder="Nama venue atau jenis acara" autocomplete="off"
                                        class="w-full min-w-0 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-muted focus:outline-none focus:ring-0">
                                </div>

                                @if ($keyword !== '')
                                    <button type="button" wire:click="$set('keyword', '')"
                                        aria-label="Hapus kata kunci"
                                        class="flex size-9 shrink-0 items-center justify-center rounded-full text-muted hover:bg-surface-subtle focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary">
                                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.8" stroke-linecap="round" class="size-4">
                                            <path d="m6 6 12 12M18 6 6 18" />
                                        </svg>
                                    </button>
                                @endif
                            </div>

                            <div class="mx-3 border-t border-border sm:mx-4"></div>

                            <div class="flex items-center gap-3 px-3 py-2.5 sm:px-4">
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-surface-subtle text-text-secondary">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                        class="size-5">
                                        <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" />
                                        <circle cx="12" cy="10" r="2.5" />
                                    </svg>
                                </span>

                                <div class="min-w-0 flex-1">
                                    <label for="location" class="mb-1 block text-xs font-semibold text-ink">
                                        Lokasi
                                    </label>

                                    <input id="location" name="location" type="text"
                                        wire:model.live.debounce.400ms="location" placeholder="Kota atau daerah"
                                        autocomplete="address-level2"
                                        class="w-full min-w-0 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-muted focus:outline-none focus:ring-0">
                                </div>

                                <a href="#kategori"
                                    class="inline-flex min-h-10 shrink-0 items-center gap-1.5 rounded-control px-3 text-sm font-semibold text-brand-primary transition-colors hover:bg-brand-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary">
                                    Kategori
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                        class="size-4">
                                        <path d="m9 18 6-6-6-6" />
                                    </svg>
                                </a>
                            </div>
                        </form>
                    </div>

                    <div class="mt-5 flex flex-wrap items-center gap-2">
                        <span class="mr-1 text-xs font-medium text-muted">Coba cari:</span>

                        @foreach (['Pernikahan', 'Meeting', 'Ulang tahun'] as $suggestion)
                            <button type="button"
                                wire:click="choosePopularSearch('{{ $suggestion === 'Pernikahan' ? 'Venue pernikahan' : $suggestion }}')"
                                class="rounded-full px-2.5 py-1 text-xs font-medium text-text-secondary transition-colors hover:bg-brand-soft hover:text-brand-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary">
                                {{ $suggestion }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Ilustrasi hero --}}
                <div class="relative mx-auto w-full max-w-xl lg:max-w-none">
                    <div class="absolute -right-8 top-2 size-40 rounded-full bg-brand-soft/80 blur-3xl sm:size-56">
                    </div>
                    <div class="absolute -bottom-6 -left-6 size-36 rounded-full bg-amber-100/70 blur-3xl sm:size-48">
                    </div>

                    <div
                        class="relative isolate overflow-hidden rounded-[2rem] border border-border bg-surface-subtle p-4 sm:p-6 lg:p-7">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-xs font-medium text-muted">Rencanakan momenmu</p>
                                <h2 class="mt-1 text-lg font-bold tracking-tight text-ink sm:text-xl">
                                    Tempat yang terasa tepat
                                </h2>
                            </div>

                            <span
                                class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-primary text-white">
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="size-6">
                                    <path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-7h6v7M3 8h18" />
                                </svg>
                            </span>
                        </div>

                        {{-- Ilustrasi rumah --}}
                        <div
                            class="relative mt-6 min-h-80 overflow-hidden rounded-2xl bg-brand-soft px-4 pb-24 pt-8 sm:min-h-[22rem] sm:px-8 sm:pb-28">
                            <div
                                class="absolute -right-10 -top-10 size-36 rounded-full border-[22px] border-white/50 sm:size-48">
                            </div>
                            <div class="absolute left-7 top-8 z-0 size-3 rounded-full bg-amber-400"></div>
                            <div class="absolute left-12 top-16 z-0 size-2 rounded-full bg-brand-primary/40"></div>

                            <svg aria-hidden="true" viewBox="0 0 480 300" fill="none"
                                class="pointer-events-none absolute inset-x-0 bottom-12 z-0 mx-auto w-full max-w-md">
                                <ellipse cx="240" cy="270" rx="180" ry="18"
                                    fill="#C7D2FE" />

                                <path d="M74 250V127L148 83L222 127V250H74Z" fill="#FFFFFF" />
                                <path d="M74 127L148 83L222 127" stroke="#818CF8" stroke-width="5"
                                    stroke-linejoin="round" />
                                <path d="M95 139H201V250H95V139Z" fill="#EEF2FF" />
                                <path d="M112 155H141V190H112V155Z" fill="#A5B4FC" />
                                <path d="M155 155H184V190H155V155Z" fill="#A5B4FC" />
                                <path d="M130 207H166V250H130V207Z" fill="#6366F1" />

                                <path d="M207 250V98L288 53L369 98V250H207Z" fill="#6366F1" />
                                <path d="M207 98L288 53L369 98" stroke="#4338CA" stroke-width="5"
                                    stroke-linejoin="round" />
                                <path d="M229 112H347V250H229V112Z" fill="#EEF2FF" />
                                <path d="M245 128H274V165H245V128Z" fill="#A5B4FC" />
                                <path d="M301 128H330V165H301V128Z" fill="#A5B4FC" />
                                <path d="M245 181H274V218H245V181Z" fill="#A5B4FC" />
                                <path d="M301 181H330V218H301V181Z" fill="#A5B4FC" />
                                <path d="M276 204H301V250H276V204Z" fill="#4338CA" />

                                <path d="M50 250H397" stroke="#818CF8" stroke-width="4" stroke-linecap="round" />
                                <circle cx="386" cy="71" r="20" fill="#FDE68A" />
                                <path d="M388 130C388 119 397 110 408 110C419 110 428 119 428 130V250H388V130Z"
                                    fill="#A7F3D0" />
                                <path d="M408 110V250" stroke="#059669" stroke-width="2" />
                                <path d="M395 146H421M395 166H421M395 186H421" stroke="#059669" stroke-width="2"
                                    stroke-linecap="round" />
                            </svg>

                            {{-- Kartu overlay --}}
                            <div
                                class="absolute inset-x-4 bottom-4 z-10 rounded-2xl border border-white/80 bg-white p-3 shadow-md sm:inset-x-5 sm:bottom-5 sm:p-4">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span
                                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand-primary">
                                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="1.7" stroke-linecap="round"
                                            stroke-linejoin="round" class="size-5">
                                            <path d="M12 3v18M3 12h18" />
                                        </svg>
                                    </span>

                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold leading-5 text-ink">
                                            Sesuai kebutuhanmu
                                        </p>
                                        <p class="mt-1 text-xs leading-5 text-text-secondary">
                                            Jelajahi, bandingkan, lalu pilih venue.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p class="mt-4 text-xs leading-5 text-muted">
                            Mulai dari ide, temukan tempat untuk mewujudkannya.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- KATEGORI --}}
    <section id="kategori" class="scroll-mt-24 border-t border-border bg-surface-subtle/70">
        <div class="mx-auto max-w-7xl px-5 py-12 sm:px-8 sm:py-16 lg:px-10">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                <div>
                    <p class="text-sm font-semibold text-brand-primary">Mulai menjelajah</p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight text-ink sm:text-3xl">
                        Cari berdasarkan kebutuhan
                    </h2>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-text-secondary sm:text-base">
                        Pilih jenis tempat yang ingin kamu temukan.
                    </p>
                </div>

                @if ($category !== '')
                    <button type="button" wire:click="$set('category', '')"
                        class="inline-flex min-h-10 items-center gap-2 self-start rounded-control px-3 text-sm font-semibold text-brand-primary hover:bg-brand-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary sm:self-auto">
                        Hapus pilihan
                        <span aria-hidden="true">×</span>
                    </button>
                @endif
            </div>

            <div class="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
                @forelse ($this->categories as $item)
                    <button type="button" wire:key="category-{{ $item->id }}"
                        wire:click="chooseCategory('{{ $item->slug }}')"
                        aria-pressed="{{ $category === $item->slug ? 'true' : 'false' }}"
                        class="group flex min-h-36 flex-col items-start justify-between rounded-2xl border p-4 text-left transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary sm:min-h-44 sm:p-5 {{ $category === $item->slug ? 'border-brand-primary bg-brand-soft' : 'border-border bg-white hover:border-brand-primary/50 hover:bg-brand-soft/40' }}">
                        <span
                            class="flex size-12 items-center justify-center rounded-2xl transition-colors {{ $category === $item->slug ? 'bg-brand-primary text-white' : 'bg-brand-soft text-brand-primary group-hover:bg-brand-primary group-hover:text-white' }}">
                            @if ($item->slug === 'acara')
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                    class="size-6">
                                    <rect x="3" y="5" width="18" height="16" rx="2" />
                                    <path d="M16 3v4M8 3v4M3 10h18" />
                                </svg>
                            @elseif ($item->slug === 'meeting')
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                    class="size-6">
                                    <rect x="3" y="7" width="18" height="14" rx="2" />
                                    <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2" />
                                </svg>
                            @elseif ($item->slug === 'foto')
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                    class="size-6">
                                    <path d="M14 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9" />
                                    <circle cx="11" cy="12" r="3" />
                                    <path d="m15 4 2-2h4v7" />
                                </svg>
                            @else
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                    class="size-6">
                                    <path d="M3 20h18M5 20V9l7-6 7 6v11M9 20v-6h6v6" />
                                    <path d="m3 9 9 7 9-7" />
                                </svg>
                            @endif
                        </span>

                        <span class="mt-5 flex w-full items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-ink sm:text-base">
                                {{ $item->name }}
                            </span>

                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                class="size-4 shrink-0 text-muted transition-transform group-hover:translate-x-0.5">
                                <path d="m9 18 6-6-6-6" />
                            </svg>
                        </span>
                    </button>
                @empty
                    <p class="col-span-full rounded-2xl border border-border bg-white p-6 text-sm text-text-secondary">
                        Kategori belum tersedia. Silakan coba lagi nanti.
                    </p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- PENCARIAN POPULER --}}
    <section class="mx-auto max-w-7xl px-5 py-12 sm:px-8 sm:py-16 lg:px-10">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-lg">
                <p class="text-sm font-semibold text-brand-primary">Butuh inspirasi?</p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight text-ink sm:text-3xl">
                    Mulai dari ide yang kamu punya
                </h2>
                <p class="mt-2 text-sm leading-6 text-text-secondary sm:text-base">
                    Pilih salah satu kata kunci untuk mengisi pencarian.
                </p>
            </div>

            <div class="flex max-w-2xl flex-wrap gap-2.5">
                @foreach ($popularSearches as $search)
                    <button type="button" wire:click="choosePopularSearch('{{ $search }}')"
                        class="inline-flex min-h-11 items-center gap-2 rounded-full border px-4 py-2.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary {{ $keyword === $search ? 'border-brand-primary bg-brand-soft text-brand-primary' : 'border-border bg-white text-text-secondary hover:border-brand-primary hover:bg-brand-soft/50 hover:text-brand-primary' }}">
                        {{ $search }}

                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-3.5">
                            <path d="m9 18 6-6-6-6" />
                        </svg>
                    </button>
                @endforeach
            </div>
        </div>
    </section>

    {{-- DAFTAR VENUE --}}
    <section class="border-t border-border">
        <div class="mx-auto max-w-7xl px-5 py-12 sm:px-8 sm:py-16 lg:px-10">
            <div>
                <p class="text-sm font-semibold text-brand-primary">Jelajahi Adatempat</p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight text-ink sm:text-3xl">
                    Temukan venue pilihanmu
                </h2>
                <p class="mt-2 max-w-xl text-sm leading-6 text-text-secondary sm:text-base">
                    Tempat yang tepat dimulai dari pencarian yang tepat.
                </p>
            </div>

            <div class="mt-7 overflow-hidden rounded-panel border border-border bg-surface-subtle">
                <div class="grid items-center gap-6 p-6 sm:p-8 lg:grid-cols-[auto_1fr] lg:gap-8 lg:p-10">
                    <div class="flex size-16 items-center justify-center rounded-2xl bg-brand-soft text-brand-primary">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-8">
                            <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" />
                            <circle cx="12" cy="10" r="2.5" />
                        </svg>
                    </div>

                    <div>
                        <h3 class="text-lg font-bold text-ink sm:text-xl">
                            Tempat yang kamu cari akan dimulai di sini.
                        </h3>

                        <p class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary sm:text-base">
                            Daftar venue akan ditampilkan setelah data venue dan proses pencarian terhubung.
                            Untuk saat ini, kamu dapat mencoba mengisi kata kunci dan memilih kategori.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- TENTANG --}}
    <section class="bg-ink text-white">
        <div
            class="mx-auto flex max-w-7xl flex-col gap-5 px-5 py-10 sm:px-8 sm:py-12 lg:flex-row lg:items-center lg:justify-between lg:px-10">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold text-indigo-300">Tentang Adatempat</p>

                <h2 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">
                    Setiap rencana layak mendapat tempat yang tepat.
                </h2>

                <p class="mt-3 text-sm leading-7 text-slate-300 sm:text-base">
                    Adatempat membantu pelanggan menemukan venue dan membantu pemilik usaha
                    menampilkan tempat mereka secara online.
                </p>
            </div>

            <a href="#jelajahi"
                class="inline-flex min-h-11 w-fit shrink-0 items-center gap-2 rounded-control bg-white px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-brand-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                Mulai mencari
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round" class="size-4">
                    <path d="M5 12h14m-7-7 7 7-7 7" />
                </svg>
            </a>
        </div>
    </section>
</div>

