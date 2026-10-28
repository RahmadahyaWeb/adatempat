<?php

use App\Models\Booking;
use App\Models\LegalDocument;
use App\Models\Payment;
use App\Models\TermsAcceptance;
use App\Models\Venue;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingEngineService;
use App\Services\Booking\PricingAndPromoService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Checkout booking')] class extends Component {
    public string $venuePublicId = '';
    public string $starts = '';
    public string $ends = '';
    public array $sessionIds = [];

    public bool $acceptTerms = false;
    public bool $acceptPrivacy = false;

    public string $idempotencyKey = '';

    public ?int $bookingId = null;
    public ?int $paymentId = null;

    public function mount(string $venue): void
    {
        $this->venuePublicId = $venue;
        $this->starts = (string) request()->query('starts', '');
        $this->ends = (string) request()->query('ends', '');
        $sessions = trim((string) request()->query('sessions', ''));

        $this->sessionIds = $sessions === ''
            ? []
            : collect(explode(',', $sessions))
                ->filter(fn ($id) => ctype_digit(trim((string) $id)))
                ->map(fn ($id) => (int) trim((string) $id))
                ->unique()
                ->values()
                ->all();

        $this->idempotencyKey = (string) Str::uuid();
    }

    #[Computed]
    public function venue(): Venue
    {
        return Venue::query()
            ->published()
            ->where('public_id', $this->venuePublicId)
            ->with(['business', 'category', 'prices', 'availabilityRules'])
            ->firstOrFail();
    }

    #[Computed]
    public function termsDocument(): ?LegalDocument
    {
        return LegalDocument::query()
            ->published()
            ->where('type', 'terms_and_conditions')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }

    #[Computed]
    public function privacyDocument(): ?LegalDocument
    {
        return LegalDocument::query()
            ->published()
            ->where('type', 'privacy_policy')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }

    #[Computed]
    public function quote(): ?array
    {
        if ($this->bookingId !== null) {
            return null;
        }

        try {
            [$startsAtUtc, $endsAtUtc] = $this->window();

            if (! app(AvailabilityService::class)->isAvailable($this->venue, $startsAtUtc, $endsAtUtc)) {
                return null;
            }

            return app(PricingAndPromoService::class)->quote(
                $this->venue,
                $startsAtUtc,
                $endsAtUtc,
                $this->sessionIds,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    #[Computed]
    public function booking(): ?Booking
    {
        if ($this->bookingId === null) {
            return null;
        }

        return Booking::query()
            ->whereKey($this->bookingId)
            ->where('customer_id', auth()->id())
            ->with(['venue.business', 'payments', 'holds'])
            ->first();
    }

    #[Computed]
    public function payment(): ?Payment
    {
        if ($this->paymentId === null) {
            return null;
        }

        return Payment::query()
            ->whereKey($this->paymentId)
            ->where('payer_user_id', auth()->id())
            ->first();
    }

    public function confirmBooking(BookingEngineService $engine): void
    {
        $this->resetValidation();

        if (! $this->termsDocument || ! $this->privacyDocument) {
            $this->addError('legal', 'Dokumen legal aktif belum tersedia. Booking belum dapat dilanjutkan.');
            return;
        }

        $this->validate([
            'acceptTerms' => ['accepted'],
            'acceptPrivacy' => ['accepted'],
        ], [
            'acceptTerms.accepted' => 'Syarat dan Ketentuan wajib disetujui.',
            'acceptPrivacy.accepted' => 'Kebijakan Privasi wajib disetujui.',
        ]);

        [$startsAtUtc, $endsAtUtc] = $this->window();

        $customer = auth()->user();

        $termsAcceptance = TermsAcceptance::query()->firstOrCreate(
            [
                'user_id' => $customer->getKey(),
                'legal_document_id' => $this->termsDocument->getKey(),
                'context' => 'booking',
            ],
            [
                'accepted_at' => now('UTC'),
                'ip_address' => request()->ip(),
                'user_agent' => (string) request()->userAgent(),
            ],
        );

        TermsAcceptance::query()->firstOrCreate(
            [
                'user_id' => $customer->getKey(),
                'legal_document_id' => $this->privacyDocument->getKey(),
                'context' => 'booking',
            ],
            [
                'accepted_at' => now('UTC'),
                'ip_address' => request()->ip(),
                'user_agent' => (string) request()->userAgent(),
            ],
        );

        try {
            $result = $engine->createCheckout(
                $customer,
                $this->venue,
                $startsAtUtc,
                $endsAtUtc,
                $termsAcceptance->getKey(),
                $this->idempotencyKey,
                $this->sessionIds,
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }

            return;
        }

        $this->bookingId = $result['booking']->getKey();
        $this->paymentId = $result['payment']?->getKey();

        unset($this->quote, $this->booking, $this->payment);
    }

    private function window(): array
    {
        try {
            $startsAtUtc = CarbonImmutable::parse($this->starts)->utc();
            $endsAtUtc = CarbonImmutable::parse($this->ends)->utc();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'slot' => 'Jadwal booking tidak valid. Kembali ke detail venue dan pilih ulang jadwal.',
            ]);
        }

        if (! $startsAtUtc->isFuture() || ! $startsAtUtc->isBefore($endsAtUtc)) {
            throw ValidationException::withMessages([
                'slot' => 'Jadwal booking sudah tidak berlaku. Pilih ulang jadwal venue.',
            ]);
        }

        return [$startsAtUtc, $endsAtUtc];
    }
};
?>

<div class="booking-checkout-page">
    @php
        $venue = $this->venue;
        $business = $venue->business;
        $quote = $this->quote;
        $booking = $this->booking;
        $payment = $this->payment;
        $timezone = $business->timezone ?: 'Asia/Jakarta';
        try {
            $startsLocal = \Carbon\CarbonImmutable::parse($starts)->setTimezone($timezone);
            $endsLocal = \Carbon\CarbonImmutable::parse($ends)->setTimezone($timezone);
        } catch (\Throwable) {
            $startsLocal = null;
            $endsLocal = null;
        }
    @endphp

    <section class="site-container booking-checkout-shell">
        <div class="booking-checkout-main">
            <a href="{{ route('venue.public', ['businessSlug' => $business->slug, 'venueSlug' => $venue->slug]) }}" class="booking-checkout-back">← Kembali ke detail venue</a>

            @if ($booking)
                <div class="booking-checkout-success">
                    <span class="booking-checkout-success-mark">✓</span>
                    <div>
                        <p class="eyebrow"><span class="eyebrow-dot"></span> Booking dibuat</p>
                        <h1>{{ $booking->payment_required ? 'Selesaikan pembayaranmu.' : 'Booking berhasil dikonfirmasi.' }}</h1>
                        <p>Referensi booking <strong>{{ $booking->reference_code }}</strong>.</p>
                    </div>
                </div>

                <div class="booking-checkout-result-grid">
                    <section class="booking-checkout-card">
                        <h2>Ringkasan booking</h2>
                        <dl class="booking-summary-list">
                            <div><dt>Venue</dt><dd>{{ $venue->name }}</dd></div>
                            <div><dt>Usaha</dt><dd>{{ $business->name }}</dd></div>
                            @if ($startsLocal && $endsLocal)
                                <div><dt>Jadwal</dt><dd>{{ $startsLocal->translatedFormat('d M Y, H:i') }} – {{ $endsLocal->translatedFormat('d M Y, H:i') }}</dd></div>
                            @endif
                            <div><dt>Status</dt><dd>{{ str_replace('_', ' ', $booking->status) }}</dd></div>
                            <div><dt>Total</dt><dd>Rp {{ number_format((int) $booking->snapshot_actual_paid_amount, 0, ',', '.') }}</dd></div>
                        </dl>
                    </section>

                    @if ($payment)
                        <section class="booking-checkout-card booking-payment-card">
                            <p class="eyebrow"><span class="eyebrow-dot"></span> Pembayaran QRIS</p>
                            <h2>Bayar sebelum hold berakhir.</h2>
                            <p>Slot ditahan selama 15 menit sejak checkout dibuat.</p>

                            @if ($payment->qris_payload)
                                <div class="booking-qris-payload">
                                    <span>Payload QRIS</span>
                                    <code>{{ $payment->qris_payload }}</code>
                                </div>
                            @else
                                <div class="status-note">QRIS belum tersedia. Status pembayaran: {{ str_replace('_', ' ', $payment->status) }}.</div>
                            @endif

                            @if ($payment->expires_at)
                                <p class="booking-payment-deadline">Batas pembayaran: {{ $payment->expires_at->setTimezone($timezone)->translatedFormat('d M Y, H:i:s') }} {{ $timezone }}</p>
                            @endif
                        </section>
                    @else
                        <section class="booking-checkout-card">
                            <h2>Tidak ada pembayaran yang diperlukan.</h2>
                            <p>Total akhir booking ini Rp0 sehingga booking langsung dikonfirmasi tanpa QRIS.</p>
                        </section>
                    @endif
                </div>
            @else
                <div class="booking-checkout-heading">
                    <p class="eyebrow"><span class="eyebrow-dot"></span> Checkout booking</p>
                    <h1>Periksa detail sebelum membuat booking.</h1>
                    <p>Slot baru ditahan setelah tombol konfirmasi ditekan. Harga dan promo diperiksa ulang oleh sistem saat booking dibuat.</p>
                </div>

                <div class="booking-checkout-grid">
                    <div class="booking-checkout-left">
                        <section class="booking-checkout-card">
                            <h2>Venue & jadwal</h2>
                            <dl class="booking-summary-list">
                                <div><dt>Venue</dt><dd>{{ $venue->name }}</dd></div>
                                <div><dt>Usaha</dt><dd>{{ $business->name }}</dd></div>
                                <div><dt>Lokasi</dt><dd>{{ $business->address ?: collect([$business->city, $business->province])->filter()->implode(', ') }}</dd></div>
                                @if ($startsLocal && $endsLocal)
                                    <div><dt>Mulai</dt><dd>{{ $startsLocal->translatedFormat('d M Y, H:i') }}</dd></div>
                                    <div><dt>Selesai</dt><dd>{{ $endsLocal->translatedFormat('d M Y, H:i') }}</dd></div>
                                @endif
                            </dl>
                        </section>

                        <section class="booking-checkout-card">
                            <h2>Persetujuan</h2>

                            @if ($this->termsDocument && $this->privacyDocument)
                                <div class="booking-legal-options">
                                    <label>
                                        <input type="checkbox" wire:model="acceptTerms">
                                        <span>Saya menyetujui <strong>{{ $this->termsDocument->title }}</strong> versi {{ $this->termsDocument->version }}.</span>
                                    </label>
                                    @error('acceptTerms') <p class="ui-error">{{ $message }}</p> @enderror

                                    <label>
                                        <input type="checkbox" wire:model="acceptPrivacy">
                                        <span>Saya menyetujui <strong>{{ $this->privacyDocument->title }}</strong> versi {{ $this->privacyDocument->version }}.</span>
                                    </label>
                                    @error('acceptPrivacy') <p class="ui-error">{{ $message }}</p> @enderror
                                </div>
                            @else
                                <div class="status-note">Dokumen legal aktif belum tersedia. Checkout belum dapat dikonfirmasi.</div>
                            @endif

                            <div class="booking-checkout-policy-note">
                                <strong>Kebijakan penting</strong>
                                <p>Booking dibayar 100% di muka. Pembatalan pelanggan hanya dapat dilakukan minimal 1 jam sebelum jadwal mulai. Tidak tersedia reschedule langsung.</p>
                            </div>
                        </section>
                    </div>

                    <aside class="booking-checkout-card booking-checkout-total-card">
                        <h2>Ringkasan harga</h2>

                        @if ($quote)
                            <div class="booking-price-breakdown">
                                <div><span>Harga dasar</span><span>Rp {{ number_format((int) $quote['base_amount'], 0, ',', '.') }}</span></div>
                                @if ((int) $quote['owner_discount_amount'] > 0)
                                    <div><span>Diskon Owner</span><span>− Rp {{ number_format((int) $quote['owner_discount_amount'], 0, ',', '.') }}</span></div>
                                @endif
                                @if ((int) $quote['platform_subsidy_amount'] > 0)
                                    <div><span>Subsidi Adatempat</span><span>− Rp {{ number_format((int) $quote['platform_subsidy_amount'], 0, ',', '.') }}</span></div>
                                @endif
                                <div class="booking-price-total"><span>Total</span><strong>Rp {{ number_format((int) $quote['actual_paid_amount'], 0, ',', '.') }}</strong></div>
                            </div>

                            <button
                                type="button"
                                wire:click="confirmBooking"
                                wire:loading.attr="disabled"
                                wire:target="confirmBooking"
                                class="btn btn-primary w-full"
                                @if (! $this->termsDocument || ! $this->privacyDocument) disabled @endif
                            >
                                <span wire:loading.remove wire:target="confirmBooking">{{ (int) $quote['actual_paid_amount'] > 0 ? 'Buat booking & lanjut QRIS' : 'Konfirmasi booking gratis' }}</span>
                                <span wire:loading wire:target="confirmBooking">Memproses...</span>
                            </button>

                            <p class="booking-checkout-hold-note">{{ (int) $quote['actual_paid_amount'] > 0 ? 'Setelah dikonfirmasi, slot akan ditahan selama 15 menit untuk pembayaran.' : 'Booking Rp0 langsung dikonfirmasi tanpa transaksi QRIS.' }}</p>
                        @else
                            <div class="status-note">Jadwal atau harga sudah berubah/tidak tersedia. Kembali ke halaman venue dan pilih ulang slot.</div>
                        @endif

                        @error('slot') <p class="ui-error">{{ $message }}</p> @enderror
                        @error('bookingStart') <p class="ui-error">{{ $message }}</p> @enderror
                        @error('pricing') <p class="ui-error">{{ $message }}</p> @enderror
                        @error('legal') <p class="ui-error">{{ $message }}</p> @enderror
                    </aside>
                </div>
            @endif
        </div>
    </section>
</div>
