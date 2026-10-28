<?php

use App\Models\PayoutAccount;
use App\Services\Finance\PayoutAccountService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rekening payout')] class extends Component {
    public string $bankCode = '';
    public string $bankName = '';
    public string $accountNumber = '';
    public string $accountHolderName = '';

    #[Computed]
    public function accounts()
    {
        return auth()->user()->payoutAccounts()->latest()->get();
    }

    public function save(PayoutAccountService $service): void
    {
        $validated = $this->validate([
            'bankCode' => ['required', 'string', 'min:2', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'],
            'bankName' => ['required', 'string', 'max:100'],
            'accountNumber' => ['required', 'string', 'regex:/^[0-9]{6,30}$/'],
            'accountHolderName' => ['required', 'string', 'max:140'],
        ], [
            'bankCode.required' => 'Kode bank wajib diisi.',
            'bankCode.regex' => 'Kode bank hanya boleh berisi huruf, angka, garis bawah, atau tanda hubung.',
            'bankName.required' => 'Nama bank wajib diisi.',
            'accountNumber.required' => 'Nomor rekening wajib diisi.',
            'accountNumber.regex' => 'Nomor rekening harus berupa 6–30 digit angka.',
            'accountHolderName.required' => 'Nama pemilik rekening wajib diisi.',
        ]);

        $service->createForOwner(
            auth()->user(),
            $validated['bankCode'],
            $validated['bankName'],
            $validated['accountNumber'],
            $validated['accountHolderName'],
        );

        $this->reset(['bankCode', 'bankName', 'accountNumber', 'accountHolderName']);
        $this->resetValidation();
        unset($this->accounts);
        session()->flash('status', 'Rekening dikirim untuk verifikasi Super Admin. Nomor rekening disimpan terenkripsi.');
    }
};
?>
<div class="site-container owner-core-page owner-payout-page py-7 sm:py-9 lg:py-10">
    <header class="owner-core-header">
        <p class="owner-core-kicker">Keuangan Owner</p>
        <h1>Rekening payout</h1>
        <p class="mt-3 text-sm leading-6 text-[var(--muted)]">Rekening baru harus diverifikasi Super Admin sebelum dapat menerima pencairan. Nomor rekening disimpan terenkripsi dan hanya empat digit terakhir yang ditampilkan.</p>
    </header>

    @if (session('status'))
        <div role="status" class="status-note status-success mt-6">{{ session('status') }}</div>
    @endif
    @error('payoutAccount')
        <div role="alert" class="status-note mt-4">{{ $message }}</div>
    @enderror

    <div class="owner-finance-layout">
        <section class="owner-finance-panel owner-finance-panel-form">
            <h2 class="text-lg font-extrabold">Tambahkan rekening</h2>
            <p class="mt-1 text-sm leading-6 text-[var(--muted)]">Untuk mengubah rekening, tambahkan rekening baru. Rekening yang sudah diverifikasi tidak dapat diedit.</p>
            <form wire:submit="save" class="mt-5 space-y-4">
                <div>
                    <label for="payout-bank-code" class="ui-label">Kode bank</label>
                    <input id="payout-bank-code" type="text" wire:model="bankCode" maxlength="30" class="ui-input" placeholder="BCA" required>
                    @error('bankCode') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="payout-bank-name" class="ui-label">Nama bank</label>
                    <input id="payout-bank-name" type="text" wire:model="bankName" maxlength="100" class="ui-input" placeholder="Bank Central Asia" required>
                    @error('bankName') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="payout-account-number" class="ui-label">Nomor rekening</label>
                    <input id="payout-account-number" type="password" inputmode="numeric" autocomplete="off" wire:model="accountNumber" maxlength="30" class="ui-input" required>
                    @error('accountNumber') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="payout-account-holder" class="ui-label">Nama pemilik rekening</label>
                    <input id="payout-account-holder" type="text" wire:model="accountHolderName" maxlength="140" class="ui-input" required>
                    @error('accountHolderName') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full">
                    <span wire:loading.remove wire:target="save">Kirim untuk verifikasi</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </button>
            </form>
        </section>

        <section class="owner-finance-panel">
            <h2 class="text-lg font-extrabold">Rekening terdaftar</h2>
            <div class="mt-5 space-y-3">
                @forelse ($this->accounts as $account)
                    @php
                        $status = [
                            'pending' => ['Menunggu verifikasi', 'bg-[var(--surface-soft)] text-[var(--ink-soft)]'],
                            'verified' => ['Terverifikasi', 'bg-[var(--success-bg)] text-[var(--success-ink)]'],
                            'rejected' => ['Ditolak', 'bg-[var(--danger-bg)] text-[var(--danger-ink)]'],
                        ];
                        [$label, $class] = $status[$account->verification_status] ?? ['Tidak diketahui', 'bg-[var(--surface-soft)] text-[var(--muted)]'];
                    @endphp
                    <article wire:key="payout-account-{{ $account->id }}" class="owner-finance-item">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-extrabold">{{ $account->bank_name }} <span class="text-[var(--muted)]">({{ $account->bank_code }})</span></h3>
                                <p class="mt-1 text-sm text-[var(--ink-soft)]">•••• {{ $account->account_number_last4 }}</p>
                                <p class="mt-1 text-xs text-[var(--muted)]">{{ $account->account_holder_name }}</p>
                            </div>
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $class }}">{{ $label }}</span>
                        </div>
                        @if ($account->verification_status === 'verified')
                            <p class="mt-3 text-xs text-[var(--muted)]">Rekening ini dapat dipakai untuk payout sesuai kelayakan booking.</p>
                        @elseif ($account->verification_status === 'rejected')
                            <p class="mt-3 text-xs text-[var(--danger-ink)]">Tambahkan rekening baru jika ingin mengajukan ulang.</p>
                        @endif
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed border-[var(--border-strong)] p-6 text-center">
                        <p class="text-sm font-semibold">Belum ada rekening payout.</p>
                        <p class="mt-1 text-sm text-[var(--muted)]">Tambahkan rekening dan tunggu verifikasi.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
