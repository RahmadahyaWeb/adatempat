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

<div class="venue-v2" x-data="{ bookingOpen: false, infoOpen: false }" @keydown.escape.window="bookingOpen=false;infoOpen=false">
    @php
        $venue = $this->venue;
        $business = $venue->business;
        $media = $venue->media->values();
        $activePrices = $this->activePrices();
        $pricingUnit = $this->pricingUnit();
        $startingPrice = $this->startingPrice();
        $details = $this->displayDetails();
        $refundPolicy = $venue->refund_policy_json ?: [
            'customer_cancellation' => ['minimum_minutes_before_start' => 60, 'refund_percentage' => 100],
            'owner_cancellation' => ['refund_percentage' => 100],
        ];
        $unitLabel = match ($pricingUnit) { 'hourly' => 'jam', 'daily' => 'hari', default => 'sesi' };
    @endphp

    <section class="venue-v2-head">
        <div class="site-container">
            <nav class="venue-v2-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('home') }}#hasil">Jelajahi</a>
                <span>›</span>
                <a href="{{ route('business.public', ['businessSlug' => $business->slug]) }}">{{ $business->name }}</a>
            </nav>

            <div class="venue-v2-title-row">
                <div>
                    <div class="venue-v2-kicker">
                        <span>{{ $venue->category?->name ?? 'Venue' }}</span>
                        @if ($business->verified_at)<span>Usaha terverifikasi</span>@endif
                    </div>
                    <h1>{{ $venue->name }}</h1>
                    <p>{{ $business->name }} · {{ $business->city ?: $business->address }}</p>
                </div>

                <button type="button" class="venue-v2-book-top" @click="bookingOpen = true">
                    @if ($startingPrice !== null)
                        <span>Mulai Rp {{ number_format($startingPrice, 0, ',', '.') }} / {{ $unitLabel }}</span>
                    @endif
                    <strong>Pilih jadwal</strong>
                </button>
            </div>
        </div>
    </section>

    <section class="site-container venue-v2-gallery-wrap">
        @if ($media->isNotEmpty())
            <div class="venue-v2-gallery {{ $media->count() === 1 ? 'is-single' : '' }}">
                <img class="venue-v2-gallery-main" src="{{ \Illuminate\Support\Facades\Storage::disk($media->first()->disk)->url($media->first()->file_path) }}" alt="{{ $media->first()->alt_text }}" fetchpriority="high" decoding="async">
                @if ($media->count() > 1)
                    <div class="venue-v2-gallery-grid">
                        @foreach ($media->slice(1, 4) as $photo)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk($photo->disk)->url($photo->file_path) }}" alt="{{ $photo->alt_text }}" loading="lazy" decoding="async">
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            <div class="venue-v2-gallery-empty">Foto venue belum tersedia.</div>
        @endif
    </section>

    <section class="site-container venue-v2-main">
        <div class="venue-v2-story">
            <div class="venue-v2-quickfacts">
                @if ($venue->capacity_persons)
                    <div><strong>{{ number_format((int) $venue->capacity_persons, 0, ',', '.') }}</strong><span>orang</span></div>
                @endif
                @if (($venue->facilities_json ?? []) !== [])
                    <div><strong>{{ count($venue->facilities_json) }}</strong><span>fasilitas</span></div>
                @endif
                @if ($startingPrice !== null)
                    <div><strong>Rp {{ number_format($startingPrice, 0, ',', '.') }}</strong><span>mulai / {{ $unitLabel }}</span></div>
                @endif
            </div>

            <section class="venue-v2-section">
                <h2>Tentang tempat ini</h2>
                <p class="venue-v2-description">{{ $venue->description ?: 'Pemilik venue belum menambahkan deskripsi.' }}</p>
            </section>

            @if (($venue->facilities_json ?? []) !== [])
                <section class="venue-v2-section">
                    <div class="venue-v2-section-head">
                        <h2>Yang tersedia di sini</h2>
                    </div>
                    <div class="venue-v2-amenities">
                        @foreach ($venue->facilities_json as $facility)
                            <span>{{ $facility }}</span>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="venue-v2-section">
                <div class="venue-v2-section-head">
                    <div>
                        <h2>Harga & ketentuan</h2>
                        <p>Lihat rincian harga, jam operasional, spesifikasi, dan kebijakan pembatalan.</p>
                    </div>
                    <button type="button" class="venue-v2-text-button" @click="infoOpen = true">Lihat semua detail</button>
                </div>
            </section>
        </div>

        <aside class="venue-v2-cta-card">
            <div>
                <span class="venue-v2-cta-label">Mulai dari</span>
                @if ($startingPrice !== null)
                    <div class="venue-v2-cta-price">Rp {{ number_format($startingPrice, 0, ',', '.') }} <small>/ {{ $unitLabel }}</small></div>
                @else
                    <div class="venue-v2-cta-price">Harga belum tersedia</div>
                @endif
            </div>
            <ul>
                <li>Cek jadwal real-time</li>
                <li>Harga dihitung sebelum checkout</li>
                <li>Slot ditahan 15 menit saat pembayaran</li>
            </ul>
            <button type="button" class="btn btn-primary w-full" @click="bookingOpen = true">Pilih jadwal</button>
            <p>Belum ada biaya pada tahap ini.</p>
        </aside>
    </section>

    <div class="venue-v2-mobile-bar">
        <div>
            <span>Mulai dari</span>
            <strong>{{ $startingPrice !== null ? 'Rp ' . number_format($startingPrice, 0, ',', '.') : 'Cek harga' }}</strong>
        </div>
        <button type="button" class="btn btn-primary" @click="bookingOpen = true">Pilih jadwal</button>
    </div>

    <div x-cloak x-show="bookingOpen" x-transition.opacity class="venue-v2-modal-backdrop" @click.self="bookingOpen=false">
        <section role="dialog" aria-modal="true" aria-labelledby="booking-modal-title" class="venue-v2-modal">
            <header class="venue-v2-modal-head">
                <div>
                    <span>Booking {{ $venue->name }}</span>
                    <h2 id="booking-modal-title">Pilih jadwalmu</h2>
                </div>
                <button type="button" class="venue-v2-modal-close" @click="bookingOpen=false" aria-label="Tutup modal">
                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                        <path d="M5 5l10 10M15 5 5 15"/>
                    </svg>
                </button>
            </header>

            <div class="venue-v2-modal-body">
                <form wire:submit="checkAvailability" class="venue-v2-booking-form">
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
                            <div class="venue-v2-session-list">
                                @foreach ($activePrices as $price)
                                    <label>
                                        <input type="checkbox" value="{{ $price->id }}" wire:model="selectedSessionIds">
                                        <span>
                                            <strong>{{ $price->session_name ?: 'Sesi' }}</strong>
                                            <small>{{ substr((string) $price->session_start_time, 0, 5) }}–{{ substr((string) $price->session_end_time, 0, 5) }}</small>
                                        </span>
                                        <b>Rp {{ number_format((int) $price->price_amount, 0, ',', '.') }}</b>
                                    </label>
                                @endforeach
                            </div>
                            @error('selectedSessionIds') <p class="ui-error">{{ $message }}</p> @enderror
                        </fieldset>
                    @else
                        <div class="venue-v2-time-grid">
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

                    <button type="submit" class="btn btn-secondary w-full" wire:loading.attr="disabled" wire:target="checkAvailability">
                        <span wire:loading.remove wire:target="checkAvailability">Cek jadwal & harga</span>
                        <span wire:loading wire:target="checkAvailability">Memeriksa...</span>
                    </button>
                </form>

                @if ($availabilityChecked)
                    @if ($isAvailable && $quote)
                        <div class="venue-v2-quote">
                            <div class="venue-v2-quote-status"><span></span>Jadwal tersedia</div>
                            <div><span>Harga dasar</span><span>Rp {{ number_format((int) $quote['base_amount'], 0, ',', '.') }}</span></div>
                            @if ((int) $quote['owner_discount_amount'] > 0)
                                <div><span>Diskon</span><span>− Rp {{ number_format((int) $quote['owner_discount_amount'], 0, ',', '.') }}</span></div>
                            @endif
                            @if ((int) $quote['platform_subsidy_amount'] > 0)
                                <div><span>Subsidi Adatempat</span><span>− Rp {{ number_format((int) $quote['platform_subsidy_amount'], 0, ',', '.') }}</span></div>
                            @endif
                            <div class="venue-v2-quote-total"><span>Total</span><strong>Rp {{ number_format((int) $quote['actual_paid_amount'], 0, ',', '.') }}</strong></div>
                            <button type="button" wire:click="continueBooking" wire:loading.attr="disabled" wire:target="continueBooking" class="btn btn-primary w-full">
                                <span wire:loading.remove wire:target="continueBooking">Lanjutkan booking</span>
                                <span wire:loading wire:target="continueBooking">Menyiapkan...</span>
                            </button>
                            <small>Login pelanggan diperlukan sebelum checkout. Slot belum ditahan.</small>
                        </div>
                    @else
                        <div class="venue-v2-unavailable"><strong>Jadwal belum tersedia.</strong><span>Coba tanggal atau waktu lain.</span></div>
                    @endif
                @endif
            </div>
        </section>
    </div>

    <div x-cloak x-show="infoOpen" x-transition.opacity class="venue-v2-modal-backdrop" @click.self="infoOpen=false">
        <section role="dialog" aria-modal="true" aria-labelledby="info-modal-title" class="venue-v2-modal venue-v2-info-modal">
            <header class="venue-v2-modal-head">
                <div><span>{{ $venue->name }}</span><h2 id="info-modal-title">Detail venue</h2></div>
                <button type="button" class="venue-v2-modal-close" @click="infoOpen=false" aria-label="Tutup modal">
                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                        <path d="M5 5l10 10M15 5 5 15"/>
                    </svg>
                </button>
            </header>

            <div class="venue-v2-modal-body venue-v2-info-body">
                <section>
                    <h3>Harga</h3>
                    <div class="venue-v2-detail-list">
                        @forelse ($activePrices as $price)
                            <div>
                                <span>{{ $price->pricing_unit === 'session' ? ($price->session_name ?: 'Sesi') : match ($price->pricing_unit) { 'hourly' => 'Per jam', 'daily' => 'Per hari', default => 'Harga' } }}</span>
                                <strong>Rp {{ number_format((int) $price->price_amount, 0, ',', '.') }}</strong>
                            </div>
                        @empty
                            <p>Harga belum dikonfigurasi.</p>
                        @endforelse
                    </div>
                </section>

                <section>
                    <h3>Jam operasional</h3>
                    <div class="venue-v2-detail-list">
                        @foreach ($venue->availabilityRules->sortBy('day_of_week') as $rule)
                            <div><span>{{ $this->dayLabel((int) $rule->day_of_week) }}</span><strong>{{ $rule->is_open ? substr((string) $rule->open_time, 0, 5) . '–' . substr((string) $rule->close_time, 0, 5) : 'Tutup' }}</strong></div>
                        @endforeach
                    </div>
                </section>

                @if ($details->isNotEmpty())
                    <section>
                        <h3>Spesifikasi</h3>
                        <div class="venue-v2-detail-list">
                            @foreach ($details as $item)
                                <div><span>{{ $item['label'] }}</span><strong>{{ $item['value'] }}</strong></div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section>
                    <h3>Kebijakan pembatalan</h3>
                    <div class="venue-v2-policy">
                        <p>Pembatalan pelanggan maksimal {{ (int) data_get($refundPolicy, 'customer_cancellation.minimum_minutes_before_start', 60) }} menit sebelum jadwal.</p>
                        <p>Refund pelanggan {{ (int) data_get($refundPolicy, 'customer_cancellation.refund_percentage', 100) }}% sesuai snapshot kebijakan saat booking.</p>
                        <p>Jika Owner membatalkan, refund {{ (int) data_get($refundPolicy, 'owner_cancellation.refund_percentage', 100) }}%.</p>
                        <p>Tidak tersedia reschedule langsung.</p>
                    </div>
                </section>
            </div>
        </section>
    </div>
</div>
