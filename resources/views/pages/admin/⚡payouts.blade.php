<?php

use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Services\Finance\PayoutAccountService;
use App\Services\Finance\PayoutService;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $reason = '';
    public string $transferReference = '';

    #[Computed]
    public function pendingAccounts()
    {
        return PayoutAccount::query()->with('owner')->where('verification_status', 'pending')->oldest()->limit(100)->get();
    }

    #[Computed]
    public function payouts()
    {
        return Payout::query()->with(['owner', 'booking.venue', 'payoutAccount'])->latest()->limit(100)->get();
    }

    public function reviewAccount(int $id, string $decision, PayoutAccountService $service, PayoutService $payoutService): void
    {
        $this->validate(['reason' => ['required', 'string', 'max:1000']]);
        $service->review(PayoutAccount::query()->findOrFail($id), auth()->user(), $decision, $this->reason);
        $payoutService->prepareEligiblePayouts();
        $this->reset('reason');
        unset($this->pendingAccounts, $this->payouts);
        session()->flash('status', 'Review rekening tersimpan.');
    }

    public function markPaid(int $id, PayoutService $service): void
    {
        $this->validate(['transferReference' => ['required', 'string', 'max:120']]);
        $service->markPaid(Payout::query()->findOrFail($id), auth()->user(), $this->transferReference);
        $this->reset('transferReference');
        unset($this->payouts);
        session()->flash('status', 'Transfer dicatat dan ledger diperbarui.');
    }
};
?>
<div class="site-container py-8 sm:py-12">
    <header class="max-w-3xl">
        <p class="eyebrow"><span class="eyebrow-dot"></span> Super Admin / Keuangan</p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-[-0.05em]">Rekening dan payout</h1>
        <p class="mt-3 text-sm text-[var(--muted)]">Verifikasi rekening Owner dan catat transfer payout manual. Nomor rekening penuh tidak ditampilkan.</p>
    </header>
    @if (session('status')) <div role="status" class="status-note status-success mt-5">{{ session('status') }}</div> @endif
    <section class="ui-panel mt-6 p-5">
        <h2 class="text-lg font-extrabold">Rekening menunggu verifikasi</h2>
        <label class="ui-label mt-4">Alasan review</label>
        <textarea wire:model="reason" rows="2" maxlength="1000" class="ui-input"></textarea>
        @error('reason') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
        <div class="mt-4 space-y-3">
            @forelse ($this->pendingAccounts as $account)
                <article wire:key="account-{{ $account->id }}" class="rounded-xl border border-[var(--border)] p-4">
                    <p class="font-bold">{{ $account->owner->name }} · {{ $account->bank_name }} ({{ $account->bank_code }})</p>
                    <p class="mt-1 text-sm text-[var(--muted)]">•••• {{ $account->account_number_last4 }} · {{ $account->account_holder_name }}</p>
                    <div class="mt-3 flex gap-2">
                        <button type="button" wire:click="reviewAccount({{ $account->id }}, 'verify')" class="btn btn-primary">Verifikasi</button>
                        <button type="button" wire:click="reviewAccount({{ $account->id }}, 'reject')" class="btn btn-secondary">Tolak</button>
                    </div>
                </article>
            @empty
                <p class="text-sm text-[var(--muted)]">Tidak ada pengajuan rekening pending.</p>
            @endforelse
        </div>
    </section>
    <section class="ui-panel mt-6 p-5">
        <h2 class="text-lg font-extrabold">Payout</h2>
        @error('transferReference') <p role="alert" class="ui-error mt-2">{{ $message }}</p> @enderror
        <div class="mt-4 space-y-3">
            @forelse ($this->payouts as $payout)
                <article wire:key="payout-{{ $payout->id }}" class="rounded-xl border border-[var(--border)] p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-bold">{{ $payout->reference_code }} · {{ $payout->owner->name }}</p>
                            <p class="mt-1 text-sm text-[var(--muted)]">{{ $payout->booking->reference_code }} · {{ $payout->payoutAccount?->bank_name ?? 'Rekening belum verified' }}</p>
                            <p class="mt-2 text-sm">Status: <strong>{{ $payout->status }}</strong></p>
                            <p class="text-base font-extrabold">Rp{{ number_format($payout->net_amount, 0, ',', '.') }}</p>
                        </div>
                        @if ($payout->status === 'eligible')
                            <div class="w-full max-w-xs">
                                <input type="text" wire:model="transferReference" maxlength="120" class="ui-input" placeholder="Referensi transfer bank">
                                <button type="button" wire:click="markPaid({{ $payout->id }})" class="btn btn-primary mt-2 w-full">Catat transfer paid</button>
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <p class="text-sm text-[var(--muted)]">Belum ada payout.</p>
            @endforelse
        </div>
    </section>
</div>
