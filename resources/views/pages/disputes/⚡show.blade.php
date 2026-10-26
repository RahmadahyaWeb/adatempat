<?php

use App\Models\Booking;
use App\Models\Dispute;
use App\Services\Disputes\DisputeService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Sengketa booking')] class extends Component {
    use WithFileUploads;

    public int $bookingId;
    public string $reasonType = 'service_not_provided';
    public string $description = '';
    public string $message = '';
    public $evidenceFile = null;
    public string $evidenceCaption = '';

    public function mount(Booking $booking): void
    {
        $actor = auth()->user();
        $record = Booking::query()->with('venue.business')->whereKey($booking->getKey())->firstOrFail();
        $isCustomer = (int) $record->customer_id === (int) $actor->getKey();
        $isOwner = $actor->isOwner() && (int) $record->venue->business->owner_id === (int) $actor->getKey();

        abort_unless($actor->status === 'active' && $actor->email_verified_at !== null && ($isCustomer || $isOwner), 404);

        $this->bookingId = (int) $record->getKey();
    }

    #[Computed]
    public function currentBooking(): Booking
    {
        return Booking::query()
            ->with(['venue.business', 'dispute.opener', 'dispute.respondent', 'dispute.messages.sender', 'dispute.evidence.uploader'])
            ->whereKey($this->bookingId)
            ->firstOrFail();
    }

    #[Computed]
    public function currentDispute(): ?Dispute
    {
        return $this->currentBooking->dispute;
    }

    public function openDispute(DisputeService $service): void
    {
        $validated = $this->validate([
            'reasonType' => ['required', 'in:service_not_provided,venue_condition,booking_mismatch,payment_or_refund,other'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        $service->open(
            auth()->user(),
            $this->currentBooking,
            $validated['reasonType'],
            $validated['description'],
        );

        $this->reset(['description']);
        $this->resetValidation();
        unset($this->currentBooking, $this->currentDispute);
        session()->flash('status', 'Sengketa dibuka. Pihak terkait memiliki waktu 48 jam untuk memberikan tanggapan.');
    }

    public function sendMessage(DisputeService $service): void
    {
        $this->validate(['message' => ['required', 'string', 'max:5000']]);
        abort_unless($this->currentDispute, 404);

        $service->respond(auth()->user(), $this->currentDispute, $this->message);

        $this->reset('message');
        $this->resetValidation();
        unset($this->currentBooking, $this->currentDispute);
    }

    public function uploadEvidence(DisputeService $service): void
    {
        $this->validate([
            'evidenceFile' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
            'evidenceCaption' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless($this->currentDispute, 404);
        $service->addEvidence(auth()->user(), $this->currentDispute, $this->evidenceFile, $this->evidenceCaption);

        $this->reset(['evidenceFile', 'evidenceCaption']);
        $this->resetValidation();
        unset($this->currentBooking, $this->currentDispute);
        session()->flash('status', 'Bukti berhasil diunggah ke penyimpanan privat.');
    }
};
?>
<div class="site-container py-7 sm:py-10 lg:py-12">
    @php($booking = $this->currentBooking)
    <a href="{{ url()->previous() }}" class="text-sm font-semibold text-[var(--ink-soft)] underline-offset-4 hover:underline">Kembali</a>
    <header class="mt-5 max-w-3xl">
        <p class="eyebrow"><span class="eyebrow-dot"></span> Penyelesaian masalah</p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-[-0.055em]">Sengketa booking</h1>
        <p class="mt-2 text-sm text-[var(--ink-soft)]">{{ $booking->reference_code }} · {{ $booking->venue->name }}</p>
        <p class="mt-3 text-sm leading-6 text-[var(--muted)]">Sengketa hanya dapat dibuka selama jendela konfirmasi 24 jam. Bukti tersimpan privat dan hanya dapat diunduh pihak terkait atau Super Admin.</p>
    </header>

    @if (session('status')) <div role="status" class="status-note status-success mt-5">{{ session('status') }}</div> @endif

    @if ($this->currentDispute)
        @php($dispute = $this->currentDispute)
        <section class="ui-panel mt-6 p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-extrabold">{{ $dispute->reference_code }}</h2>
                    <p class="mt-1 text-sm text-[var(--muted)]">Tanggapan ditargetkan sebelum {{ $dispute->response_deadline->format('d/m/Y H:i') }} UTC.</p>
                </div>
                <span class="rounded-full bg-[var(--surface-soft)] px-3 py-1 text-xs font-bold">{{ str_replace('_', ' ', $dispute->status) }}</span>
            </div>
            <p class="mt-4 whitespace-pre-line text-sm leading-6">{{ $dispute->description }}</p>
        </section>

        <section class="ui-panel mt-5 p-5 sm:p-6">
            <h2 class="text-lg font-extrabold">Riwayat tanggapan</h2>
            <div class="mt-4 space-y-3">
                @foreach ($dispute->messages as $entry)
                    <article wire:key="dispute-message-{{ $entry->id }}" class="rounded-xl border border-[var(--border)] p-4">
                        <div class="flex flex-wrap justify-between gap-2">
                            <p class="text-sm font-bold">{{ $entry->sender->name }}</p>
                            <time class="text-xs text-[var(--muted)]">{{ $entry->created_at->format('d/m/Y H:i') }} UTC</time>
                        </div>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $entry->message }}</p>
                    </article>
                @endforeach
            </div>
            @if (!in_array($dispute->status, ['resolved', 'rejected'], true))
                <form wire:submit="sendMessage" class="mt-5 space-y-3">
                    <label class="ui-label" for="dispute-message">Tanggapan</label>
                    <textarea id="dispute-message" wire:model="message" rows="3" maxlength="5000" class="ui-input" required></textarea>
                    @error('message') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    <button type="submit" class="btn btn-primary">Kirim tanggapan</button>
                </form>
            @endif
        </section>

        <section class="ui-panel mt-5 p-5 sm:p-6">
            <h2 class="text-lg font-extrabold">Bukti pendukung</h2>
            <div class="mt-4 space-y-2">
                @forelse ($dispute->evidence as $evidence)
                    <a class="block rounded-lg border border-[var(--border)] p-3 text-sm font-semibold underline" href="{{ route('disputes.evidence.download', ['evidence' => $evidence->id]) }}">{{ $evidence->original_name }}</a>
                @empty
                    <p class="text-sm text-[var(--muted)]">Belum ada bukti yang diunggah.</p>
                @endforelse
            </div>
            @if (!in_array($dispute->status, ['resolved', 'rejected'], true))
                <form wire:submit="uploadEvidence" class="mt-5 space-y-3">
                    <label class="ui-label" for="dispute-evidence">Unggah bukti (JPG, PNG, WEBP, PDF; maks. 10 MB)</label>
                    <input id="dispute-evidence" type="file" wire:model="evidenceFile" accept=".jpg,.jpeg,.png,.webp,.pdf" class="ui-input">
                    @error('evidenceFile') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    <input type="text" wire:model="evidenceCaption" maxlength="255" class="ui-input" placeholder="Keterangan bukti (opsional)">
                    <button type="submit" class="btn btn-secondary">Unggah bukti</button>
                </form>
            @endif
        </section>
    @elseif ($booking->status === 'awaiting_completion' && $booking->completion_window_ends_at && $booking->completion_window_ends_at->isFuture())
        <section class="ui-panel mt-6 p-5 sm:p-6">
            <h2 class="text-lg font-extrabold">Buka sengketa</h2>
            <p class="mt-2 text-sm leading-6 text-[var(--muted)]">Sengketa menahan proses penyelesaian dan payout sampai Super Admin mengambil keputusan.</p>
            <form wire:submit="openDispute" class="mt-5 space-y-4">
                <div>
                    <label class="ui-label" for="dispute-reason">Kategori</label>
                    <select id="dispute-reason" wire:model="reasonType" class="ui-input">
                        <option value="service_not_provided">Layanan tidak diberikan</option>
                        <option value="venue_condition">Kondisi venue</option>
                        <option value="booking_mismatch">Tidak sesuai booking</option>
                        <option value="payment_or_refund">Pembayaran atau refund</option>
                        <option value="other">Lainnya</option>
                    </select>
                </div>
                <div>
                    <label class="ui-label" for="dispute-description">Jelaskan masalah</label>
                    <textarea id="dispute-description" wire:model="description" rows="5" maxlength="5000" class="ui-input" required></textarea>
                    @error('description') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn-primary">Buka sengketa</button>
            </form>
        </section>
    @else
        <div class="status-note mt-6">Booking ini tidak berada dalam jendela yang mengizinkan pembukaan sengketa.</div>
    @endif
</div>
