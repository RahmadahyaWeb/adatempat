<?php

use App\Models\Business;
use App\Models\Venue;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profil usaha')] class extends Component {
    public string $businessSlug = '';

    public function mount(string $businessSlug): void
    {
        $this->businessSlug = $businessSlug;
    }

    #[Computed]
    public function business(): Business
    {
        return Business::query()
            ->where('slug', $this->businessSlug)
            ->where('status', Business::STATUS_ACTIVE)
            ->whereNotNull('published_at')
            ->with([
                'venues' => function ($query): void {
                    $query->published()
                        ->with([
                            'category',
                            'venueTemplate.fields',
                            'fieldValues.templateField',
                            'fieldValues.ownerCustomField',
                            'media',
                            'prices',
                            'availabilityRules',
                        ])
                        ->orderBy('name');
                },
            ])
            ->firstOrFail();
    }

    public function coverMedia(Venue $venue): mixed
    {
        return $venue->media->firstWhere('is_cover', true) ?? $venue->media->first();
    }

    public function activePrices(Venue $venue): Collection
    {
        return $venue->prices->where('is_active', true)->values();
    }

    public function startingPrice(Venue $venue): ?int
    {
        $amounts = $this->activePrices($venue)
            ->flatMap(fn ($price) => [$price->price_amount, $price->weekend_price_amount])
            ->filter(fn ($amount) => $amount !== null)
            ->map(fn ($amount) => (int) $amount);

        return $amounts->isEmpty() ? null : $amounts->min();
    }

    public function pricingUnitLabel(Venue $venue): string
    {
        $unit = $this->activePrices($venue)->first()?->pricing_unit;

        return match ($unit) {
            'hourly' => 'jam',
            'daily' => 'hari',
            default => 'sesi',
        };
    }

    public function dayLabel(int $day): string
    {
        return [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ][$day] ?? 'Hari';
    }

    public function displayTemplateValues(Venue $venue): Collection
    {
        return $venue->fieldValues
            ->filter(fn ($fieldValue) => $fieldValue->templateField !== null)
            ->map(function ($fieldValue): array {
                $field = $fieldValue->templateField;
                $value = match ($field->input_type) {
                    'boolean' => $fieldValue->value_boolean === null ? '' : ($fieldValue->value_boolean ? 'Ya' : 'Tidak'),
                    'number' => $fieldValue->value_number !== null
                        ? number_format(
                            (float) $fieldValue->value_number,
                            fmod((float) $fieldValue->value_number, 1.0) === 0.0 ? 0 : 2,
                            ',',
                            '.',
                        ) . ($field->unit_label ? ' ' . $field->unit_label : '')
                        : '',
                    'multiselect' => collect($fieldValue->value_json ?? [])->implode(', '),
                    default => (string) ($fieldValue->value_text ?? ''),
                };

                return ['label' => $field->label, 'value' => $value];
            })
            ->filter(fn ($item) => $item['value'] !== '')
            ->values();
    }

    public function displayCustomValues(Venue $venue): Collection
    {
        return $venue->fieldValues
            ->filter(fn ($fieldValue) => $fieldValue->ownerCustomField !== null
                && $fieldValue->ownerCustomField->moderation_status === \App\Models\OwnerCustomField::STATUS_ACTIVE)
            ->map(function ($fieldValue): array {
                $field = $fieldValue->ownerCustomField;
                $value = match ($field->input_type) {
                    'boolean' => $fieldValue->value_boolean === null ? '' : ($fieldValue->value_boolean ? 'Ya' : 'Tidak'),
                    'number' => $fieldValue->value_number !== null
                        ? number_format(
                            (float) $fieldValue->value_number,
                            fmod((float) $fieldValue->value_number, 1.0) === 0.0 ? 0 : 2,
                            ',',
                            '.',
                        )
                        : '',
                    default => (string) ($fieldValue->value_text ?? ''),
                };

                return ['label' => $field->label, 'value' => $value];
            })
            ->filter(fn ($item) => $item['value'] !== '')
            ->values();
    }
};
?>

<div class="business-public-page">
    @php
        $business = $this->business;
        $venues = $business->venues;
        $featuredVenue = $venues->first();
        $featuredMedia = $featuredVenue ? $this->coverMedia($featuredVenue) : null;
        $locationLabel = collect([$business->city, $business->province])->filter()->implode(', ');
    @endphp

    <section class="business-public-hero">
        <div class="site-container">
            <a href="{{ route('home') }}#hasil" class="business-back-link">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m15 18-6-6 6-6"/></svg>
                Kembali ke pencarian
            </a>

            <div class="business-public-hero-grid">
                <div class="business-public-identity">
                    <div class="business-public-status">
                        @if ($business->verified_at)
                            <span class="business-verified">
                                <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6.5 10.5 2.2 2.2 4.8-5"/></svg>
                                Usaha terverifikasi
                            </span>
                        @endif
                    </div>

                    <h1>{{ $business->name }}</h1>

                    @if ($locationLabel !== '' || $business->address)
                        <p class="business-public-location">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                            <span>{{ $locationLabel !== '' ? $locationLabel : $business->address }}</span>
                        </p>
                    @endif

                    @if ($business->address && $locationLabel !== '')
                        <p class="business-public-address">{{ $business->address }}</p>
                    @endif

                    <div class="business-public-summary">
                        <div>
                            <strong>{{ $venues->count() }}</strong>
                            <span>venue tersedia</span>
                        </div>
                        @if ($venues->whereNotNull('capacity_persons')->isNotEmpty())
                            <div>
                                <strong>{{ number_format((int) $venues->max('capacity_persons'), 0, ',', '.') }}</strong>
                                <span>kapasitas terbesar</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="business-public-hero-media">
                    @if ($featuredMedia)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk($featuredMedia->disk)->url($featuredMedia->file_path) }}" alt="{{ $featuredMedia->alt_text }}" fetchpriority="high" decoding="async">
                    @else
                        <div class="business-public-hero-placeholder">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 15-5-5L5 20"/></svg>
                            <span>Foto usaha akan tampil dari venue yang dipublikasikan.</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section aria-labelledby="venue-list-heading" class="business-public-content">
        <div class="site-container">
            <div class="business-public-section-heading">
                <div>
                    <p class="eyebrow"><span class="eyebrow-dot"></span> Pilihan tempat</p>
                    <h2 id="venue-list-heading">Venue di {{ $business->name }}</h2>
                    <p>Pilih venue berdasarkan kapasitas, fasilitas, harga, dan kebutuhanmu. Detail tambahan tetap tersedia tanpa membuat daftar sulit dipindai.</p>
                </div>
                <span class="business-public-count">{{ $venues->count() }} venue</span>
            </div>

            @if ($venues->isEmpty())
                <div class="business-public-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M10 21v-3h4v3"/></svg>
                    <h3>Belum ada venue yang dipublikasikan.</h3>
                    <p>Usaha ini sudah aktif, tetapi belum memiliki venue publik yang dapat ditampilkan.</p>
                    <a href="{{ route('home') }}#hasil" class="btn btn-secondary">Jelajahi tempat lain</a>
                </div>
            @else
                <div class="business-venue-list">
                    @foreach ($venues as $venue)
                        @php
                            $coverMedia = $this->coverMedia($venue);
                            $startingPrice = $this->startingPrice($venue);
                            $templateValues = $this->displayTemplateValues($venue);
                            $customValues = $this->displayCustomValues($venue);
                        @endphp

                        <article id="venue-{{ $venue->public_id }}" class="business-venue-card">
                            <div class="business-venue-media">
                                @if ($coverMedia)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk($coverMedia->disk)->url($coverMedia->file_path) }}" alt="{{ $coverMedia->alt_text }}" loading="lazy" decoding="async">
                                @else
                                    <div class="business-venue-media-placeholder">
                                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 15-5-5L5 20"/></svg>
                                        <span>Foto venue belum tersedia</span>
                                    </div>
                                @endif
                            </div>

                            <div class="business-venue-main">
                                <div class="business-venue-topline">
                                    <span class="business-venue-category">{{ $venue->category?->name ?? 'Venue' }}</span>
                                    @if ($venue->capacity_persons)
                                        <span class="business-venue-capacity">{{ number_format((int) $venue->capacity_persons, 0, ',', '.') }} orang</span>
                                    @endif
                                </div>

                                <h3>{{ $venue->name }}</h3>

                                @if ($venue->description)
                                    <p class="business-venue-description">{{ \Illuminate\Support\Str::limit($venue->description, 170) }}</p>
                                @endif

                                @if (($venue->facilities_json ?? []) !== [])
                                    <div class="business-venue-facilities" aria-label="Fasilitas utama">
                                        @foreach (array_slice($venue->facilities_json, 0, 4) as $facility)
                                            <span>{{ $facility }}</span>
                                        @endforeach
                                        @if (count($venue->facilities_json) > 4)
                                            <span>+{{ count($venue->facilities_json) - 4 }} lainnya</span>
                                        @endif
                                    </div>
                                @endif

                                <div class="business-venue-bottom">
                                    <div>
                                        @if ($startingPrice !== null)
                                            <span class="business-venue-price-label">Mulai dari</span>
                                            <p class="business-venue-price">Rp {{ number_format($startingPrice, 0, ',', '.') }} <span>/ {{ $this->pricingUnitLabel($venue) }}</span></p>
                                        @else
                                            <p class="business-venue-no-price">Harga belum dicantumkan</p>
                                        @endif
                                    </div>

                                    <button
                                        type="button"
                                        class="business-venue-detail-link"
                                        onclick="const details = this.closest('article').querySelector('details'); details.open = true; details.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest' });"
                                    >
                                        Lihat detail
                                    </button>
                                </div>

                                <details id="venue-detail-{{ $venue->public_id }}" class="business-venue-details">
                                    <summary>Detail venue dan jam operasional</summary>

                                    <div class="business-venue-details-grid">
                                        @if ($this->activePrices($venue)->isNotEmpty())
                                            <section>
                                                <h4>{{ $this->activePrices($venue)->first()?->pricing_unit === 'session' ? 'Harga per sesi' : 'Harga dasar' }}</h4>
                                                <div class="business-venue-detail-list">
                                                    @foreach ($this->activePrices($venue) as $price)
                                                        <div>
                                                            <span>
                                                                {{ $price->pricing_unit === 'session' ? ($price->session_name ?: 'Sesi') : ucfirst($this->pricingUnitLabel($venue)) }}
                                                                @if ($price->pricing_unit === 'session' && $price->session_start_time && $price->session_end_time)
                                                                    <small>{{ substr((string) $price->session_start_time, 0, 5) }}–{{ substr((string) $price->session_end_time, 0, 5) }}</small>
                                                                @endif
                                                            </span>
                                                            <strong>
                                                                {{ 'Rp ' . number_format((int) $price->price_amount, 0, ',', '.') . ($price->pricing_unit !== 'session' ? ' / ' . $this->pricingUnitLabel($venue) : '') }}
                                                                @if ($price->weekend_price_amount !== null)
                                                                    <small>{{ 'Akhir pekan Rp ' . number_format((int) $price->weekend_price_amount, 0, ',', '.') }}</small>
                                                                @endif
                                                            </strong>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </section>
                                        @endif

                                        @if ($venue->availabilityRules->isNotEmpty())
                                            <section>
                                                <h4>Jam operasional · {{ $business->timezone ?: 'Asia/Jakarta' }}</h4>
                                                <div class="business-venue-detail-list">
                                                    @foreach ($venue->availabilityRules->sortBy('day_of_week') as $rule)
                                                        <div>
                                                            <span>{{ $this->dayLabel((int) $rule->day_of_week) }}</span>
                                                            <strong>
                                                                {{ $rule->is_open
                                                                    ? substr((string) $rule->open_time, 0, 5) . '–' . substr((string) $rule->close_time, 0, 5)
                                                                    : 'Tutup' }}
                                                            </strong>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </section>
                                        @endif

                                        @if ($venue->capacity_persons || $templateValues->isNotEmpty() || $customValues->isNotEmpty())
                                            <section>
                                                <h4>Spesifikasi</h4>
                                                <div class="business-venue-detail-list">
                                                    @if ($venue->capacity_persons)
                                                        <div>
                                                            <span>Kapasitas</span>
                                                            <strong>Kapasitas: {{ number_format((int) $venue->capacity_persons, 0, ',', '.') }} orang</strong>
                                                        </div>
                                                    @endif
                                                    @foreach ($templateValues->concat($customValues) as $item)
                                                        <div>
                                                            <span>{{ $item['label'] }}</span>
                                                            <strong>{{ $item['value'] }}</strong>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </section>
                                        @endif

                                        @if (($venue->facilities_json ?? []) !== [])
                                            <section>
                                                <h4>Fasilitas</h4>
                                                <div class="business-venue-facilities">
                                                    @foreach ($venue->facilities_json as $facility)
                                                        <span>{{ $facility }}</span>
                                                    @endforeach
                                                </div>
                                            </section>
                                        @endif
                                    </div>
                                </details>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>
