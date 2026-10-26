<?php

use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\User;
use App\Services\Finance\PayoutService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Payout Owner')] class extends Component {
    public string $search = '';

    public string $statusFilter = '';

    public string $selectedPayoutId = '';

    public string $transferReference = '';

    public string $accountReviewReason = '';

    #[Computed]
    public function payouts()
    {
        $this->requireSuperAdmin();

        return Payout::query()
            ->with(['owner:id,name,email', 'booking.venue:id,name', 'payoutAccount:id,bank_name,account_number_last4,account_holder_name,verification_status'])
            ->when(trim($this->search) !== '', function ($query): void {
                $term = trim($this->search);

                $query->where(function ($nested) use ($term): void {
                    $nested->where('reference_code', 'like', '%' . $term . '%')
                        ->orWhere('transfer_reference', 'like', '%' . $term . '%')
                        ->orWhereHas('owner', fn ($owner) => $owner->where('name', 'like', '%' . $term . '%')->orWhere('email', 'like', '%' . $term . '%'))
                        ->orWhereHas('booking.venue', fn ($venue) => $venue->where('name', 'like', '%' . $term . '%'));
                });
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderByRaw("CASE status WHEN 'held' THEN 0 WHEN 'eligible' THEN 1 WHEN 'processing' THEN 2 WHEN 'failed' THEN 3 WHEN 'paid' THEN 4 ELSE 5 END")
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function pendingAccounts()
    {
        $this->requireSuperAdmin();

        return PayoutAccount::query()
            ->where('verification_status', PayoutAccount::STATUS_PENDING)
            ->with('owner:id,name,email')
            ->orderBy('created_at')
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function summary(): array
    {
        $this->requireSuperAdmin();

        $counts = Payout::query()
            ->selectRaw('status, COUNT(*) as total, SUM(net_amount) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'held' => (int) ($counts[Payout::STATUS_HELD]->total ?? 0),
            'eligible' => (int) ($counts[Payout::STATUS_ELIGIBLE]->total ?? 0),
            'paid_amount' => (int) ($counts[Payout::STATUS_PAID]->amount ?? 0),
        ];
    }

    private function requireSuperAdmin(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User && $user->isSuperAdmin() && $user->status === 'active', 403);

        return $user;
    }

    public function reviewAccount(int $accountId, string $decision, PayoutService $payoutService): void
    {
        $admin = $this->requireSuperAdmin();

        $data = $this->validate([
            'accountReviewReason' => ['required', 'string', 'min:8', 'max:2000'],
        ], [
            'accountReviewReason.required' => 'Alasan verifikasi wajib diisi.',
            'accountReviewReason.min' => 'Alasan minimal 8 karakter.',
            'accountReviewReason.max' => 'Alasan maksimal 2.000 karakter.',
        ]);

        $account = PayoutAccount::query()->findOrFail($accountId);
        $payoutService->reviewPayoutAccount($account, $admin, $decision, trim($data['accountReviewReason']));

        $this->accountReviewReason = '';
        unset($this->pendingAccounts, $this->payouts, $this->summary);
        session()->flash('status', $decision === PayoutAccount::STATUS_VERIFIED
            ? 'Rekening ditandai terverifikasi dan dapat dipakai untuk payout.'
            : 'Rekening ditolak. Owner dapat mengajukan rekening baru.');
    }

    public function startSettlement(int $payoutId): void
    {
        $this->requireSuperAdmin();

        $payout = Payout::query()->findOrFail($payoutId);

        abort_unless(in_array($payout->status, [Payout::STATUS_ELIGIBLE, Payout::STATUS_PROCESSING], true), 403);

        $this->selectedPayoutId = (string) $payout->getKey();
        $this->transferReference = '';
        $this->resetErrorBag();
    }

    public function cancelSettlement(): void
    {
        $this->requireSuperAdmin();
        $this->selectedPayoutId = '';
        $this->transferReference = '';
        $this->resetErrorBag();
    }

    public function markPaid(PayoutService $payoutService): void
    {
        $admin = $this->requireSuperAdmin();
        $data = $this->validate([
            'selectedPayoutId' => ['required', 'integer', 'exists:payouts,id'],
            'transferReference' => ['required', 'string', 'min:4', 'max:120'],
        ], [
            'transferReference.required' => 'Referensi transfer wajib diisi.',
            'transferReference.min' => 'Referensi transfer minimal 4 karakter.',
            'transferReference.max' => 'Referensi transfer maksimal 120 karakter.',
        ]);

        $payout = Payout::query()->findOrFail((int) $data['selectedPayoutId']);

        $payoutService->markPaid($payout, $admin, trim($data['transferReference']));

        $this->selectedPayoutId = '';
        $this->transferReference = '';
        unset($this->payouts, $this->summary);
        session()->flash('status', 'Payout ditandai selesai setelah referensi transfer dicatat.');
    }
};
?>

<div class="site-container space-y-6 py-6 sm:py-10">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow"><span class="eyebrow-dot"></span> Keuangan / Settlement</p>
            <h1 class="mt-3 text-3xl font-extrabold tracking-[-0.05em] sm:text-4xl">Payout Owner</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-[var(--ink-soft)]">Pantau kewajiban payout dan catat transfer manual yang sudah diverifikasi. Payout yang ditahan tidak dapat diselesaikan.</p>
        </div>
    </div>

    @if (session()->has('status'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="ui-panel p-5"><p class="text-sm text-[var(--ink-soft)]">Payout ditahan</p><p class="mt-2 text-3xl font-extrabold">{{ number_format($this->summary['held'], 0, ',', '.') }}</p></div>
        <div class="ui-panel p-5"><p class="text-sm text-[var(--ink-soft)]">Siap dibayar</p><p class="mt-2 text-3xl font-extrabold">{{ number_format($this->summary['eligible'], 0, ',', '.') }}</p></div>
        <div class="ui-panel p-5"><p class="text-sm text-[var(--ink-soft)]">Total transfer tercatat</p><p class="mt-2 text-2xl font-extrabold">Rp{{ number_format($this->summary['paid_amount'], 0, ',', '.') }}</p></div>
    </div>

    <section class="ui-panel p-5 sm:p-6">
        <div>
            <h2 class="text-xl font-extrabold">Verifikasi rekening payout</h2>
            <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Periksa rekening melalui prosedur verifikasi yang berlaku sebelum menyetujui. Setiap keputusan wajib menyertakan alasan dan masuk audit log.</p>
        </div>
        <div class="mt-4 space-y-3">
            @forelse ($this->pendingAccounts as $account)
                <article wire:key="pending-payout-account-{{ $account->id }}" class="rounded-xl border border-[var(--border)] p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-bold">{{ $account->bank_name }} · {{ $account->bank_code }}</p>
                            <p class="mt-1 text-sm">{{ $account->owner?->name ?? 'Owner tidak tersedia' }} <span class="text-[var(--ink-soft)]">({{ $account->owner?->email }})</span></p>
                            <p class="mt-1 text-sm text-[var(--ink-soft)]">Rekening ****{{ $account->account_number_last4 }} · {{ $account->account_holder_name }}</p>
                            <p class="mt-1 text-xs text-[var(--ink-soft)]">Diajukan {{ $account->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>
                        </div>
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">Menunggu verifikasi</span>
                    </div>
                    <label class="ui-label mt-4" for="account-review-reason-{{ $account->id }}">Alasan / catatan verifikasi</label>
                    <textarea id="account-review-reason-{{ $account->id }}" wire:model="accountReviewReason" rows="2" maxlength="2000" class="ui-input w-full" placeholder="Catat dasar pemeriksaan rekening"></textarea>
                    @error('accountReviewReason') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    @error('accountReview') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" wire:click="reviewAccount({{ $account->id }}, 'verified')" class="btn btn-primary" wire:loading.attr="disabled">Verifikasi rekening</button>
                        <button type="button" wire:click="reviewAccount({{ $account->id }}, 'rejected')" class="btn btn-secondary" wire:loading.attr="disabled">Tolak rekening</button>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-[var(--border-strong)] p-5 text-center text-sm text-[var(--ink-soft)]">Tidak ada rekening yang menunggu verifikasi.</div>
            @endforelse
        </div>
    </section>

    <section class="ui-panel p-4 sm:p-6">
        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_220px]">
            <div>
                <label class="ui-label" for="payout-search">Cari payout</label>
                <input id="payout-search" type="search" wire:model.live.debounce.300ms="search" class="ui-input w-full" placeholder="Kode payout, Owner, venue, referensi transfer">
            </div>
            <div>
                <label class="ui-label" for="payout-status">Status</label>
                <select id="payout-status" wire:model.live="statusFilter" class="ui-input w-full">
                    <option value="">Semua status</option>
                    <option value="held">Ditahan</option>
                    <option value="eligible">Siap dibayar</option>
                    <option value="processing">Diproses</option>
                    <option value="paid">Dibayar</option>
                    <option value="failed">Gagal</option>
                    <option value="on_hold">Ditahan manual</option>
                    <option value="cancelled">Dibatalkan</option>
                </select>
            </div>
        </div>

        <div class="mt-5 space-y-3">
            @forelse ($this->payouts as $payout)
                <article wire:key="payout-{{ $payout->id }}" class="rounded-xl border border-[var(--border)] p-4 sm:p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-bold">{{ $payout->reference_code }}</p>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $payout->status === 'paid' ? 'bg-emerald-100 text-emerald-800' : ($payout->status === 'eligible' ? 'bg-lime-100 text-lime-900' : ($payout->status === 'held' || $payout->status === 'on_hold' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700')) }}">{{ str($payout->status)->replace('_', ' ')->title() }}</span>
                            </div>
                            <p class="mt-2 text-sm">{{ $payout->owner?->name ?? 'Owner tidak tersedia' }} · {{ $payout->booking?->venue?->name ?? 'Venue tidak tersedia' }}</p>
                            <p class="mt-1 text-xs text-[var(--ink-soft)]">Booking #{{ $payout->booking_id }} · {{ $payout->eligible_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? 'Belum eligible' }} WIB</p>
                            <div class="mt-3 text-sm text-[var(--ink-soft)]">
                                @if ($payout->payoutAccount)
                                    {{ $payout->payoutAccount->bank_name }} · rekening ****{{ $payout->payoutAccount->account_number_last4 }} · {{ $payout->payoutAccount->account_holder_name }}
                                @else
                                    Belum ada rekening payout terverifikasi.
                                @endif
                            </div>
                            @if ($payout->transfer_reference)
                                <p class="mt-2 text-xs text-[var(--ink-soft)]">Referensi transfer: {{ $payout->transfer_reference }}</p>
                            @endif
                        </div>
                        <div class="shrink-0 lg:text-right">
                            <p class="text-xs text-[var(--ink-soft)]">Nominal bersih</p>
                            <p class="mt-1 text-xl font-extrabold">Rp{{ number_format($payout->net_amount, 0, ',', '.') }}</p>
                            @if (in_array($payout->status, ['eligible', 'processing'], true))
                                <button type="button" wire:click="startSettlement({{ $payout->id }})" class="btn btn-primary mt-3 w-full sm:w-auto">Catat transfer</button>
                            @endif
                        </div>
                    </div>

                    @if ($selectedPayoutId === (string) $payout->id)
                        <form wire:submit="markPaid" class="mt-5 rounded-xl bg-[var(--surface-soft)] p-4">
                            <label class="ui-label" for="transfer-reference-{{ $payout->id }}">Referensi transfer bank</label>
                            <input id="transfer-reference-{{ $payout->id }}" type="text" wire:model="transferReference" class="ui-input w-full" maxlength="120" required placeholder="Nomor referensi dari bank">
                            @error('transferReference') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                            @error('selectedPayoutId') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                            <p class="mt-2 text-xs leading-5 text-[var(--ink-soft)]">Gunakan hanya setelah transfer bank benar-benar terkonfirmasi. Tindakan ini bersifat finansial dan akan masuk audit log serta ledger.</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Konfirmasi transfer</button>
                                <button type="button" wire:click="cancelSettlement" class="btn btn-secondary">Batal</button>
                            </div>
                        </form>
                    @endif
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-[var(--border-strong)] p-8 text-center text-sm text-[var(--ink-soft)]">Belum ada payout yang sesuai dengan filter.</div>
            @endforelse
        </div>
        <p class="mt-4 text-xs leading-5 text-[var(--ink-soft)]">Daftar dibatasi 100 payout terbaru/terprioritas. Rekening tidak menampilkan nomor lengkap. Payout ditahan atau yang memiliki refund terbuka tidak dapat diselesaikan melalui halaman ini.</p>
    </section>
</div>
