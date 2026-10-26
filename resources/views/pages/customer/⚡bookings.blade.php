<?php

use App\Models\Booking;
use App\Models\Dispute;
use App\Models\User;
use App\Services\Booking\BookingCancellationService;
use App\Services\Booking\BookingCompletionService;
use App\Services\Booking\DisputeService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Booking saya')] class extends Component {
    public string $cancellationReason = '';

    public string $selectedBookingId = '';

    public string $disputeCategory = '';

    public string $disputeDescription = '';

    #[Computed]
    public function bookings()
    {
        $customer = $this->requireCustomer();

        return Booking::query()
            ->where('customer_id', $customer->getKey())
            ->with(['venue:id,name,business_id', 'venue.business:id,name', 'dispute'])
            ->withCount('refunds')
            ->orderByDesc('booking_start')
            ->limit(100)
            ->get();
    }

    private function requireCustomer(): User
    {
        $customer = auth()->user();

        abort_unless($customer instanceof User && $customer->isCustomer() && $customer->status === 'active' && $customer->hasVerifiedEmail(), 403);

        return $customer;
    }

    public function cancelBooking(int $bookingId, BookingCancellationService $cancellations): void
    {
        $customer = $this->requireCustomer();
        $booking = Booking::query()->where('customer_id', $customer->getKey())->whereKey($bookingId)->firstOrFail();

        $reason = trim($this->cancellationReason);
        $cancellations->cancelByCustomer($customer, $booking, $reason !== '' ? $reason : null);

        $this->cancellationReason = '';
        unset($this->bookings);
        session()->flash('status', 'Booking dibatalkan. Jika refund bernilai positif, slot tetap ditahan sampai gateway mengonfirmasi refund berhasil.');
    }

    public function confirmCompletion(int $bookingId, BookingCompletionService $completion): void
    {
        $customer = $this->requireCustomer();
        $booking = Booking::query()->where('customer_id', $customer->getKey())->whereKey($bookingId)->firstOrFail();

        $completion->confirmCompletionByCustomer($customer, $booking);

        unset($this->bookings);
        session()->flash('status', 'Penyelesaian booking dikonfirmasi. Jendela laporan masalah 24 jam setelah waktu akhir tetap berlaku.');
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
        $this->requireCustomer();
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

        $dispute = $disputes->open($customer, $booking, $data['disputeCategory'], $data['disputeDescription']);

        $this->cancelDispute();
        unset($this->bookings);
        session()->flash('status', 'Sengketa ' . $dispute->reference_code . ' dibuat. Payout ditahan sampai Super Admin menyelesaikan peninjauan.');
    }
};
?>

<div class="site-container space-y-6 py-6 sm:py-10">
    <header>
        <p class="eyebrow"><span class="eyebrow-dot"></span> Akun pelanggan</p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-[-0.05em] sm:text-4xl">Booking saya</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-[var(--ink-soft)]">Pantau booking, konfirmasi penyelesaian, lakukan pembatalan sesuai batas waktu, atau laporkan masalah dalam 24 jam setelah waktu booking berakhir.</p>
    </header>

    @if (session()->has('status'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    @error('booking') <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $message }}</div> @enderror
    @error('payment') <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $message }}</div> @enderror

    <div class="space-y-4">
        @forelse ($this->bookings as $booking)
            @php
                $nowUtc = now('UTC');
                $startsUtc = $booking->booking_start?->copy()->utc();
                $endsUtc = $booking->booking_end?->copy()->utc();
                $disputeOpenUntil = $endsUtc?->copy()->addHours(24);
                $canCancel = $booking->status === 'confirmed' && $startsUtc && $startsUtc->greaterThan($nowUtc->copy()->addMinutes(60));
                $canReport = in_array($booking->status, ['confirmed', 'in_progress', 'awaiting_completion', 'completed'], true)
                    && $endsUtc && $nowUtc->greaterThanOrEqualTo($endsUtc)
                    && $disputeOpenUntil && $nowUtc->lessThanOrEqualTo($disputeOpenUntil)
                    && !$booking->dispute;
            @endphp
            <article wire:key="customer-booking-{{ $booking->id }}" class="ui-panel p-5 sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-extrabold">{{ $booking->venue?->name ?? 'Venue tidak tersedia' }}</h2>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $booking->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($booking->status === 'disputed' ? 'bg-red-100 text-red-800' : (str_starts_with($booking->status, 'cancelled') ? 'bg-slate-100 text-slate-700' : 'bg-sky-100 text-sky-800')) }}">{{ str($booking->status)->replace('_', ' ')->title() }}</span>
                        </div>
                        <p class="mt-2 text-sm text-[var(--ink-soft)]">{{ $booking->venue?->business?->name }}</p>
                        <p class="mt-2 text-sm">Kode booking: <span class="font-semibold">{{ $booking->reference_code }}</span></p>
                        <p class="mt-1 text-sm">{{ $booking->booking_start?->timezone('Asia/Jakarta')->format('d M Y H:i') }} – {{ $booking->booking_end?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>
                        <p class="mt-2 text-sm text-[var(--ink-soft)]">Dibayar: Rp{{ number_format($booking->snapshot_actual_paid_amount, 0, ',', '.') }}</p>
                        @if ($booking->dispute)
                            <p class="mt-3 text-sm font-semibold text-red-800">Sengketa {{ $booking->dispute->reference_code }} · {{ str($booking->dispute->status)->replace('_', ' ')->title() }}</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 flex-col gap-2 sm:flex-row lg:flex-col">
                        @if ($canCancel)
                            <button type="button" wire:click="cancelBooking({{ $booking->id }})" wire:confirm="Batalkan booking ini? Persentase refund mengikuti snapshot kebijakan booking." class="btn btn-secondary">Batalkan booking</button>
                        @endif
                        @if ($booking->status === 'awaiting_completion')
                            <button type="button" wire:click="confirmCompletion({{ $booking->id }})" wire:confirm="Konfirmasi bahwa penggunaan venue telah selesai?" class="btn btn-primary">Konfirmasi selesai</button>
                        @endif
                        @if ($canReport)
                            <button type="button" wire:click="startDispute({{ $booking->id }})" class="btn btn-secondary">Laporkan masalah</button>
                        @endif
                    </div>
                </div>

                @if ($canCancel)
                    <div class="mt-4 border-t border-[var(--border)] pt-4">
                        <label class="ui-label" for="cancel-reason-{{ $booking->id }}">Alasan pembatalan (opsional)</label>
                        <input id="cancel-reason-{{ $booking->id }}" wire:model="cancellationReason" class="ui-input w-full" maxlength="2000" placeholder="Alasan pembatalan">
                    </div>
                @endif

                @if ($selectedBookingId === (string) $booking->id)
                    <form wire:submit="openDispute" class="mt-5 rounded-xl bg-[var(--surface-soft)] p-4 sm:p-5">
                        <h3 class="font-bold">Laporkan masalah</h3>
                        <p class="mt-1 text-sm leading-6 text-[var(--ink-soft)]">Laporan akan mengubah status booking menjadi disputed dan menahan payout. Pastikan deskripsi akurat; keputusan akhir dilakukan Super Admin.</p>
                        <label class="ui-label mt-4" for="dispute-category">Kategori</label>
                        <select id="dispute-category" wire:model="disputeCategory" class="ui-input w-full" required>
                            <option value="">Pilih kategori</option>
                            <option value="venue_not_available">Venue tidak tersedia</option>
                            <option value="venue_condition">Kondisi venue bermasalah</option>
                            <option value="service_not_delivered">Layanan tidak diberikan</option>
                            <option value="payment_or_refund">Pembayaran atau refund</option>
                            <option value="other">Lainnya</option>
                        </select>
                        @error('disputeCategory') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        <label class="ui-label mt-4" for="dispute-description">Deskripsi kejadian</label>
                        <textarea id="dispute-description" wire:model="disputeDescription" class="ui-input w-full" rows="4" minlength="20" maxlength="5000" required placeholder="Jelaskan kronologi, waktu, dan dampak masalah"></textarea>
                        @error('disputeDescription') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        @error('selectedBookingId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Kirim laporan</button>
                            <button type="button" wire:click="cancelDispute" class="btn btn-secondary">Batal</button>
                        </div>
                    </form>
                @endif
            </article>
        @empty
            <div class="ui-panel p-8 text-center">
                <h2 class="text-lg font-bold">Belum ada booking</h2>
                <p class="mt-2 text-sm text-[var(--ink-soft)]">Booking yang kamu buat akan muncul di sini.</p>
            </div>
        @endforelse
    </div>
</div>
