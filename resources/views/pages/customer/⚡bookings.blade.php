<?php

use App\Models\Booking;
use App\Models\Dispute;
use App\Models\User;
use App\Services\Booking\BookingCancellationService;
use App\Services\Booking\BookingCompletionService;
use App\Services\Booking\DisputeService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Booking saya')] class extends Component {
    public string $bookingFilter = 'active';

    public string $cancellationReason = '';
    public string $cancellationBookingId = '';

    public string $selectedBookingId = '';
    public string $disputeCategory = '';
    public string $disputeDescription = '';

    #[Computed]
    public function bookings()
    {
        $customer = $this->requireCustomer();

        $query = Booking::query()
            ->where('customer_id', $customer->getKey())
            ->with([
                'venue:id,name,slug,business_id',
                'venue.business:id,name,slug,timezone',
                'payments' => fn ($query) => $query->latest('id'),
                'refunds' => fn ($query) => $query->latest('id'),
                'dispute',
            ])
            ->orderByDesc('booking_start');

        if ($this->bookingFilter === 'active') {
            $query->whereIn('status', [
                Booking::STATUS_PENDING_PAYMENT,
                Booking::STATUS_PAYMENT_VERIFYING,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_IN_PROGRESS,
                Booking::STATUS_AWAITING_COMPLETION,
                Booking::STATUS_RECONCILIATION_REQUIRED,
                Booking::STATUS_DISPUTED,
            ]);
        } elseif ($this->bookingFilter === 'completed') {
            $query->where('status', Booking::STATUS_COMPLETED);
        } elseif ($this->bookingFilter === 'cancelled') {
            $query->whereIn('status', [
                Booking::STATUS_CANCELLED_BY_CUSTOMER,
                Booking::STATUS_CANCELLED_BY_OWNER,
                Booking::STATUS_CANCELLED_BY_ADMIN,
                Booking::STATUS_EXPIRED,
            ]);
        }

        return $query->limit(100)->get();
    }

    #[Computed]
    public function bookingCounts(): array
    {
        $customer = $this->requireCustomer();

        $base = Booking::query()->where('customer_id', $customer->getKey());

        return [
            'all' => (clone $base)->count(),
            'active' => (clone $base)->whereIn('status', [
                Booking::STATUS_PENDING_PAYMENT,
                Booking::STATUS_PAYMENT_VERIFYING,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_IN_PROGRESS,
                Booking::STATUS_AWAITING_COMPLETION,
                Booking::STATUS_RECONCILIATION_REQUIRED,
                Booking::STATUS_DISPUTED,
            ])->count(),
            'completed' => (clone $base)->where('status', Booking::STATUS_COMPLETED)->count(),
            'cancelled' => (clone $base)->whereIn('status', [
                Booking::STATUS_CANCELLED_BY_CUSTOMER,
                Booking::STATUS_CANCELLED_BY_OWNER,
                Booking::STATUS_CANCELLED_BY_ADMIN,
                Booking::STATUS_EXPIRED,
            ])->count(),
        ];
    }

    private function requireCustomer(): User
    {
        $customer = auth()->user();

        abort_unless(
            $customer instanceof User
            && $customer->isCustomer()
            && $customer->status === 'active'
            && $customer->hasVerifiedEmail(),
            403,
        );

        return $customer;
    }

    public function setBookingFilter(string $filter): void
    {
        if (! in_array($filter, ['active', 'completed', 'cancelled', 'all'], true)) {
            return;
        }

        $this->bookingFilter = $filter;
        unset($this->bookings);
    }

    public function startCancellation(int $bookingId): void
    {
        $customer = $this->requireCustomer();

        Booking::query()
            ->where('customer_id', $customer->getKey())
            ->whereKey($bookingId)
            ->firstOrFail();

        $this->cancellationBookingId = (string) $bookingId;
        $this->cancellationReason = '';
        $this->resetErrorBag();
    }

    public function closeCancellation(): void
    {
        $this->cancellationBookingId = '';
        $this->cancellationReason = '';
        $this->resetErrorBag();
    }

    public function cancelBooking(int $bookingId, BookingCancellationService $cancellations): void
    {
        $customer = $this->requireCustomer();
        $booking = Booking::query()
            ->where('customer_id', $customer->getKey())
            ->whereKey($bookingId)
            ->firstOrFail();

        $reason = trim($this->cancellationReason);

        $cancellations->cancelByCustomer(
            $customer,
            $booking,
            $reason !== '' ? $reason : null,
        );

        $this->closeCancellation();
        unset($this->bookings, $this->bookingCounts);

        session()->flash(
            'status',
            'Booking dibatalkan. Jika ada refund, status prosesnya akan diperbarui di halaman ini.',
        );
    }

    public function confirmCompletion(int $bookingId, BookingCompletionService $completion): void
    {
        $customer = $this->requireCustomer();
        $booking = Booking::query()
            ->where('customer_id', $customer->getKey())
            ->whereKey($bookingId)
            ->firstOrFail();

        $completion->confirmCompletionByCustomer($customer, $booking);

        unset($this->bookings, $this->bookingCounts);

        session()->flash(
            'status',
            'Booking telah dikonfirmasi selesai. Jendela laporan masalah 24 jam tetap berlaku.',
        );
    }

    public function startDispute(int $bookingId): void
    {
        $this->requireCustomer();
        $this->selectedBookingId = (string) $bookingId;
        $this->disputeCategory = '';
        $this->disputeDescription = '';
        $this->resetErrorBag();
    }

    public function cancelDispute(): void
    {
        $this->selectedBookingId = '';
        $this->disputeCategory = '';
        $this->disputeDescription = '';
        $this->resetErrorBag();
    }

    public function openDispute(DisputeService $disputes): void
    {
        $customer = $this->requireCustomer();

        $data = $this->validate([
            'selectedBookingId' => ['required', 'integer', 'exists:bookings,id'],
            'disputeCategory' => ['required', 'string', 'max:80'],
            'disputeDescription' => ['required', 'string', 'min:20', 'max:5000'],
        ], [
            'disputeCategory.required' => 'Pilih kategori masalah.',
            'disputeDescription.required' => 'Jelaskan masalah yang terjadi.',
            'disputeDescription.min' => 'Deskripsi minimal 20 karakter.',
            'disputeDescription.max' => 'Deskripsi maksimal 5.000 karakter.',
        ]);

        $booking = Booking::query()
            ->where('customer_id', $customer->getKey())
            ->whereKey((int) $data['selectedBookingId'])
            ->firstOrFail();

        $dispute = $disputes->open(
            $customer,
            $booking,
            $data['disputeCategory'],
            $data['disputeDescription'],
        );

        $this->cancelDispute();
        unset($this->bookings, $this->bookingCounts);

        session()->flash(
            'status',
            'Laporan ' . $dispute->reference_code . ' telah dibuat dan sedang menunggu peninjauan.',
        );
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            Booking::STATUS_PENDING_PAYMENT => 'Menunggu pembayaran',
            Booking::STATUS_PAYMENT_VERIFYING => 'Pembayaran diperiksa',
            Booking::STATUS_CONFIRMED => 'Terkonfirmasi',
            Booking::STATUS_IN_PROGRESS => 'Sedang berlangsung',
            Booking::STATUS_AWAITING_COMPLETION => 'Menunggu konfirmasi selesai',
            Booking::STATUS_COMPLETED => 'Selesai',
            Booking::STATUS_CANCELLED_BY_CUSTOMER => 'Dibatalkan oleh kamu',
            Booking::STATUS_CANCELLED_BY_OWNER => 'Dibatalkan pemilik',
            Booking::STATUS_CANCELLED_BY_ADMIN => 'Dibatalkan Adatempat',
            Booking::STATUS_EXPIRED => 'Pembayaran kedaluwarsa',
            Booking::STATUS_RECONCILIATION_REQUIRED => 'Perlu verifikasi pembayaran',
            Booking::STATUS_DISPUTED => 'Dalam sengketa',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    public function statusTone(string $status): string
    {
        return match ($status) {
            Booking::STATUS_COMPLETED => 'success',
            Booking::STATUS_DISPUTED,
            Booking::STATUS_RECONCILIATION_REQUIRED => 'danger',
            Booking::STATUS_CANCELLED_BY_CUSTOMER,
            Booking::STATUS_CANCELLED_BY_OWNER,
            Booking::STATUS_CANCELLED_BY_ADMIN,
            Booking::STATUS_EXPIRED => 'muted',
            Booking::STATUS_PENDING_PAYMENT,
            Booking::STATUS_PAYMENT_VERIFYING => 'warning',
            default => 'active',
        };
    }
};
?>
<div
    class="customer-area customer-area-v2"
    x-data="{ cancelOpen: @entangle('cancellationBookingId').live, disputeOpen: @entangle('selectedBookingId').live }"
    x-effect="document.body.classList.toggle('modal-open', !!cancelOpen || !!disputeOpen)"
    @keydown.escape.window="if (cancelOpen) { $wire.closeCancellation() } else if (disputeOpen) { $wire.cancelDispute() }"
>
    <section class="site-container customer-v2-shell">
        <header class="customer-v2-header">
            <div>
                <p class="customer-v2-overline">Aktivitas akun</p>
                <h1>Booking saya</h1>
                <p>Semua reservasi, pembayaran, dan tindak lanjut ada di sini.</p>
            </div>
            <a href="{{ route('home') }}#hasil" class="customer-v2-explore">
                Cari tempat
                <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 10h11m-4-4 4 4-4 4"/>
                </svg>
            </a>
        </header>

        @if (session()->has('status'))
            <div role="status" class="status-note status-success">{{ session('status') }}</div>
        @endif

        @error('booking') <div role="alert" class="status-note">{{ $message }}</div> @enderror
        @error('payment') <div role="alert" class="status-note">{{ $message }}</div> @enderror

        <nav class="customer-v2-tabs" aria-label="Filter booking">
            @foreach ([
                'active' => 'Aktif',
                'completed' => 'Selesai',
                'cancelled' => 'Dibatalkan',
                'all' => 'Semua',
            ] as $key => $label)
                <button
                    type="button"
                    wire:click="setBookingFilter('{{ $key }}')"
                    class="{{ $bookingFilter === $key ? 'is-active' : '' }}"
                    aria-pressed="{{ $bookingFilter === $key ? 'true' : 'false' }}"
                >
                    {{ $label }}
                    <span>{{ $this->bookingCounts[$key] }}</span>
                </button>
            @endforeach
        </nav>

        <div class="customer-v2-list">
            @forelse ($this->bookings as $booking)
                @php
                    $nowUtc = now('UTC');
                    $startsUtc = $booking->booking_start?->copy()->utc();
                    $endsUtc = $booking->booking_end?->copy()->utc();
                    $disputeOpenUntil = $endsUtc?->copy()->addHours(24);
                    $canCancel = $booking->status === Booking::STATUS_CONFIRMED
                        && $startsUtc
                        && $startsUtc->greaterThan($nowUtc->copy()->addMinutes(60));
                    $canReport = in_array($booking->status, [
                        Booking::STATUS_CONFIRMED,
                        Booking::STATUS_IN_PROGRESS,
                        Booking::STATUS_AWAITING_COMPLETION,
                        Booking::STATUS_COMPLETED,
                    ], true)
                        && $endsUtc
                        && $nowUtc->greaterThanOrEqualTo($endsUtc)
                        && $disputeOpenUntil
                        && $nowUtc->lessThanOrEqualTo($disputeOpenUntil)
                        && ! $booking->dispute;
                    $timezone = $booking->venue?->business?->timezone ?: 'Asia/Jakarta';
                    $startLocal = $booking->booking_start?->copy()->timezone($timezone);
                    $endLocal = $booking->booking_end?->copy()->timezone($timezone);
                    $payment = $booking->payments->first();
                    $refund = $booking->refunds->first();
                    $venueUrl = $booking->venue?->business?->slug && $booking->venue?->slug
                        ? route('venue.public', [
                            'businessSlug' => $booking->venue->business->slug,
                            'venueSlug' => $booking->venue->slug,
                        ])
                        : null;
                @endphp

                <article wire:key="customer-booking-{{ $booking->id }}" class="customer-v2-card">
                    <div class="customer-v2-date">
                        <span>{{ $startLocal?->translatedFormat('M') }}</span>
                        <strong>{{ $startLocal?->format('d') }}</strong>
                        <small>{{ $startLocal?->format('Y') }}</small>
                    </div>

                    <div class="customer-v2-card-content">
                        <div class="customer-v2-card-top">
                            <div>
                                <span class="customer-booking-status is-{{ $this->statusTone($booking->status) }}">
                                    {{ $this->statusLabel($booking->status) }}
                                </span>
                                <h2>
                                    @if ($venueUrl)
                                        <a href="{{ $venueUrl }}">{{ $booking->venue?->name ?? 'Venue tidak tersedia' }}</a>
                                    @else
                                        {{ $booking->venue?->name ?? 'Venue tidak tersedia' }}
                                    @endif
                                </h2>
                                <p>{{ $booking->venue?->business?->name }}</p>
                            </div>

                            <div class="customer-v2-amount">
                                <span>Total</span>
                                <strong>Rp {{ number_format((int) $booking->snapshot_actual_paid_amount, 0, ',', '.') }}</strong>
                            </div>
                        </div>

                        <div class="customer-v2-meta">
                            <div>
                                <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><circle cx="10" cy="10" r="7"/><path d="M10 6v4l2.5 1.5"/></svg>
                                <span>{{ $startLocal?->format('H:i') }}–{{ $endLocal?->format('H:i') }}</span>
                            </div>
                            <div>
                                <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M3 5h14v10H3z"/><path d="M3 8h14"/></svg>
                                <span>{{ $booking->reference_code }}</span>
                            </div>
                        </div>

                        @if ($payment || $refund || $booking->dispute)
                            <div class="customer-v2-state-row">
                                @if ($payment)
                                    <span>Pembayaran <strong>{{ str($payment->status)->replace('_', ' ')->title() }}</strong></span>
                                @endif
                                @if ($refund)
                                    <span>Refund <strong>{{ str($refund->status)->replace('_', ' ')->title() }}</strong></span>
                                @endif
                                @if ($booking->dispute)
                                    <span>Laporan <strong>{{ str($booking->dispute->status)->replace('_', ' ')->title() }}</strong></span>
                                @endif
                            </div>
                        @endif

                        <div class="customer-v2-actions">
                            @if ($venueUrl)
                                <a href="{{ $venueUrl }}" class="customer-v2-link">Lihat venue</a>
                            @endif

                            @if ($booking->dispute)
                                <a href="{{ route('disputes.evidence', $booking->dispute) }}" class="customer-v2-link">Lihat laporan</a>
                            @endif

                            <div class="customer-v2-action-buttons">
                                @if ($booking->status === Booking::STATUS_AWAITING_COMPLETION)
                                    <button
                                        type="button"
                                        wire:click="confirmCompletion({{ $booking->id }})"
                                        wire:confirm="Konfirmasi bahwa penggunaan venue telah selesai?"
                                        class="btn btn-primary"
                                    >
                                        Konfirmasi selesai
                                    </button>
                                @endif

                                @if ($canCancel)
                                    <button type="button" wire:click="startCancellation({{ $booking->id }})" class="btn btn-secondary">
                                        Batalkan
                                    </button>
                                @endif

                                @if ($canReport)
                                    <button type="button" wire:click="startDispute({{ $booking->id }})" class="btn btn-secondary">
                                        Laporkan masalah
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="customer-v2-empty">
                    <div>
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="4" y="5" width="16" height="15" rx="2"/>
                            <path d="M8 3v4M16 3v4M4 10h16"/>
                        </svg>
                    </div>
                    <h2>Belum ada booking di sini</h2>
                    <p>Pilih venue yang sesuai, cek jadwal, lalu booking akan muncul di halaman ini.</p>
                    <a href="{{ route('home') }}#hasil" class="btn btn-primary">Jelajahi tempat</a>
                </div>
            @endforelse
        </div>
    </section>

    @if ($cancellationBookingId !== '')
        <div class="customer-modal-backdrop" @click.self="$wire.closeCancellation()" @wheel.stop @touchmove.stop>
            <section role="dialog" aria-modal="true" aria-labelledby="cancel-booking-title" class="customer-modal">
                <header class="customer-modal-head">
                    <div>
                        <span>Pembatalan booking</span>
                        <h2 id="cancel-booking-title">Batalkan booking ini?</h2>
                    </div>
                    <button type="button" class="venue-v2-modal-close" wire:click="closeCancellation" aria-label="Tutup">
                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M5 5l10 10M15 5 5 15"/></svg>
                    </button>
                </header>
                <div class="customer-modal-body">
                    <p>Refund mengikuti snapshot kebijakan yang tersimpan saat booking dibuat. Status refund akan ditampilkan di halaman ini.</p>
                    <div>
                        <label for="cancel-reason" class="ui-label">Alasan pembatalan <span>(opsional)</span></label>
                        <textarea id="cancel-reason" wire:model="cancellationReason" class="ui-input w-full" rows="4" maxlength="2000" placeholder="Ceritakan alasan pembatalan"></textarea>
                    </div>
                    <div class="customer-modal-actions">
                        <button type="button" wire:click="cancelBooking({{ (int) $cancellationBookingId }})" wire:loading.attr="disabled" class="btn btn-primary">
                            <span wire:loading.remove wire:target="cancelBooking">Ya, batalkan booking</span>
                            <span wire:loading wire:target="cancelBooking">Memproses...</span>
                        </button>
                        <button type="button" wire:click="closeCancellation" class="btn btn-secondary">Kembali</button>
                    </div>
                </div>
            </section>
        </div>
    @endif

    @if ($selectedBookingId !== '')
        <div class="customer-modal-backdrop" @click.self="$wire.cancelDispute()" @wheel.stop @touchmove.stop>
            <section role="dialog" aria-modal="true" aria-labelledby="dispute-title" class="customer-modal">
                <header class="customer-modal-head">
                    <div>
                        <span>Laporan masalah</span>
                        <h2 id="dispute-title">Apa yang terjadi?</h2>
                    </div>
                    <button type="button" class="venue-v2-modal-close" wire:click="cancelDispute" aria-label="Tutup">
                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M5 5l10 10M15 5 5 15"/></svg>
                    </button>
                </header>

                <form wire:submit="openDispute" class="customer-modal-body">
                    <p>Laporan akan menahan payout sampai proses peninjauan selesai. Jelaskan kejadian secara akurat.</p>

                    <div>
                        <label for="dispute-category" class="ui-label">Kategori</label>
                        <div class="ui-select-wrap">
                            <select id="dispute-category" wire:model="disputeCategory" class="ui-input ui-select" required>
                                <option value="">Pilih kategori</option>
                                <option value="venue_not_available">Venue tidak tersedia</option>
                                <option value="venue_condition">Kondisi venue bermasalah</option>
                                <option value="service_not_delivered">Layanan tidak diberikan</option>
                                <option value="payment_or_refund">Pembayaran atau refund</option>
                                <option value="other">Lainnya</option>
                            </select>
                            <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="ui-select-chevron"><path d="m6 8 4 4 4-4"/></svg>
                        </div>
                        @error('disputeCategory') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="dispute-description" class="ui-label">Deskripsi kejadian</label>
                        <textarea id="dispute-description" wire:model="disputeDescription" class="ui-input w-full" rows="5" minlength="20" maxlength="5000" required placeholder="Jelaskan kronologi, waktu, dan dampaknya"></textarea>
                        @error('disputeDescription') <p class="ui-error">{{ $message }}</p> @enderror
                        @error('selectedBookingId') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="customer-modal-actions">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove>Kirim laporan</span>
                            <span wire:loading>Mengirim...</span>
                        </button>
                        <button type="button" wire:click="cancelDispute" class="btn btn-secondary">Batal</button>
                    </div>
                </form>
            </section>
        </div>
    @endif
</div>

