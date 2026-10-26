<?php

use App\Models\Business;
use Illuminate\Database\Eloquent\Collection;
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
                        ->with(['category', 'venueTemplate.fields', 'fieldValues.templateField', 'fieldValues.ownerCustomField', 'media', 'prices', 'availabilityRules'])
                        ->orderBy('name');
                },
            ])
            ->firstOrFail();
    }
};
?>

<div class="min-h-[70vh]">
    @php($business = $this->business)

    <section class="bg-[var(--brand-forest)] text-white">
        <div class="site-container py-9 sm:py-12 lg:py-16">
            <a href="{{ route('home') }}" class="inline-flex min-h-9 items-center gap-2 text-sm font-semibold text-white/80 underline-offset-4 hover:text-white hover:underline">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m15 18-6-6 6-6"/></svg>
                Jelajahi Adatempat
            </a>

            <div class="mt-7 max-w-4xl">
                <p class="inline-flex items-center gap-2 text-xs font-extrabold uppercase tracking-[0.08em] text-[var(--brand-lime)]">
                    <span class="size-2 rounded-full bg-[var(--brand-lime)]"></span>
                    Profil usaha
                </p>
                <h1 class="mt-3 break-words text-3xl font-extrabold leading-tight tracking-[-0.055em] text-white sm:text-4xl lg:text-5xl">{{ $business->name }}</h1>
                @if ($business->address)
                    <p class="mt-4 flex items-start gap-2 text-sm leading-6 text-white/80 sm:text-base">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-5 shrink-0 text-[var(--brand-lime)]"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                        <span>{{ $business->address }}</span>
                    </p>
                @endif
            </div>
        </div>
    </section>

    <section aria-labelledby="venue-list-heading" class="site-container py-9 sm:py-12 lg:py-14">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow"><span class="eyebrow-dot"></span> Katalog usaha</p>
                <h2 id="venue-list-heading" class="mt-3 text-2xl font-extrabold leading-tight tracking-[-0.05em] sm:text-3xl">Venue yang tersedia</h2>
                <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Informasi di halaman ini berasal dari venue yang sudah dipublikasikan.</p>
            </div>
            <span class="text-sm font-semibold text-[var(--muted)]">{{ $business->venues->count() }} venue</span>
        </div>

        @if ($business->venues->isEmpty())
            <div class="mt-6 rounded-2xl bg-[var(--surface-soft)] px-5 py-9 text-center sm:px-8 sm:py-12">
                <span class="mx-auto flex size-12 items-center justify-center rounded-xl bg-[var(--brand-lime)] text-[var(--ink)]">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M10 21v-3h4v3"/></svg>
                </span>
                <h3 class="mt-4 text-lg font-extrabold tracking-[-0.035em]">Belum ada venue yang dipublikasikan.</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[var(--muted)]">Silakan kembali lagi nanti untuk melihat pilihan venue dari usaha ini.</p>
            </div>
        @else
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($business->venues as $venue)
                    <article wire:key="public-venue-{{ $venue->id }}" class="min-w-0 overflow-hidden rounded-2xl bg-white">
                        @php($coverMedia = $venue->media->firstWhere('is_cover', true) ?? $venue->media->first())
                        @if ($coverMedia)
                            <div class="aspect-[4/3] overflow-hidden bg-[var(--surface-soft)]">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk($coverMedia->disk)->url($coverMedia->file_path) }}" alt="{{ $coverMedia->alt_text }}" loading="lazy" decoding="async" class="size-full object-cover">
                            </div>
                        @else
                            <div class="flex aspect-[4/3] flex-col items-center justify-center gap-3 bg-[var(--surface-soft)] px-5 text-center">
                                <span class="flex size-11 items-center justify-center rounded-xl bg-white text-[var(--brand-forest)]">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="size-6"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 15-5-5L5 20"/></svg>
                                </span>
                                <p class="text-xs font-semibold text-[var(--muted)]">Foto venue belum tersedia</p>
                            </div>
                        @endif
                        <div class="p-4 sm:p-5">
                            <span class="inline-flex max-w-full items-center rounded-md bg-[var(--surface-lime)] px-2.5 py-1 text-xs font-bold text-[var(--brand-forest-deep)]">{{ $venue->category?->name ?? 'Kategori belum tersedia' }}</span>
                            <h3 class="mt-3 break-words text-lg font-extrabold leading-tight tracking-[-0.04em]">{{ $venue->name }}</h3>
                            @php($activePrices = $venue->prices->where('is_active', true)->values())
                            @if ($activePrices->isNotEmpty())
                                <div class="mt-3 rounded-lg bg-[var(--surface-soft)] p-3">
                                    @if ($activePrices->first()->pricing_unit === 'session')
                                        <p class="text-xs font-bold uppercase tracking-[0.07em] text-[var(--muted)]">Harga per sesi</p>
                                        <div class="mt-2 space-y-2">
                                            @foreach ($activePrices as $price)
                                                <div class="flex items-start justify-between gap-3 text-sm">
                                                    <span class="min-w-0 break-words font-semibold text-[var(--ink-soft)]">{{ $price->session_name }} <span class="block text-xs font-normal text-[var(--muted)]">{{ substr((string) $price->session_start_time, 0, 5) }}–{{ substr((string) $price->session_end_time, 0, 5) }}</span></span>
                                                    <span class="shrink-0 text-right font-extrabold text-[var(--brand-forest)]">Rp {{ number_format((int) $price->price_amount, 0, ',', '.') }}
                                                        @if ($price->weekend_price_amount !== null)
                                                            <span class="block text-xs font-semibold text-[var(--muted)]">Akhir pekan Rp {{ number_format((int) $price->weekend_price_amount, 0, ',', '.') }}</span>
                                                        @endif
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-xs font-bold uppercase tracking-[0.07em] text-[var(--muted)]">Harga dasar</p>
                                        <p class="mt-1 text-lg font-extrabold text-[var(--brand-forest)]">Rp {{ number_format((int) $activePrices->first()->price_amount, 0, ',', '.') }} / {{ $activePrices->first()->pricing_unit === 'hourly' ? 'jam' : 'hari' }}</p>
                                        @if ($activePrices->first()->weekend_price_amount !== null)
                                            <p class="mt-1 text-xs font-semibold text-[var(--muted)]">Akhir pekan Rp {{ number_format((int) $activePrices->first()->weekend_price_amount, 0, ',', '.') }}</p>
                                        @endif
                                    @endif
                                </div>
                            @endif
                            @if ($venue->availabilityRules->isNotEmpty())
                                <div class="mt-3 rounded-lg border border-[var(--border)] p-3">
                                    <h4 class="text-xs font-bold uppercase tracking-[0.07em] text-[var(--muted)]">Jam operasional · {{ $business->timezone ?: 'Asia/Jakarta' }}</h4>
                                    <div class="mt-2 space-y-1.5">
                                        @php($dayLabels = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'])
                                        @foreach ($venue->availabilityRules->sortBy('day_of_week') as $availabilityRule)
                                            <div class="flex items-start justify-between gap-3 text-xs">
                                                <span class="font-semibold text-[var(--ink-soft)]">{{ $dayLabels[$availabilityRule->day_of_week] ?? 'Hari' }}</span>
                                                @if ($availabilityRule->is_open)
                                                    <span class="text-right text-[var(--ink)]">{{ substr((string) $availabilityRule->open_time, 0, 5) }}–{{ substr((string) $availabilityRule->close_time, 0, 5) }}@if ($availabilityRule->buffer_minutes > 0) <span class="block text-[var(--muted)]">Jeda {{ $availabilityRule->buffer_minutes }} menit</span>@endif</span>
                                                @else
                                                    <span class="text-[var(--muted)]">Tutup</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($venue->description)
                                <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">{{ \Illuminate\Support\Str::limit($venue->description, 180) }}</p>
                            @else
                                <p class="mt-2 text-sm leading-6 text-[var(--muted)]">Deskripsi venue belum ditambahkan oleh pemilik usaha.</p>
                            @endif

                            @if ($venue->capacity_persons || ($venue->facilities_json ?? []) !== [])
                                <div class="mt-4 border-t border-[var(--border)] pt-4">
                                    @if ($venue->capacity_persons)
                                        <p class="text-sm font-semibold text-[var(--ink-soft)]">Kapasitas: {{ number_format($venue->capacity_persons) }} orang</p>
                                    @endif
                                    @if (($venue->facilities_json ?? []) !== [])
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach ($venue->facilities_json as $facility)
                                                <span class="inline-flex items-center rounded-md bg-[var(--surface-soft)] px-2 py-1 text-xs font-semibold text-[var(--ink-soft)]">{{ $facility }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @php
                                $displayTemplateValues = $venue->fieldValues
                                    ->filter(fn ($fieldValue) => $fieldValue->templateField !== null)
                                    ->map(function ($fieldValue) {
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
                            @endphp

                            @if ($displayTemplateValues->isNotEmpty())
                                <dl class="mt-4 space-y-2 border-t border-[var(--border)] pt-4">
                                    @foreach ($displayTemplateValues as $item)
                                        <div class="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-3">
                                            <dt class="text-xs font-semibold text-[var(--muted)]">{{ $item['label'] }}</dt>
                                            <dd class="break-words text-sm font-semibold text-[var(--ink-soft)] sm:text-right">{{ $item['value'] }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif

                            @php
                                $displayCustomValues = $venue->fieldValues
                                    ->filter(fn ($fieldValue) => $fieldValue->ownerCustomField !== null && $fieldValue->ownerCustomField->moderation_status === \App\Models\OwnerCustomField::STATUS_ACTIVE)
                                    ->map(function ($fieldValue) {
                                        $field = $fieldValue->ownerCustomField;
                                        $value = match ($field->input_type) {
                                            'boolean' => $fieldValue->value_boolean === null ? '' : ($fieldValue->value_boolean ? 'Ya' : 'Tidak'),
                                            'number' => $fieldValue->value_number !== null
                                                ? number_format((float) $fieldValue->value_number, fmod((float) $fieldValue->value_number, 1.0) === 0.0 ? 0 : 2, ',', '.')
                                                : '',
                                            default => (string) ($fieldValue->value_text ?? ''),
                                        };

                                        return ['label' => $field->label, 'value' => $value];
                                    })
                                    ->filter(fn ($item) => $item['value'] !== '')
                                    ->values();
                            @endphp

                            @if ($displayCustomValues->isNotEmpty())
                                <dl class="mt-4 space-y-2 border-t border-[var(--border)] pt-4">
                                    @foreach ($displayCustomValues as $item)
                                        <div class="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-3">
                                            <dt class="text-xs font-semibold text-[var(--muted)]">{{ $item['label'] }}</dt>
                                            <dd class="break-words text-sm font-semibold text-[var(--ink-soft)] sm:text-right">{{ $item['value'] }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>