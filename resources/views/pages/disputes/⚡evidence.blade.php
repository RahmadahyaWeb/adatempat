<?php

use App\Models\Dispute;
use App\Models\User;
use App\Services\Booking\DisputeEvidenceService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Bukti dan tanggapan sengketa')] class extends Component {
    use WithFileUploads;

    public int $disputeId;
    public string $message = '';
    public $evidenceFile = null;
    public string $evidenceCaption = '';

    public function mount(Dispute $dispute, DisputeEvidenceService $service): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $service->participant($actor, $dispute);
        $this->disputeId = (int) $dispute->getKey();
    }

    #[Computed]
    public function dispute(): Dispute
    {
        $dispute = Dispute::query()
            ->with(['booking.venue.business', 'messages.sender', 'evidence.uploader'])
            ->findOrFail($this->disputeId);

        app(DisputeEvidenceService::class)->participant(auth()->user(), $dispute);

        return $dispute;
    }

    public function sendMessage(DisputeEvidenceService $service): void
    {
        $this->validate([
            'message' => ['required', 'string', 'min:20', 'max:5000'],
        ]);

        $service->respond(auth()->user(), $this->dispute, $this->message);

        $this->reset('message');
        unset($this->dispute);

        session()->flash('status', 'Tanggapan disimpan.');
    }

    public function uploadEvidence(DisputeEvidenceService $service): void
    {
        $this->validate([
            'evidenceFile' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'evidenceCaption' => ['nullable', 'string', 'max:255'],
        ]);

        $service->upload(
            auth()->user(),
            $this->dispute,
            $this->evidenceFile,
            $this->evidenceCaption,
        );

        $this->reset('evidenceFile', 'evidenceCaption');
        unset($this->dispute);

        session()->flash('status', 'Bukti disimpan secara privat.');
    }
};
?>

<div class="site-container space-y-6 py-8">
    <header>
        <p class="eyebrow">Penyelesaian masalah</p>
        <h1 class="mt-3 text-3xl font-extrabold">Bukti dan tanggapan sengketa</h1>
        <p class="mt-2 text-sm text-[var(--ink-soft)]">{{ $this->dispute->reference_code }} · {{ $this->dispute->booking->reference_code }}</p>
    </header>

    @if (session('status'))
        <div role="status" class="status-note status-success">{{ session('status') }}</div>
    @endif

    <section class="ui-panel p-5">
        <h2 class="text-lg font-bold">Kronologi awal</h2>
        <p class="mt-3 whitespace-pre-line text-sm">{{ $this->dispute->description }}</p>
    </section>

    <section class="ui-panel p-5">
        <h2 class="text-lg font-bold">Riwayat tanggapan</h2>

        <div class="mt-4 space-y-3">
            @forelse ($this->dispute->messages as $entry)
                <article wire:key="message-{{ $entry->id }}" class="rounded-xl border border-[var(--border)] p-4">
                    <p class="text-sm font-semibold">{{ $entry->sender->name }} · {{ $entry->created_at->format('d/m/Y H:i') }} UTC</p>
                    <p class="mt-2 whitespace-pre-line text-sm">{{ $entry->message }}</p>
                </article>
            @empty
                <p class="text-sm text-[var(--ink-soft)]">Belum ada tanggapan tambahan.</p>
            @endforelse
        </div>

        @if (in_array($this->dispute->status, \App\Models\Dispute::OPEN_STATUSES, true))
            <form wire:submit="sendMessage" class="mt-5 space-y-3">
                <label class="ui-label" for="dispute-message">Tanggapan tambahan</label>
                <textarea id="dispute-message" wire:model="message" class="ui-input w-full" rows="4" minlength="20" maxlength="5000" required></textarea>
                @error('message')
                    <p role="alert" class="ui-error">{{ $message }}</p>
                @enderror
                <button class="btn btn-primary" type="submit">Kirim tanggapan</button>
            </form>
        @endif
    </section>

    <section class="ui-panel p-5">
        <h2 class="text-lg font-bold">Bukti privat</h2>

        <div class="mt-4 space-y-2">
            @forelse ($this->dispute->evidence as $entry)
                <a class="block text-sm font-semibold underline" href="{{ route('disputes.evidence.download', $entry) }}">{{ $entry->original_name }}</a>
            @empty
                <p class="text-sm text-[var(--ink-soft)]">Belum ada berkas bukti.</p>
            @endforelse
        </div>

        @if (in_array($this->dispute->status, \App\Models\Dispute::OPEN_STATUSES, true))
            <form wire:submit="uploadEvidence" class="mt-5 space-y-3">
                <label class="ui-label" for="evidence-file">JPG, PNG, atau WEBP (maksimal 5 MB)</label>
                <input id="evidence-file" type="file" wire:model="evidenceFile" class="ui-input w-full" accept=".jpg,.jpeg,.png,.webp" required>
                @error('evidenceFile')
                    <p role="alert" class="ui-error">{{ $message }}</p>
                @enderror

                <input type="text" wire:model="evidenceCaption" class="ui-input w-full" maxlength="255" placeholder="Keterangan bukti (opsional)">

                <button class="btn btn-secondary" type="submit">Unggah bukti</button>
            </form>
        @endif
    </section>
</div>
