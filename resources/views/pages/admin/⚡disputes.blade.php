<?php

use App\Models\Dispute;
use App\Services\Disputes\DisputeResolutionService;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $selectedDisputeId = null;
    public string $resolutionType = 'full_owner_payout';
    public string $customerRefundAmount = '0';
    public string $ownerPayoutAmount = '0';
    public string $resolutionNotes = '';

    #[Computed]
    public function openDisputes()
    {
        return Dispute::query()
            ->with(['booking.venue.business', 'opener', 'respondent', 'messages.sender', 'evidence'])
            ->whereIn('status', [Dispute::STATUS_AWAITING_RESPONSE, Dispute::STATUS_UNDER_REVIEW])
            ->orderBy('response_deadline')
            ->limit(100)
            ->get();
    }

    public function selectDispute(int $id): void
    {
        $dispute = Dispute::query()->with('booking')->findOrFail($id);
        abort_unless(in_array($dispute->status, [Dispute::STATUS_AWAITING_RESPONSE, Dispute::STATUS_UNDER_REVIEW], true), 404);

        $this->selectedDisputeId = $id;
        $this->resolutionType = Dispute::RESOLUTION_FULL_OWNER_PAYOUT;
        $this->customerRefundAmount = '0';
        $this->ownerPayoutAmount = (string) $dispute->booking->snapshot_net_owner_entitlement;
        $this->resolutionNotes = '';
        $this->resetValidation();
    }

    public function resolve(DisputeResolutionService $service): void
    {
        $validated = $this->validate([
            'selectedDisputeId' => ['required', 'integer', 'exists:disputes,id'],
            'resolutionType' => ['required', 'in:full_owner_payout,full_customer_refund,partial_split'],
            'customerRefundAmount' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'ownerPayoutAmount' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'resolutionNotes' => ['required', 'string', 'max:5000'],
        ]);

        $service->resolve(
            Dispute::query()->findOrFail($validated['selectedDisputeId']),
            auth()->user(),
            $validated['resolutionType'],
            (int) $validated['customerRefundAmount'],
            (int) $validated['ownerPayoutAmount'],
            $validated['resolutionNotes'],
        );

        $this->reset(['selectedDisputeId', 'resolutionNotes', 'customerRefundAmount', 'ownerPayoutAmount']);
        $this->resolutionType = Dispute::RESOLUTION_FULL_OWNER_PAYOUT;
        $this->resetValidation();
        unset($this->openDisputes);
        session()->flash('status', 'Keputusan sengketa disimpan dan audit log dicatat.');
    }
};
?>
<div class="site-container py-8 sm:py-12">
    <header class="max-w-3xl">
        <p class="eyebrow"><span class="eyebrow-dot"></span> Super Admin / Resolusi</p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-[-0.05em]">Sengketa booking</h1>
        <p class="mt-3 text-sm leading-6 text-[var(--muted)]">Tinjau tanggapan dan bukti sebelum menetapkan refund pelanggan serta payout Owner. Keputusan bersifat final dan diaudit.</p>
    </header>
    @if (session('status')) <div role="status" class="status-note status-success mt-5">{{ session('status') }}</div> @endif

    <div class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(340px,.8fr)]">
        <section class="ui-panel p-5">
            <h2 class="text-lg font-extrabold">Kasus terbuka</h2>
            <div class="mt-4 space-y-3">
                @forelse ($this->openDisputes as $dispute)
                    <article wire:key="admin-dispute-{{ $dispute->id }}" class="rounded-xl border border-[var(--border)] p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-extrabold">{{ $dispute->reference_code }}</p>
                                <p class="mt-1 text-sm text-[var(--ink-soft)]">Booking {{ $dispute->booking->reference_code }} · {{ $dispute->booking->venue->name }}</p>
                            </div>
                            <span class="rounded-full bg-[var(--surface-soft)] px-3 py-1 text-xs font-bold">{{ str_replace('_', ' ', $dispute->status) }}</span>
                        </div>
                        <p class="mt-2 text-sm">{{ $dispute->opener->name }} vs {{ $dispute->respondent->name }}</p>
                        <p class="mt-1 text-xs text-[var(--muted)]">Deadline respons: {{ $dispute->response_deadline->format('d/m/Y H:i') }} UTC</p>
                        <p class="mt-3 line-clamp-3 whitespace-pre-line text-sm leading-6">{{ $dispute->description }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" wire:click="selectDispute({{ $dispute->id }})" class="btn btn-primary">Tinjau / putuskan</button>
                            @foreach ($dispute->evidence as $evidence)
                                <a href="{{ route('disputes.evidence.download-admin', ['evidence' => $evidence->id]) }}" class="btn btn-secondary">Bukti: {{ $evidence->original_name }}</a>
                            @endforeach
                        </div>
                        <details class="mt-3">
                            <summary class="cursor-pointer text-sm font-bold">Tanggapan ({{ $dispute->messages->count() }})</summary>
                            <div class="mt-3 space-y-2">
                                @foreach ($dispute->messages as $message)
                                    <div class="rounded-lg bg-[var(--surface-soft)] p-3" wire:key="admin-dispute-message-{{ $message->id }}">
                                        <p class="text-xs font-bold">{{ $message->sender->name }} · {{ $message->created_at->format('d/m/Y H:i') }} UTC</p>
                                        <p class="mt-1 whitespace-pre-line text-sm">{{ $message->message }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    </article>
                @empty
                    <p class="text-sm text-[var(--muted)]">Tidak ada sengketa yang menunggu keputusan.</p>
                @endforelse
            </div>
        </section>

        <section class="ui-panel h-fit p-5">
            <h2 class="text-lg font-extrabold">Keputusan final</h2>
            @if ($selectedDisputeId)
                @php($selected = $this->openDisputes->firstWhere('id', $selectedDisputeId))
                @if ($selected)
                    <p class="mt-2 text-sm text-[var(--muted)]">{{ $selected->reference_code }} · {{ $selected->booking->reference_code }}</p>
                    <form wire:submit="resolve" class="mt-4 space-y-4">
                        <div>
                            <label class="ui-label" for="resolution-type">Hasil resolusi</label>
                            <select id="resolution-type" wire:model.live="resolutionType" class="ui-input">
                                <option value="full_owner_payout">Payout Owner penuh</option>
                                <option value="full_customer_refund">Refund pelanggan penuh</option>
                                <option value="partial_split">Pembagian sebagian</option>
                            </select>
                        </div>
                        @if ($resolutionType === 'partial_split')
                            <div>
                                <label class="ui-label" for="resolution-refund">Refund pelanggan (Rp)</label>
                                <input id="resolution-refund" type="number" min="0" wire:model="customerRefundAmount" class="ui-input" required>
                            </div>
                            <div>
                                <label class="ui-label" for="resolution-payout">Payout Owner (Rp)</label>
                                <input id="resolution-payout" type="number" min="0" wire:model="ownerPayoutAmount" class="ui-input" required>
                            </div>
                        @else
                            <p class="rounded-lg bg-[var(--surface-soft)] p-3 text-sm text-[var(--muted)]">
                                @if ($resolutionType === 'full_owner_payout')
                                    Nominal akan memakai snapshot net entitlement Owner.
                                @else
                                    Nominal refund akan memakai actual paid amount pada snapshot booking.
                                @endif
                            </p>
                        @endif
                        <div>
                            <label class="ui-label" for="resolution-notes">Alasan dan ringkasan keputusan</label>
                            <textarea id="resolution-notes" wire:model="resolutionNotes" rows="5" maxlength="5000" class="ui-input" required></textarea>
                        </div>
                        @error('resolutionNotes') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        @error('resolution') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        @error('customerRefundAmount') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        @error('ownerPayoutAmount') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full">Simpan keputusan final</button>
                        <p class="text-xs leading-5 text-[var(--muted)]">Keputusan final mengubah status booking, dapat membuat kewajiban refund, dan menentukan payout Owner. Pastikan bukti sudah ditinjau.</p>
                    </form>
                @else
                    <p class="mt-3 text-sm text-[var(--muted)]">Kasus telah berubah atau ditutup. Muat ulang daftar untuk melihat status terbaru.</p>
                @endif
            @else
                <p class="mt-3 text-sm text-[var(--muted)]">Pilih kasus di sebelah kiri untuk meninjau bukti dan menetapkan keputusan.</p>
            @endif
        </section>
    </div>
</div>
