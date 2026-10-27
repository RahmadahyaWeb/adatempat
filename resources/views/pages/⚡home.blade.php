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
            ->with(['business', 'category', 'media', 'prices'])
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

<div class="home-page">
    @php
        $categoryImages = [
            'acara' => 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=1200&q=86',
            'meeting' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1200&q=86',
            'foto' => 'https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=1200&q=86',
            'outdoor' => 'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=1200&q=86',
        ];
        $defaultCategoryImage = 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=1200&q=86';
        $isFiltered = $hasSearched || trim($keyword) !== '' || trim($location) !== '' || $category !== '';
    @endphp

    <section class="home-hero" aria-labelledby="hero-heading">
        <div class="site-container home-hero-grid">
            <div class="home-hero-copy">
                <h1 id="hero-heading" class="home-hero-title">Temukan tempat yang pas untuk rencanamu.</h1>
                <p class="home-hero-description">Bandingkan tempat, lokasi, dan harga untuk meeting, acara, produksi, atau kebutuhan lainnya dalam satu pencarian.</p>

                <form role="search" aria-label="Cari tempat" wire:submit="search" class="home-search">
                    <div class="home-search-field">
                        <label for="keyword">Mau mengadakan apa?</label>
                        <input id="keyword" name="keyword" type="search" wire:model.live.debounce.400ms="keyword" placeholder="Meeting, pernikahan, studio foto..." autocomplete="off">
                    </div>
                    <div class="home-search-field">
                        <label for="location">Lokasi</label>
                        <input id="location" name="location" type="text" wire:model.live.debounce.400ms="location" placeholder="Kota atau daerah" autocomplete="address-level2">
                    </div>
                    <button type="submit" wire:loading.attr="disabled" class="home-search-action">
                        <span wire:loading.remove wire:target="search">Cari tempat</span>
                        <span wire:loading wire:target="search">Mencari...</span>
                    </button>
                </form>

                <div class="home-quick-searches" aria-label="Pencarian populer">
                    <span>Coba:</span>
                    @foreach ($popularSearches as $search)
                        <button type="button" wire:key="popular-search-{{ $loop->index }}" wire:click="choosePopularSearch('{{ $search }}')" aria-pressed="{{ $keyword === $search ? 'true' : 'false' }}">{{ $search }}</button>
                    @endforeach
                </div>
            </div>

            <figure class="home-hero-media">
                <img src="https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=1600&q=88" alt="Ruang acara dengan meja dan kursi yang tertata" fetchpriority="high" decoding="async">
                <figcaption>Ruang acara · inspirasi pencarian Adatempat</figcaption>
            </figure>
        </div>
    </section>

    <section id="kategori" class="home-section home-category-section scroll-mt-24" aria-labelledby="category-heading">
        <div class="site-container">
            <div class="home-section-heading">
                <h2 id="category-heading">Mulai dari kebutuhanmu.</h2>
                <p>Pilih jenis kegiatan untuk mempersempit pilihan tempat yang tersedia.</p>
            </div>

            <div class="home-category-grid">
                @forelse ($this->categories as $item)
                    <button type="button" wire:key="category-{{ $item->id }}" wire:click="chooseCategory('{{ $item->slug }}')" aria-pressed="{{ $category === $item->slug ? 'true' : 'false' }}" class="home-category-item">
                        <img src="{{ $categoryImages[$item->slug] ?? $defaultCategoryImage }}" alt="" loading="lazy" decoding="async">
                        <span class="home-category-copy">
                            <strong>{{ $item->name }}</strong>
                            <span>Lihat tempat</span>
                        </span>
                    </button>
                @empty
                    <div class="home-empty">Kategori belum tersedia. Kamu tetap bisa mencari berdasarkan nama tempat atau lokasi.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section id="hasil" class="home-section scroll-mt-24" aria-labelledby="results-heading">
        <div class="site-container">
            <div class="home-section-heading home-results-heading">
                <div>
                    <h2 id="results-heading">{{ $isFiltered ? 'Tempat yang sesuai pencarianmu.' : 'Tempat yang bisa kamu jelajahi.' }}</h2>
                    <p>{{ $isFiltered ? 'Hasil diperbarui berdasarkan kata kunci, lokasi, dan kategori yang kamu pilih.' : 'Listing venue yang sudah dipublikasikan oleh pemilik tempat.' }}</p>
                </div>
                @if (trim($keyword) !== '' || trim($location) !== '' || $category !== '')
                    <button type="button" wire:click="resetSearch" class="text-action">Bersihkan pencarian</button>
                @endif
            </div>

            <div class="home-venue-grid">
                @forelse ($this->venues as $venue)
                    <article wire:key="venue-{{ $venue->id }}" class="home-venue-item">
                        @php($coverMedia = $venue->media->firstWhere('is_cover', true) ?? $venue->media->first())
                        <div class="home-venue-media">
                            @if ($coverMedia)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk($coverMedia->disk)->url($coverMedia->file_path) }}" alt="{{ $coverMedia->alt_text }}" loading="lazy" decoding="async">
                            @else
                                <div class="home-venue-media-empty">Foto venue belum tersedia</div>
                            @endif
                        </div>
                        <div class="home-venue-copy">
                            <div class="home-venue-meta">
                                <span>{{ $venue->category?->name ?? 'Venue' }}</span>
                                <span>{{ $venue->business?->address ?: 'Lokasi belum dicantumkan' }}</span>
                            </div>
                            <h3>{{ $venue->name }}</h3>
                            <p class="home-venue-business">{{ $venue->business?->name ?? 'Nama usaha belum tersedia' }}</p>
                            @php($activePrices = $venue->prices->where('is_active', true)->values())
                            @if ($activePrices->isNotEmpty())
                                @php($displayPrice = $activePrices->flatMap(fn ($price) => [$price->price_amount, $price->weekend_price_amount])->filter(fn ($amount) => $amount !== null)->min())
                                <p class="home-venue-price">Mulai Rp {{ number_format((int) $displayPrice, 0, ',', '.') }} / {{ match ($activePrices->first()->pricing_unit) { 'hourly' => 'jam', 'daily' => 'hari', default => 'sesi' } }}</p>
                            @endif
                            @if ($venue->description)
                                <p class="home-venue-description">{{ \Illuminate\Support\Str::limit($venue->description, 130) }}</p>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="home-empty home-empty-wide">
                        <h3>{{ $isFiltered ? 'Belum ada tempat yang cocok.' : 'Belum ada listing yang tersedia.' }}</h3>
                        <p>{{ $isFiltered ? 'Coba ubah kata kunci, lokasi, atau kategori yang digunakan.' : 'Tempat yang sudah dipublikasikan akan tampil di bagian ini.' }}</p>
                        @if ($isFiltered)
                            <button type="button" wire:click="resetSearch" class="btn btn-primary">Hapus semua filter</button>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section id="untuk-pemilik" class="home-owner-section scroll-mt-24">
        <div class="site-container">
            <div class="home-owner-panel">
                <div>
                    <h2>Punya tempat yang layak ditemukan lebih banyak orang?</h2>
                    <p>Kelola profil usaha dan venue, atur ketersediaan, terima booking, dan bagikan halaman publik usahamu dari satu tempat.</p>
                </div>
                <a href="{{ route('register') }}" class="home-owner-action">Daftar sebagai pemilik</a>
            </div>
        </div>
    </section>
</div>
