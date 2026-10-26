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
        $this->cancellationReason = '';
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

<div class="site-container space-y-6 py-6 sm:py-10">
    <header>
        <p class="eyebrow"><span class="eyebrow-dot"></span> Ruang Owner / Operasional</p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-[-0.05em] sm:text-4xl">Booking venue</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-[var(--ink-soft)]">Kelola booking masuk, catat penggunaan venue, ajukan penyelesaian, dan tanggapi sengketa sesuai batas waktu.</p>
    </header>

    @if (session()->has('status'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <div class="space-y-4">
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
            @endphp
            <article wire:key="owner-booking-{{ $booking->id }}" class="ui-panel p-5 sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-extrabold">{{ $booking->venue?->name ?? 'Venue tidak tersedia' }}</h2>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $booking->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($booking->status === 'disputed' ? 'bg-red-100 text-red-800' : (str_starts_with($booking->status, 'cancelled') ? 'bg-slate-100 text-slate-700' : 'bg-sky-100 text-sky-800')) }}">{{ str($booking->status)->replace('_', ' ')->title() }}</span>
                        </div>
                        <p class="mt-2 text-sm text-[var(--ink-soft)]">{{ $booking->venue?->business?->name }}</p>
                        <p class="mt-2 text-sm">Kode booking: <span class="font-semibold">{{ $booking->reference_code }}</span></p>
                        <p class="mt-1 text-sm">{{ $booking->booking_start?->timezone('Asia/Jakarta')->format('d M Y H:i') }} – {{ $booking->booking_end?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>
                        <p class="mt-2 text-sm">Pelanggan: {{ $booking->customer?->name }} · {{ $booking->customer?->email }}</p>
                        <p class="mt-1 text-sm text-[var(--ink-soft)]">Hak bersih Owner normal: Rp{{ number_format($booking->snapshot_net_owner_entitlement, 0, ',', '.') }}</p>
                        @if ($booking->dispute)
                            <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3">
                                <p class="font-semibold text-amber-950">Sengketa {{ $booking->dispute->reference_code }} · {{ str($booking->dispute->status)->replace('_', ' ')->title() }}</p>
                                <p class="mt-1 text-sm text-amber-900">{{ $booking->dispute->category }} — {{ $booking->dispute->description }}</p>
                                @if ($booking->dispute->opened_by_role === 'customer' && !$booking->dispute->owner_responded_at && $booking->dispute->owner_response_deadline_at?->isFuture())
                                    <button type="button" wire:click="startResponse({{ $booking->dispute->id }})" class="btn btn-secondary mt-3">Tanggapi sengketa</button>
                                @elseif ($booking->dispute->owner_responded_at)
                                    <p class="mt-2 text-xs text-amber-900">Tanggapan terkirim {{ $booking->dispute->owner_responded_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB.</p>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="flex shrink-0 flex-col gap-2 sm:flex-row lg:flex-col">
                        @if ($canStart)
                            <button type="button" wire:click="startBooking({{ $booking->id }})" class="btn btn-primary">Mulai booking</button>
                        @endif
                        @if ($canRequestCompletion)
                            <button type="button" wire:click="requestCompletion({{ $booking->id }})" class="btn btn-primary">Ajukan selesai</button>
                        @endif
                        @if ($canCancel)
                            <button type="button" wire:click="cancelBooking({{ $booking->id }})" class="btn btn-secondary">Batalkan booking</button>
                        @endif
                        @if ($canReport)
                            <button type="button" wire:click="startDispute({{ $booking->id }})" class="btn btn-secondary">Laporkan masalah</button>
                        @endif
                    </div>
                </div>

                @if ($canCancel)
                    <div class="mt-4 border-t border-[var(--border)] pt-4">
                        <label class="ui-label" for="owner-cancel-reason-{{ $booking->id }}">Alasan pembatalan (wajib untuk Owner)</label>
                        <input id="owner-cancel-reason-{{ $booking->id }}" wire:model="cancellationReason" class="ui-input w-full" maxlength="2000" placeholder="Jelaskan alasan pembatalan">
                        @error('cancellationReason') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                @endif

                @if ($selectedBookingId === (string) $booking->id)
                    <form wire:submit="openDispute" class="mt-5 rounded-xl bg-[var(--surface-soft)] p-4 sm:p-5">
                        <h3 class="font-bold">Laporkan masalah</h3>
                        <label class="ui-label mt-4" for="owner-dispute-category">Kategori</label>
                        <select id="owner-dispute-category" wire:model="disputeCategory" class="ui-input w-full" required>
                            <option value="">Pilih kategori</option>
                            <option value="customer_no_show">Pelanggan tidak hadir</option>
                            <option value="damage_or_loss">Kerusakan atau kehilangan</option>
                            <option value="payment_or_refund">Pembayaran atau refund</option>
                            <option value="other">Lainnya</option>
                        </select>
                        @error('disputeCategory') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        <label class="ui-label mt-4" for="owner-dispute-description">Deskripsi kejadian</label>
                        <textarea id="owner-dispute-description" wire:model="disputeDescription" class="ui-input w-full" rows="4" minlength="20" maxlength="5000" required></textarea>
                        @error('disputeDescription') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        @error('selectedBookingId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Kirim laporan</button>
                            <button type="button" wire:click="$set('selectedBookingId', '')" class="btn btn-secondary">Batal</button>
                        </div>
                    </form>
                @endif

                @if ($selectedDisputeId === (string) ($booking->dispute?->id ?? ''))
                    <form wire:submit="submitResponse" class="mt-5 rounded-xl bg-[var(--surface-soft)] p-4 sm:p-5">
                        <h3 class="font-bold">Tanggapan Owner</h3>
                        <label class="ui-label mt-4" for="owner-dispute-response">Kronologi dan bukti pendukung</label>
                        <textarea id="owner-dispute-response" wire:model="ownerResponse" class="ui-input w-full" rows="4" minlength="20" maxlength="5000" required></textarea>
                        @error('ownerResponse') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        @error('selectedDisputeId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Kirim tanggapan</button>
                            <button type="button" wire:click="$set('selectedDisputeId', '')" class="btn btn-secondary">Batal</button>
                        </div>
                    </form>
                @endif
            </article>
        @empty
            <div class="ui-panel p-8 text-center">
                <h2 class="text-lg font-bold">Belum ada booking masuk</h2>
                <p class="mt-2 text-sm text-[var(--ink-soft)]">Booking untuk venue milik usaha ini akan muncul di sini.</p>
            </div>
        @endforelse
    </div>
</div>
