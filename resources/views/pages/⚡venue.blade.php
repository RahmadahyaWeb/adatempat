<?php

use App\Models\Business;
use App\Models\Venue;
use App\Models\VenuePrice;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\PricingAndPromoService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail venue')] class extends Component {
    public string $businessSlug = '';
    public string $venueSlug = '';

    public string $bookingDate = '';
    public string $bookingEndDate = '';
    public string $startTime = '';
    public string $endTime = '';
    public array $selectedSessionIds = [];

    public bool $availabilityChecked = false;
    public bool $isAvailable = false;
    public ?array $quote = null;

    public function mount(string $businessSlug, string $venueSlug): void
    {
        $this->businessSlug = $businessSlug;
        $this->venueSlug = $venueSlug;
        $this->bookingDate = now($this->venue->business->timezone ?: 'Asia/Jakarta')->addDay()->format('Y-m-d');
    }

    #[Computed]
    public function venue(): Venue
    {
        return Venue::query()
            ->published()
            ->where('slug', $this->venueSlug)
            ->whereHas('business', fn ($query) => $query->where('slug', $this->businessSlug))
            ->with([
                'business',
                'category',
                'media',
                'prices',
                'availabilityRules',
                'venueTemplate.fields',
                'fieldValues.templateField',
                'fieldValues.ownerCustomField',
            ])
            ->firstOrFail();
    }

    public function pricingUnit(): ?string
    {
        return $this->venue->prices
            ->where('is_active', true)
            ->pluck('pricing_unit')
            ->unique()
            ->values()
            ->when(fn ($units) => $units->count() === 1, fn ($units) => $units)
            ->first();
    }

    public function activePrices()
    {
        return $this->venue->prices->where('is_active', true)->values();
    }

    public function startingPrice(): ?int
    {
        $amounts = $this->activePrices()
            ->flatMap(fn ($price) => [$price->price_amount, $price->weekend_price_amount])
            ->filter(fn ($amount) => $amount !== null)
            ->map(fn ($amount) => (int) $amount);

        return $amounts->isEmpty() ? null : $amounts->min();
    }

    public function displayDetails()
    {
        return $this->venue->fieldValues
            ->map(function ($fieldValue): ?array {
                $field = $fieldValue->templateField ?? $fieldValue->ownerCustomField;

                if ($field === null) {
                    return null;
                }

                if (
                    $fieldValue->ownerCustomField !== null
                    && $fieldValue->ownerCustomField->moderation_status !== \App\Models\OwnerCustomField::STATUS_ACTIVE
                ) {
                    return null;
                }

                $inputType = $field->input_type;
                $value = match ($inputType) {
                    'boolean' => $fieldValue->value_boolean === null ? '' : ($fieldValue->value_boolean ? 'Ya' : 'Tidak'),
                    'number' => $fieldValue->value_number !== null
                        ? number_format((float) $fieldValue->value_number, fmod((float) $fieldValue->value_number, 1.0) === 0.0 ? 0 : 2, ',', '.')
                        : '',
                    'multiselect' => collect($fieldValue->value_json ?? [])->implode(', '),
                    default => (string) ($fieldValue->value_text ?? ''),
                };

                if ($fieldValue->templateField !== null && $fieldValue->templateField->unit_label && $value !== '') {
                    $value .= ' ' . $fieldValue->templateField->unit_label;
                }

                return $value === '' ? null : ['label' => $field->label, 'value' => $value];
            })
            ->filter()
            ->values();
    }

    public function dayLabel(int $day): string
    {
        return [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'][$day] ?? 'Hari';
    }

    public function checkAvailability(AvailabilityService $availability, PricingAndPromoService $pricing): void
    {
        $this->resetValidation();
        $this->availabilityChecked = false;
        $this->isAvailable = false;
        $this->quote = null;

        try {
            [$startsAtUtc, $endsAtUtc, $sessionIds] = $this->selectedWindow();

            $this->isAvailable = $availability->isAvailable($this->venue, $startsAtUtc, $endsAtUtc);

            if (! $this->isAvailable) {
                $this->availabilityChecked = true;
                return;
            }

            $this->quote = $pricing->quote(
                $this->venue,
                $startsAtUtc,
                $endsAtUtc,
                $sessionIds,
            );

            $this->availabilityChecked = true;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }
        }
    }

    public function continueBooking(AvailabilityService $availability, PricingAndPromoService $pricing)
    {
        $this->checkAvailability($availability, $pricing);

        if (! $this->availabilityChecked || ! $this->isAvailable || $this->quote === null) {
            return null;
        }

        [$startsAtUtc, $endsAtUtc, $sessionIds] = $this->selectedWindow();

        return $this->redirectRoute('customer.booking.checkout', [
            'venue' => $this->venue->public_id,
            'starts' => $startsAtUtc->toIso8601String(),
            'ends' => $endsAtUtc->toIso8601String(),
            'sessions' => implode(',', $sessionIds),
        ]);
    }

    private function selectedWindow(): array
    {
        $venue = $this->venue;
        $timezone = $venue->business->timezone ?: 'Asia/Jakarta';
        $unit = $this->pricingUnit();

        if ($unit === VenuePrice::UNIT_DAILY) {
            $validated = $this->validate([
                'bookingDate' => ['required', 'date_format:Y-m-d'],
                'bookingEndDate' => ['required', 'date_format:Y-m-d', 'after:bookingDate'],
            ], [
                'bookingDate.required' => 'Tanggal mulai wajib dipilih.',
                'bookingEndDate.required' => 'Tanggal selesai wajib dipilih.',
                'bookingEndDate.after' => 'Tanggal selesai harus setelah tanggal mulai.',
            ]);

            return [
                CarbonImmutable::parse($validated['bookingDate'] . ' 00:00', $timezone)->utc(),
                CarbonImmutable::parse($validated['bookingEndDate'] . ' 00:00', $timezone)->utc(),
                [],
            ];
        }

        if ($unit === VenuePrice::UNIT_SESSION) {
            $validated = $this->validate([
                'bookingDate' => ['required', 'date_format:Y-m-d'],
                'selectedSessionIds' => ['required', 'array', 'min:1'],
                'selectedSessionIds.*' => ['integer'],
            ], [
                'selectedSessionIds.required' => 'Pilih minimal satu sesi.',
            ]);

            $ids = collect($validated['selectedSessionIds'])->map(fn ($id) => (int) $id)->unique()->values();
            $prices = $this->activePrices()->whereIn('id', $ids)->sortBy('session_start_time')->values();

            if ($prices->count() !== $ids->count()) {
                throw ValidationException::withMessages(['selectedSessionIds' => 'Sesi yang dipilih tidak valid.']);
            }

            $first = $prices->first();
            $last = $prices->last();

            return [
                CarbonImmutable::parse($validated['bookingDate'] . ' ' . substr((string) $first->session_start_time, 0, 5), $timezone)->utc(),
                CarbonImmutable::parse($validated['bookingDate'] . ' ' . substr((string) $last->session_end_time, 0, 5), $timezone)->utc(),
                $ids->all(),
            ];
        }

        $validated = $this->validate([
            'bookingDate' => ['required', 'date_format:Y-m-d'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i'],
        ], [
            'startTime.required' => 'Jam mulai wajib dipilih.',
            'endTime.required' => 'Jam selesai wajib dipilih.',
        ]);

        if ($validated['endTime'] <= $validated['startTime']) {
            throw ValidationException::withMessages(['endTime' => 'Jam selesai harus lebih akhir daripada jam mulai.']);
        }

        return [
            CarbonImmutable::parse($validated['bookingDate'] . ' ' . $validated['startTime'], $timezone)->utc(),
            CarbonImmutable::parse($validated['bookingDate'] . ' ' . $validated['endTime'], $timezone)->utc(),
            [],
        ];
    }
};
?>

<div class="venue-detail-page">
    @php
        $venue = $this->venue;
        $business = $venue->business;
        $media = $venue->media->values();
        $activePrices = $this->activePrices();
        $pricingUnit = $this->pricingUnit();
        $startingPrice = $this->startingPrice();
        $details = $this->displayDetails();
        $refundPolicy = $venue->refund_policy_json ?: [
            'customer_cancellation' => [
                'minimum_minutes_before_start' => 60,
                'refund_percentage' => 100,
            ],
            'owner_cancellation' => [
                'refund_percentage' => 100,
            ],
        ];
    @endphp

    <section class="venue-detail-hero">
        <div class="site-container">
            <div class="venue-detail-breadcrumb">
                <a href="{{ route('home') }}#hasil">Jelajahi</a>
                <span>/</span>
                <a href="{{ route('business.public', ['businessSlug' => $business->slug]) }}">{{ $business->name }}</a>
                <span>/</span>
                <span>{{ $venue->name }}</span>
            </div>

            <div class="venue-detail-heading">
                <div>
                    <span class="venue-detail-category">{{ $venue->category?->name ?? 'Venue' }}</span>
                    <h1>{{ $venue->name }}</h1>
                    <p class="venue-detail-business">{{ $business->name }}</p>
                    <p class="venue-detail-location">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                        {{ $business->address ?: collect([$business->city, $business->province])->filter()->implode(', ') }}
                    </p>
                </div>

                <div class="venue-detail-price-summary">
                    @if ($startingPrice !== null)
                        <span>Mulai dari</span>
                        <strong>Rp {{ number_format($startingPrice, 0, ',', '.') }}</strong>
                        <small>/ {{ match ($pricingUnit) { 'hourly' => 'jam', 'daily' => 'hari', default => 'sesi' } }}</small>
                    @else
                        <span>Harga belum tersedia</span>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="site-container venue-detail-gallery-section">
        @if ($media->isNotEmpty())
            <div class="venue-detail-gallery {{ $media->count() === 1 ? 'is-single' : '' }}">
                <div class="venue-detail-gallery-main">
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk($media->first()->disk)->url($media->first()->file_path) }}" alt="{{ $media->first()->alt_text }}" fetchpriority="high" decoding="async">
                </div>
                @if ($media->count() > 1)
                    <div class="venue-detail-gallery-side">
                        @foreach ($media->slice(1, 4) as $photo)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk($photo->disk)->url($photo->file_path) }}" alt="{{ $photo->alt_text }}" loading="lazy" decoding="async">
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            <div class="venue-detail-gallery-empty">Foto venue belum tersedia.</div>
        @endif
    </section>

    <section class="site-container venue-detail-layout">
        <div class="venue-detail-content">
            <section class="venue-detail-section">
                <h2>Tentang venue</h2>
                <p>{{ $venue->description ?: 'Pemilik venue belum menambahkan deskripsi.' }}</p>

                <div class="venue-detail-facts">
                    @if ($venue->capacity_persons)
                        <div><strong>{{ number_format((int) $venue->capacity_persons, 0, ',', '.') }}</strong><span>Kapasitas orang</span></div>
                    @endif
                    @if (($venue->facilities_json ?? []) !== [])
                        <div><strong>{{ count($venue->facilities_json) }}</strong><span>Fasilitas tersedia</span></div>
                    @endif
                </div>
            </section>

            @if (($venue->facilities_json ?? []) !== [])
                <section class="venue-detail-section">
                    <h2>Fasilitas</h2>
                    <div class="venue-detail-facilities">
                        @foreach ($venue->facilities_json as $facility)
                            <span>{{ $facility }}</span>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($details->isNotEmpty())
                <section class="venue-detail-section">
                    <h2>Spesifikasi</h2>
                    <dl class="venue-detail-specs">
                        @foreach ($details as $item)
                            <div><dt>{{ $item['label'] }}</dt><dd>{{ $item['value'] }}</dd></div>
                        @endforeach
                    </dl>
                </section>
            @endif

            <section class="venue-detail-section">
                <h2>Harga</h2>
                <div class="venue-detail-price-list">
                    @forelse ($activePrices as $price)
                        <div>
                            <span>
                                {{ $price->pricing_unit === 'session' ? ($price->session_name ?: 'Sesi') : match ($price->pricing_unit) { 'hourly' => 'Per jam', 'daily' => 'Per hari', default => 'Harga' } }}
                                @if ($price->pricing_unit === 'session' && $price->session_start_time && $price->session_end_time)
                                    <small>{{ substr((string) $price->session_start_time, 0, 5) }}–{{ substr((string) $price->session_end_time, 0, 5) }}</small>
                                @endif
                            </span>
                            <strong>
                                Rp {{ number_format((int) $price->price_amount, 0, ',', '.') }}
                                @if ($price->weekend_price_amount !== null)
                                    <small>Akhir pekan Rp {{ number_format((int) $price->weekend_price_amount, 0, ',', '.') }}</small>
                                @endif
                            </strong>
                        </div>
                    @empty
                        <p>Harga belum dikonfigurasi.</p>
                    @endforelse
                </div>
            </section>

            <section class="venue-detail-section">
                <h2>Jam operasional</h2>
                <p class="venue-detail-section-note">Zona waktu: {{ $business->timezone ?: 'Asia/Jakarta' }}</p>
                <div class="venue-detail-hours">
                    @foreach ($venue->availabilityRules->sortBy('day_of_week') as $rule)
                        <div>
                            <span>{{ $this->dayLabel((int) $rule->day_of_week) }}</span>
                            <strong>{{ $rule->is_open ? substr((string) $rule->open_time, 0, 5) . '–' . substr((string) $rule->close_time, 0, 5) : 'Tutup' }}</strong>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="venue-detail-section">
                <h2>Kebijakan pembatalan</h2>
                <div class="venue-detail-policy">
                    <p>Pelanggan dapat membatalkan booking paling lambat {{ (int) data_get($refundPolicy, 'customer_cancellation.minimum_minutes_before_start', 60) }} menit sebelum jadwal mulai.</p>
                    <p>Refund pelanggan: {{ (int) data_get($refundPolicy, 'customer_cancellation.refund_percentage', 100) }}% dari jumlah aktual yang dibayar sesuai snapshot kebijakan saat booking.</p>
                    <p>Jika Owner membatalkan, pelanggan menerima refund {{ (int) data_get($refundPolicy, 'owner_cancellation.refund_percentage', 100) }}%.</p>
                    <p>Tidak tersedia reschedule langsung; perubahan jadwal dilakukan dengan membatalkan booking lama dan membuat booking baru.</p>
                </div>
            </section>
        </div>

        <aside class="venue-booking-panel" aria-labelledby="booking-panel-heading">
            <div class="venue-booking-panel-inner">
                <div>
                    <p class="eyebrow"><span class="eyebrow-dot"></span> Cek jadwal</p>
                    <h2 id="booking-panel-heading">Pilih waktu penggunaan</h2>
                    <p>Jadwal diperiksa terhadap jam operasional, booking aktif, hold pembayaran, dan blokir manual.</p>
                </div>

                <form wire:submit="checkAvailability" class="venue-booking-form">
                    <div>
                        <label for="booking-date" class="ui-label">Tanggal {{ $pricingUnit === 'daily' ? 'mulai' : '' }}</label>
                        <input id="booking-date" type="date" min="{{ now($business->timezone ?: 'Asia/Jakarta')->format('Y-m-d') }}" wire:model="bookingDate" class="ui-input">
                        @error('bookingDate') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>

                    @if ($pricingUnit === 'daily')
                        <div>
                            <label for="booking-end-date" class="ui-label">Tanggal selesai</label>
                            <input id="booking-end-date" type="date" min="{{ now($business->timezone ?: 'Asia/Jakarta')->addDay()->format('Y-m-d') }}" wire:model="bookingEndDate" class="ui-input">
                            @error('bookingEndDate') <p class="ui-error">{{ $message }}</p> @enderror
                        </div>
                    @elseif ($pricingUnit === 'session')
                        <fieldset>
                            <legend class="ui-label">Pilih sesi</legend>
                            <div class="venue-session-options">
                                @foreach ($activePrices as $price)
                                    <label>
                                        <input type="checkbox" value="{{ $price->id }}" wire:model="selectedSessionIds">
                                        <span>
                                            <strong>{{ $price->session_name ?: 'Sesi' }}</strong>
                                            <small>{{ substr((string) $price->session_start_time, 0, 5) }}–{{ substr((string) $price->session_end_time, 0, 5) }} · Rp {{ number_format((int) $price->price_amount, 0, ',', '.') }}</small>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('selectedSessionIds') <p class="ui-error">{{ $message }}</p> @enderror
                        </fieldset>
                    @else
                        <div class="venue-booking-time-grid">
                            <div>
                                <label for="booking-start" class="ui-label">Jam mulai</label>
                                <input id="booking-start" type="time" wire:model="startTime" class="ui-input">
                                @error('startTime') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="booking-end" class="ui-label">Jam selesai</label>
                                <input id="booking-end" type="time" wire:model="endTime" class="ui-input">
                                @error('endTime') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif

                    @error('bookingStart') <p class="ui-error">{{ $message }}</p> @enderror
                    @error('pricing') <p class="ui-error">{{ $message }}</p> @enderror
                    @error('duration') <p class="ui-error">{{ $message }}</p> @enderror
                    @error('sessionPriceIds') <p class="ui-error">{{ $message }}</p> @enderror

                    <button type="submit" wire:loading.attr="disabled" wire:target="checkAvailability" class="btn btn-secondary w-full">
                        <span wire:loading.remove wire:target="checkAvailability">Cek ketersediaan</span>
                        <span wire:loading wire:target="checkAvailability">Memeriksa...</span>
                    </button>
                </form>

                @if ($availabilityChecked)
                    @if ($isAvailable && $quote)
                        <div class="venue-booking-result is-available">
                            <strong>Jadwal tersedia</strong>
                            <div>
                                <span>Harga dasar</span>
                                <span>Rp {{ number_format((int) $quote['base_amount'], 0, ',', '.') }}</span>
                            </div>
                            @if ((int) $quote['owner_discount_amount'] > 0)
                                <div><span>Diskon Owner</span><span>− Rp {{ number_format((int) $quote['owner_discount_amount'], 0, ',', '.') }}</span></div>
                            @endif
                            @if ((int) $quote['platform_subsidy_amount'] > 0)
                                <div><span>Subsidi Adatempat</span><span>− Rp {{ number_format((int) $quote['platform_subsidy_amount'], 0, ',', '.') }}</span></div>
                            @endif
                            <div class="venue-booking-total"><span>Total</span><strong>Rp {{ number_format((int) $quote['actual_paid_amount'], 0, ',', '.') }}</strong></div>
                        </div>

                        <button type="button" wire:click="continueBooking" wire:loading.attr="disabled" wire:target="continueBooking" class="btn btn-primary w-full">
                            <span wire:loading.remove wire:target="continueBooking">Lanjutkan booking</span>
                            <span wire:loading wire:target="continueBooking">Menyiapkan...</span>
                        </button>
                        <p class="venue-booking-auth-note">Login pelanggan diperlukan sebelum checkout. Slot belum ditahan pada tahap ini.</p>
                    @else
                        <div class="venue-booking-result is-unavailable">
                            <strong>Jadwal belum tersedia</strong>
                            <p>Pilih tanggal atau waktu lain, lalu periksa kembali.</p>
                        </div>
                    @endif
                @endif
            </div>
        </aside>
    </section>
</div>
