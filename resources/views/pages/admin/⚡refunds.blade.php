<?php

use App\Models\Refund;
use App\Models\User;
use App\Services\Payments\RefundService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Refund pelanggan')] class extends Component {
    public string $search = '';

    public string $statusFilter = '';

    public string $selectedRefundId = '';

    public string $transferReference = '';

    public string $manualReason = '';

    public bool $verifiedNoGatewayRefund = false;

    #[Computed]
    public function refunds()
    {
        $this->requireSuperAdmin();

        return Refund::query()
            ->with([
                'recipient:id,name,email',
                'booking:id,reference_code,venue_id,customer_id,status,snapshot_actual_paid_amount',
                'booking.venue:id,name,business_id',
                'payment:id,payable_type,payable_id,amount,status,gateway_provider,gateway_transaction_id',
                'processor:id,name',
            ])
            ->when(trim($this->search) !== '', function ($query): void {
                $term = trim($this->search);

                $query->where(function ($nested) use ($term): void {
                    $nested->where('reference_code', 'like', '%' . $term . '%')
                        ->orWhere('gateway_refund_id', 'like', '%' . $term . '%')
                        ->orWhere('manual_transfer_reference', 'like', '%' . $term . '%')
                        ->orWhereHas('recipient', fn ($user) => $user->where('name', 'like', '%' . $term . '%')->orWhere('email', 'like', '%' . $term . '%'))
                        ->orWhereHas('booking', fn ($booking) => $booking->where('reference_code', 'like', '%' . $term . '%'));
                });
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderByRaw("CASE status WHEN 'failed' THEN 0 WHEN 'requested' THEN 1 WHEN 'approved' THEN 2 WHEN 'processing' THEN 3 WHEN 'under_review' THEN 4 ELSE 5 END")
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function summary(): array
    {
        $this->requireSuperAdmin();

        return [
            'open_count' => Refund::query()->whereIn('status', Refund::OPEN_STATUSES)->count(),
            'failed_count' => Refund::query()->where('status', Refund::STATUS_FAILED)->count(),
            'succeeded_amount' => (int) Refund::query()->where('status', Refund::STATUS_SUCCEEDED)->sum('amount'),
        ];
    }

    private function requireSuperAdmin(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User && $user->isSuperAdmin() && $user->status === 'active', 403);

        return $user;
    }

    public function retryRefund(int $refundId, RefundService $refundService): void
    {
        $this->requireSuperAdmin();

        $refund = Refund::query()->findOrFail($refundId);

        abort_unless(in_array($refund->status, [
            Refund::STATUS_REQUESTED,
            Refund::STATUS_APPROVED,
            Refund::STATUS_FAILED,
        ], true), 403);

        $updated = $refundService->submit($refund);

        unset($this->refunds, $this->summary);
        session()->flash('status', 'Percobaan refund dijalankan. Status saat ini: ' . $updated->status . '. Status processing belum berarti dana sudah masuk.');
    }

    public function startManualTransfer(int $refundId): void
    {
        $this->requireSuperAdmin();

        $refund = Refund::query()->findOrFail($refundId);

        abort_unless(in_array($refund->status, [
            Refund::STATUS_REQUESTED,
            Refund::STATUS_APPROVED,
            Refund::STATUS_FAILED,
        ], true), 403);

        $this->selectedRefundId = (string) $refund->getKey();
        $this->transferReference = '';
        $this->manualReason = '';
        $this->verifiedNoGatewayRefund = false;
        $this->resetErrorBag();
    }

    public function cancelManualTransfer(): void
    {
        $this->requireSuperAdmin();
        $this->reset(['selectedRefundId', 'transferReference', 'manualReason', 'verifiedNoGatewayRefund']);
        $this->resetErrorBag();
    }

    public function recordManualTransfer(RefundService $refundService): void
    {
        $admin = $this->requireSuperAdmin();

        $data = $this->validate([
            'selectedRefundId' => ['required', 'integer', 'exists:refunds,id'],
            'transferReference' => ['required', 'string', 'min:4', 'max:120'],
            'manualReason' => ['required', 'string', 'min:12', 'max:2000'],
            'verifiedNoGatewayRefund' => ['accepted'],
        ], [
            'transferReference.required' => 'Referensi transfer wajib diisi.',
            'transferReference.min' => 'Referensi transfer minimal 4 karakter.',
            'transferReference.max' => 'Referensi transfer maksimal 120 karakter.',
            'manualReason.required' => 'Alasan transfer manual wajib diisi.',
            'manualReason.min' => 'Alasan minimal 12 karakter.',
            'manualReason.max' => 'Alasan maksimal 2.000 karakter.',
            'verifiedNoGatewayRefund.accepted' => 'Konfirmasi bahwa tidak ada refund gateway yang berhasil wajib dicentang.',
        ]);

        $refund = Refund::query()->findOrFail((int) $data['selectedRefundId']);
        $updated = $refundService->recordManualTransfer(
            $refund,
            $admin,
            trim($data['transferReference']),
            trim($data['manualReason']),
            (bool) $data['verifiedNoGatewayRefund'],
        );

        $this->cancelManualTransfer();
        unset($this->refunds, $this->summary);
        session()->flash('status', 'Transfer manual refund ' . $updated->reference_code . ' tercatat sebagai berhasil. Referensi: ' . $updated->manual_transfer_reference . '.');
    }
};
?>

<div class="site-container space-y-6 py-6 sm:py-10">
    <header>
        <p class="eyebrow"><span class="eyebrow-dot"></span> Keuangan / Rekonsiliasi</p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-[-0.05em] sm:text-4xl">Refund pelanggan</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-[var(--ink-soft)]">Pantau kewajiban refund, ulangi request dengan idempotency key yang sama, atau catat transfer manual setelah memastikan gateway tidak mengembalikan dana. Status processing tidak boleh dianggap berhasil.</p>
    </header>

    @if (session()->has('status'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="ui-panel p-5"><p class="text-sm text-[var(--ink-soft)]">Kewajiban terbuka</p><p class="mt-2 text-3xl font-extrabold">{{ number_format($this->summary['open_count'], 0, ',', '.') }}</p></div>
        <div class="ui-panel p-5"><p class="text-sm text-[var(--ink-soft)]">Refund gagal</p><p class="mt-2 text-3xl font-extrabold">{{ number_format($this->summary['failed_count'], 0, ',', '.') }}</p></div>
        <div class="ui-panel p-5"><p class="text-sm text-[var(--ink-soft)]">Total refund sukses</p><p class="mt-2 text-2xl font-extrabold">Rp{{ number_format($this->summary['succeeded_amount'], 0, ',', '.') }}</p></div>
    </div>

    <section class="ui-panel p-4 sm:p-6">
        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_220px]">
            <div>
                <label class="ui-label" for="refund-search">Cari refund</label>
                <input id="refund-search" type="search" wire:model.live.debounce.300ms="search" class="ui-input w-full" placeholder="Kode refund, booking, pelanggan, referensi">
            </div>
            <div>
                <label class="ui-label" for="refund-status">Status</label>
                <select id="refund-status" wire:model.live="statusFilter" class="ui-input w-full">
                    <option value="">Semua status</option>
                    <option value="requested">Diminta</option>
                    <option value="under_review">Ditinjau</option>
                    <option value="approved">Disetujui</option>
                    <option value="processing">Diproses</option>
                    <option value="failed">Gagal</option>
                    <option value="succeeded">Berhasil</option>
                    <option value="rejected">Ditolak</option>
                </select>
            </div>
        </div>

        <div class="mt-5 space-y-3">
            @forelse ($this->refunds as $refund)
                <article wire:key="refund-{{ $refund->id }}" class="rounded-xl border border-[var(--border)] p-4 sm:p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-bold">{{ $refund->reference_code }}</p>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $refund->status === 'succeeded' ? 'bg-emerald-100 text-emerald-800' : ($refund->status === 'failed' ? 'bg-red-100 text-red-800' : (in_array($refund->status, ['requested', 'approved', 'processing'], true) ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700')) }}">{{ str($refund->status)->replace('_', ' ')->title() }}</span>
                            </div>
                            <p class="mt-2 text-sm">{{ $refund->recipient?->name }} · {{ $refund->recipient?->email }}</p>
                            <p class="mt-1 text-sm text-[var(--ink-soft)]">Booking {{ $refund->booking?->reference_code ?? ('#' . ($refund->booking_id ?? '—')) }} · {{ $refund->booking?->venue?->name ?? 'Transaksi lain' }}</p>
                            <p class="mt-2 text-sm">{{ $refund->reason_notes }}</p>
                            <p class="mt-2 text-xs text-[var(--ink-soft)]">Dibuat {{ $refund->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB · Gateway: {{ $refund->gateway_refund_id ?? 'belum ada ID refund' }}</p>
                            @if ($refund->manual_transfer_reference)
                                <p class="mt-2 text-xs text-[var(--ink-soft)]">Transfer manual: {{ $refund->manual_transfer_reference }} · diproses {{ $refund->processor?->name ?? 'Super Admin' }}</p>
                            @endif
                        </div>
                        <div class="shrink-0 lg:text-right">
                            <p class="text-xs text-[var(--ink-soft)]">Nominal refund</p>
                            <p class="mt-1 text-xl font-extrabold">Rp{{ number_format($refund->amount, 0, ',', '.') }}</p>
                            @if (in_array($refund->status, ['requested', 'approved', 'failed'], true))
                                <div class="mt-3 flex flex-col gap-2 sm:flex-row lg:flex-col">
                                    <button type="button" wire:click="retryRefund({{ $refund->id }})" class="btn btn-secondary" wire:loading.attr="disabled">Coba ulang gateway</button>
                                    <button type="button" wire:click="startManualTransfer({{ $refund->id }})" class="btn btn-primary">Catat transfer manual</button>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($selectedRefundId === (string) $refund->id)
                        <form wire:submit="recordManualTransfer" class="mt-5 rounded-xl bg-[var(--surface-soft)] p-4 sm:p-5">
                            <h3 class="font-bold">Catat transfer refund manual</h3>
                            <p class="mt-1 text-sm leading-6 text-[var(--ink-soft)]">Jangan gunakan langkah ini bila status gateway masih processing atau refund gateway belum diperiksa. Pastikan transfer bank benar-benar selesai dan tidak ada refund ganda.</p>
                            <label class="ui-label mt-4" for="manual-transfer-reference-{{ $refund->id }}">Referensi transfer bank</label>
                            <input id="manual-transfer-reference-{{ $refund->id }}" wire:model="transferReference" class="ui-input w-full" maxlength="120" required>
                            @error('transferReference') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                            <label class="ui-label mt-4" for="manual-transfer-reason-{{ $refund->id }}">Alasan dan hasil verifikasi</label>
                            <textarea id="manual-transfer-reason-{{ $refund->id }}" wire:model="manualReason" class="ui-input w-full" rows="3" maxlength="2000" required></textarea>
                            @error('manualReason') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                            <label class="mt-4 flex items-start gap-3 text-sm leading-6">
                                <input type="checkbox" wire:model="verifiedNoGatewayRefund" class="mt-1">
                                <span>Saya telah memverifikasi bahwa tidak ada refund gateway yang berhasil dan transfer manual ini telah dikonfirmasi selesai.</span>
                            </label>
                            @error('verifiedNoGatewayRefund') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                            @error('manualTransfer') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Konfirmasi refund berhasil</button>
                                <button type="button" wire:click="cancelManualTransfer" class="btn btn-secondary">Batal</button>
                            </div>
                        </form>
                    @endif
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-[var(--border-strong)] p-8 text-center text-sm text-[var(--ink-soft)]">Tidak ada refund yang sesuai dengan filter.</div>
            @endforelse
        </div>
        <p class="mt-4 text-xs leading-5 text-[var(--ink-soft)]">Daftar dibatasi 100 refund terbaru/prioritas. Semua perubahan status dicatat; kewajiban refund gagal tetap terbuka sampai diselesaikan.</p>
    </section>
</div>
