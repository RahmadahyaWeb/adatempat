<?php

use App\Models\Booking;
use App\Models\Dispute;
use App\Models\User;
use App\Services\Booking\BookingCancellationService;
use App\Services\Booking\BookingCompletionService;
use App\Services\Booking\DisputeService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Booking venue')] class extends Component {
    public string $cancellationReason = '';

    public string $cancellationBookingId = '';

    public string $selectedBookingId = '';

    public string $disputeCategory = '';

    public string $disputeDescription = '';

    public string $selectedDisputeId = '';

    public string $ownerResponse = '';

    #[Computed]
    public function bookings()
    {
        $owner = $this->requireOwner();

        return Booking::query()
            ->whereHas('venue.business', fn ($query) => $query->where('owner_id', $owner->getKey()))
            ->with([
                'venue:id,name,business_id',
                'venue.business:id,name,owner_id',
                'customer:id,name,email',
                'dispute.openedBy:id,name,email',
            ])
            ->orderByDesc('booking_start')
            ->limit(100)
            ->get();
    }

    private function requireOwner(): User
    {
        $owner = auth()->user();

        abort_unless($owner instanceof User && $owner->isOwner() && $owner->status === 'active' && $owner->hasVerifiedEmail(), 403);

        return $owner;
    }

    public function startCancellation(int $bookingId): void
    {
        $owner = $this->requireOwner();

        Booking::query()
            ->whereKey($bookingId)
            ->whereHas('venue.business', fn ($query) => $query->where('owner_id', $owner->getKey()))
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
        $owner = $this->requireOwner();
        $booking = Booking::query()
            ->whereKey($bookingId)
            ->whereHas('venue.business', fn ($query) => $query->where('owner_id', $owner->getKey()))
            ->firstOrFail();

        $reason = trim($this->cancellationReason);

        if ($reason === '') {
            $this->addError('cancellationReason', 'Alasan pembatalan Owner wajib diisi.');

            return;
        }

        $cancellations->cancelByOwner($owner, $booking, $reason);
        $this->closeCancellation();
        unset($this->bookings);
        session()->flash('status', 'Booking dibatalkan. Refund 100% diminta apabila pembayaran telah diterima.');
    }

    public function startBooking(int $bookingId, BookingCompletionService $completion): void
    {
        $owner = $this->requireOwner();
        $booking = Booking::query()
            ->whereKey($bookingId)
            ->whereHas('venue.business', fn ($query) => $query->where('owner_id', $owner->getKey()))
            ->firstOrFail();

        $completion->startByOwner($owner, $booking);
        unset($this->bookings);
        session()->flash('status', 'Booking ditandai sedang berlangsung.');
    }

    public function requestCompletion(int $bookingId, BookingCompletionService $completion): void
    {
        $owner = $this->requireOwner();
        $booking = Booking::query()
            ->whereKey($bookingId)
            ->whereHas('venue.business', fn ($query) => $query->where('owner_id', $owner->getKey()))
            ->firstOrFail();

        $completion->requestCompletionByOwner($owner, $booking);
        unset($this->bookings);
        session()->flash('status', 'Penyelesaian diajukan. Pelanggan memiliki jendela 24 jam untuk konfirmasi atau laporan masalah.');
    }

    public function startDispute(int $bookingId): void
    {
        $owner = $this->requireOwner();
        abort_unless(Booking::query()->whereKey($bookingId)->whereHas('venue.business', fn ($query) => $query->where('owner_id', $owner->getKey()))->exists(), 404);

        $this->selectedBookingId = (string) $bookingId;
        $this->disputeCategory = '';
        $this->disputeDescription = '';
        $this->resetErrorBag();
    }

    public function openDispute(DisputeService $disputes): void
    {
        $owner = $this->requireOwner();

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
            ->whereKey((int) $data['selectedBookingId'])
            ->whereHas('venue.business', fn ($query) => $query->where('owner_id', $owner->getKey()))
            ->firstOrFail();

        $dispute = $disputes->open($owner, $booking, $data['disputeCategory'], $data['disputeDescription']);

        $this->selectedBookingId = '';
        $this->disputeCategory = '';
        $this->disputeDescription = '';
        unset($this->bookings);
        session()->flash('status', 'Sengketa ' . $dispute->reference_code . ' dibuat dan payout ditahan.');
    }

    public function startResponse(int $disputeId): void
    {
        $owner = $this->requireOwner();
        $dispute = Dispute::query()
            ->whereKey($disputeId)
            ->whereHas('booking.venue.business', fn ($query) => $query->where('owner_id', $owner->getKey()))
            ->firstOrFail();

        abort_unless($dispute->opened_by_role === 'customer' && $dispute->owner_responded_at === null, 403);

        $this->selectedDisputeId = (string) $dispute->getKey();
        $this->ownerResponse = '';
        $this->resetErrorBag();
    }

    public function submitResponse(DisputeService $disputes): void
    {
        $owner = $this->requireOwner();

        $data = $this->validate([
            'selectedDisputeId' => ['required', 'integer', 'exists:disputes,id'],
            'ownerResponse' => ['required', 'string', 'min:20', 'max:5000'],
        ], [
            'ownerResponse.required' => 'Tanggapan wajib diisi.',
            'ownerResponse.min' => 'Tanggapan minimal 20 karakter.',
            'ownerResponse.max' => 'Tanggapan maksimal 5.000 karakter.',
        ]);

        $dispute = Dispute::query()
            ->whereKey((int) $data['selectedDisputeId'])
            ->whereHas('booking.venue.business', fn ($query) => $query->where('owner_id', $owner->getKey()))
            ->firstOrFail();

        $disputes->submitOwnerResponse($owner, $dispute, $data['ownerResponse']);

        $this->selectedDisputeId = '';
        $this->ownerResponse = '';
        unset($this->bookings);
        session()->flash('status', 'Tanggapan sengketa dikirim dan tercatat pada audit log.');
    }
};
?>

<div
    class="site-container owner-core-page owner-booking-page py-7 sm:py-9 lg:py-10"
    x-data="{ cancelOpen: @entangle('cancellationBookingId').live, disputeOpen: @entangle('selectedBookingId').live, responseOpen: @entangle('selectedDisputeId').live }"
    x-effect="document.body.classList.toggle('modal-open', !!cancelOpen || !!disputeOpen || !!responseOpen)"
>
    <header class="owner-core-header">
        <div>
            <p class="owner-core-kicker">Operasional booking</p>
            <h1>Booking venue</h1>
            <p>Kelola jadwal penggunaan, penyelesaian, pembatalan, dan sengketa booking dari satu tempat.</p>
        </div>
    </header>

    @if (session()->has('status'))
        <div role="status" class="status-note status-success mt-5">{{ session('status') }}</div>
    @endif

    <div class="owner-booking-list">
        @forelse ($this->bookings as $booking)
            @php
                $nowUtc = now('UTC');
                $startsUtc = $booking->booking_start?->copy()->utc();
                $endsUtc = $booking->booking_end?->copy()->utc();
                $disputeOpenUntil = $endsUtc?->copy()->addHours(24);
                $canStart = $booking->status === 'confirmed' && $startsUtc && $nowUtc->greaterThanOrEqualTo($startsUtc);
                $canRequestCompletion = $booking->status === 'in_progress' && $endsUtc && $nowUtc->greaterThanOrEqualTo($endsUtc);
                $canCancel = in_array($booking->status, ['confirmed', 'in_progress', 'awaiting_completion'], true);
                $canReport = in_array($booking->status, ['confirmed', 'in_progress', 'awaiting_completion', 'completed'], true)
                    && $endsUtc && $nowUtc->greaterThanOrEqualTo($endsUtc)
                    && $disputeOpenUntil && $nowUtc->lessThanOrEqualTo($disputeOpenUntil)
                    && !$booking->dispute;
                $statusTone = match ($booking->status) {
                    'completed' => 'success',
                    'disputed', 'reconciliation_required' => 'danger',
                    'cancelled_by_customer', 'cancelled_by_owner', 'cancelled_by_admin', 'expired' => 'muted',
                    'pending_payment', 'payment_verifying' => 'warning',
                    default => 'active',
                };
                $startLocal = $booking->booking_start?->copy()->timezone('Asia/Jakarta');
                $endLocal = $booking->booking_end?->copy()->timezone('Asia/Jakarta');
            @endphp

            <article wire:key="owner-booking-{{ $booking->id }}" class="owner-booking-card">
                <div class="owner-booking-date">
                    <span>{{ $startLocal?->translatedFormat('M') }}</span>
                    <strong>{{ $startLocal?->format('d') }}</strong>
                    <small>{{ $startLocal?->format('Y') }}</small>
                </div>

                <div class="owner-booking-body">
                    <div class="owner-booking-head">
                        <div>
                            <span class="owner-core-status is-{{ $statusTone }}">{{ str($booking->status)->replace('_', ' ')->title() }}</span>
                            <h2>{{ $booking->venue?->name ?? 'Venue tidak tersedia' }}</h2>
                            <p>{{ $booking->venue?->business?->name }}</p>
                        </div>
                        <div class="owner-booking-money">
                            <span>Hak bersih Owner</span>
                            <strong>Rp{{ number_format($booking->snapshot_net_owner_entitlement, 0, ',', '.') }}</strong>
                        </div>
                    </div>

                    <div class="owner-booking-meta">
                        <span>{{ $startLocal?->format('H:i') }}–{{ $endLocal?->format('H:i') }} WIB</span>
                        <span>{{ $booking->reference_code }}</span>
                        <span>{{ $booking->customer?->name }} · {{ $booking->customer?->email }}</span>
                    </div>

                    @if ($booking->dispute)
                        <div class="owner-booking-dispute">
                            <div>
                                <strong>Sengketa {{ $booking->dispute->reference_code }}</strong>
                                <span>{{ str($booking->dispute->status)->replace('_', ' ')->title() }}</span>
                            </div>
                            <p>{{ $booking->dispute->category }} — {{ $booking->dispute->description }}</p>
                            <div class="owner-booking-dispute-actions">
                                <a href="{{ route('disputes.evidence', $booking->dispute) }}" class="customer-v2-link">Lihat bukti</a>
                                @if ($booking->dispute->opened_by_role === 'customer' && !$booking->dispute->owner_responded_at && $booking->dispute->owner_response_deadline_at?->isFuture())
                                    <button type="button" wire:click="startResponse({{ $booking->dispute->id }})" class="btn btn-secondary">Tanggapi</button>
                                @elseif ($booking->dispute->owner_responded_at)
                                    <span class="owner-booking-response-note">Tanggapan terkirim {{ $booking->dispute->owner_responded_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</span>
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="owner-booking-actions">
                        @if ($canStart)
                            <button type="button" wire:click="startBooking({{ $booking->id }})" class="btn btn-primary">Mulai booking</button>
                        @endif
                        @if ($canRequestCompletion)
                            <button type="button" wire:click="requestCompletion({{ $booking->id }})" class="btn btn-primary">Ajukan selesai</button>
                        @endif
                        @if ($canCancel)
                            <button type="button" wire:click="startCancellation({{ $booking->id }})" class="btn btn-secondary">Batalkan</button>
                        @endif
                        @if ($canReport)
                            <button type="button" wire:click="startDispute({{ $booking->id }})" class="btn btn-secondary">Laporkan masalah</button>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="owner-core-empty">
                <span>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg>
                </span>
                <h3>Belum ada booking masuk.</h3>
                <p>Booking untuk venue milik usaha kamu akan muncul di sini.</p>
            </div>
        @endforelse
    </div>

    @if ($cancellationBookingId !== '')
        <div class="customer-modal-backdrop" @click.self="$wire.closeCancellation()" @wheel.stop @touchmove.stop>
            <section role="dialog" aria-modal="true" aria-labelledby="owner-cancel-title" class="customer-modal">
                <header class="customer-modal-head">
                    <div><span>Pembatalan Owner</span><h2 id="owner-cancel-title">Batalkan booking?</h2></div>
                    <button type="button" class="venue-v2-modal-close" wire:click="closeCancellation" aria-label="Tutup">
                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M5 5l10 10M15 5 5 15"/></svg>
                    </button>
                </header>
                <div class="customer-modal-body">
                    <p>Pembatalan oleh Owner membutuhkan alasan. Jika pembayaran sudah diterima, refund penuh akan diproses sesuai aturan sistem.</p>
                    <div>
                        <label for="owner-cancel-reason" class="ui-label">Alasan pembatalan</label>
                        <textarea id="owner-cancel-reason" wire:model="cancellationReason" class="ui-input w-full" rows="4" maxlength="2000" required placeholder="Jelaskan alasan pembatalan"></textarea>
                        @error('cancellationReason') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="customer-modal-actions">
                        <button type="button" wire:click="cancelBooking({{ (int) $cancellationBookingId }})" class="btn btn-primary" wire:loading.attr="disabled">Batalkan booking</button>
                        <button type="button" wire:click="closeCancellation" class="btn btn-secondary">Kembali</button>
                    </div>
                </div>
            </section>
        </div>
    @endif

    @if ($selectedBookingId !== '')
        <div class="customer-modal-backdrop" @click.self="$set('selectedBookingId', '')" @wheel.stop @touchmove.stop>
            <section role="dialog" aria-modal="true" aria-labelledby="owner-dispute-title" class="customer-modal">
                <header class="customer-modal-head">
                    <div><span>Laporan masalah</span><h2 id="owner-dispute-title">Laporkan kejadian</h2></div>
                    <button type="button" class="venue-v2-modal-close" wire:click="$set('selectedBookingId', '')" aria-label="Tutup">
                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M5 5l10 10M15 5 5 15"/></svg>
                    </button>
                </header>
                <form wire:submit="openDispute" class="customer-modal-body">
                    <div>
                        <label class="ui-label" for="owner-dispute-category">Kategori</label>
                        <select id="owner-dispute-category" wire:model="disputeCategory" class="ui-input w-full" required>
                            <option value="">Pilih kategori</option>
                            <option value="customer_no_show">Pelanggan tidak hadir</option>
                            <option value="damage_or_loss">Kerusakan atau kehilangan</option>
                            <option value="payment_or_refund">Pembayaran atau refund</option>
                            <option value="other">Lainnya</option>
                        </select>
                        @error('disputeCategory') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="ui-label" for="owner-dispute-description">Deskripsi kejadian</label>
                        <textarea id="owner-dispute-description" wire:model="disputeDescription" class="ui-input w-full" rows="5" minlength="20" maxlength="5000" required></textarea>
                        @error('disputeDescription') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="customer-modal-actions">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Kirim laporan</button>
                        <button type="button" wire:click="$set('selectedBookingId', '')" class="btn btn-secondary">Batal</button>
                    </div>
                </form>
            </section>
        </div>
    @endif

    @if ($selectedDisputeId !== '')
        <div class="customer-modal-backdrop" @click.self="$set('selectedDisputeId', '')" @wheel.stop @touchmove.stop>
            <section role="dialog" aria-modal="true" aria-labelledby="owner-response-title" class="customer-modal">
                <header class="customer-modal-head">
                    <div><span>Tanggapan sengketa</span><h2 id="owner-response-title">Berikan tanggapan</h2></div>
                    <button type="button" class="venue-v2-modal-close" wire:click="$set('selectedDisputeId', '')" aria-label="Tutup">
                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M5 5l10 10M15 5 5 15"/></svg>
                    </button>
                </header>
                <form wire:submit="submitResponse" class="customer-modal-body">
                    <p>Jelaskan kronologi dan bukti pendukung secara ringkas dan akurat.</p>
                    <div>
                        <label class="ui-label" for="owner-dispute-response">Tanggapan Owner</label>
                        <textarea id="owner-dispute-response" wire:model="ownerResponse" class="ui-input w-full" rows="5" minlength="20" maxlength="5000" required></textarea>
                        @error('ownerResponse') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="customer-modal-actions">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Kirim tanggapan</button>
                        <button type="button" wire:click="$set('selectedDisputeId', '')" class="btn btn-secondary">Batal</button>
                    </div>
                </form>
            </section>
        </div>
    @endif
</div>
