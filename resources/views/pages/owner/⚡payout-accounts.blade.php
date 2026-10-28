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

<div class="site-container owner-core-page owner-payout-v2 py-7 sm:py-9 lg:py-10">
    @php
        $accounts = $this->accounts;
        $verifiedCount = $accounts->where('verification_status', 'verified')->count();
        $pendingCount = $accounts->where('verification_status', 'pending')->count();
    @endphp

    <header class="owner-core-header owner-payout-v2-header">
        <div>
            <p class="owner-core-kicker">Keuangan Owner</p>
            <h1>Rekening payout</h1>
            <p>Kelola rekening penerima dana. Rekening baru harus diverifikasi sebelum dapat digunakan untuk pencairan.</p>
        </div>
    </header>

    @if (session('status'))
        <div role="status" class="status-note status-success mt-5">{{ session('status') }}</div>
    @endif
    @error('payoutAccount')
        <div role="alert" class="status-note mt-4">{{ $message }}</div>
    @enderror

    <section class="owner-payout-summary" aria-label="Ringkasan rekening payout">
        <div>
            <span>Total rekening</span>
            <strong>{{ $accounts->count() }}</strong>
        </div>
        <div>
            <span>Terverifikasi</span>
            <strong>{{ $verifiedCount }}</strong>
        </div>
        <div>
            <span>Menunggu verifikasi</span>
            <strong>{{ $pendingCount }}</strong>
        </div>
    </section>

    <section class="owner-payout-section">
        <div class="owner-core-section-head">
            <div>
                <h2>Rekening terdaftar</h2>
                <p>Hanya empat digit terakhir yang ditampilkan setelah rekening disimpan.</p>
            </div>
        </div>

        @if ($accounts->isEmpty())
            <div class="owner-payout-empty">
                <div>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="5" width="18" height="14" rx="2"/>
                        <path d="M3 10h18M7 15h4"/>
                    </svg>
                </div>
                <h3>Belum ada rekening payout</h3>
                <p>Tambahkan rekening di bawah ini. Setelah diverifikasi, rekening dapat digunakan untuk menerima payout.</p>
            </div>
        @else
            <div class="owner-payout-list">
                @foreach ($accounts as $account)
                    @php
                        $status = [
                            'pending' => ['Menunggu verifikasi', 'warning'],
                            'verified' => ['Terverifikasi', 'success'],
                            'rejected' => ['Ditolak', 'danger'],
                        ];
                        [$label, $tone] = $status[$account->verification_status] ?? ['Tidak diketahui', 'muted'];
                    @endphp
                    <article wire:key="payout-account-{{ $account->id }}" class="owner-payout-account">
                        <div class="owner-payout-account-icon">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="5" width="18" height="14" rx="2"/>
                                <path d="M3 10h18M7 15h4"/>
                            </svg>
                        </div>

                        <div class="owner-payout-account-main">
                            <div class="owner-payout-account-title">
                                <div>
                                    <h3>{{ $account->bank_name }}</h3>
                                    <p>{{ $account->bank_code }} · •••• {{ $account->account_number_last4 }}</p>
                                </div>
                                <span class="owner-core-status is-{{ $tone }}">{{ $label }}</span>
                            </div>
                            <div class="owner-payout-account-meta">
                                <span>{{ $account->account_holder_name }}</span>
                                @if ($account->verification_status === 'verified')
                                    <span>Dapat digunakan untuk payout</span>
                                @elseif ($account->verification_status === 'rejected')
                                    <span>Tambahkan rekening baru untuk mengajukan ulang</span>
                                @else
                                    <span>Sedang menunggu pemeriksaan</span>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="owner-payout-form-section">
        <div class="owner-payout-form-copy">
            <p class="owner-core-kicker">Rekening baru</p>
            <h2>Tambahkan rekening</h2>
            <p>Gunakan rekening atas nama penerima yang sah. Rekening terverifikasi tidak dapat diedit; untuk mengganti rekening, tambahkan rekening baru.</p>
            <div class="owner-payout-security-note">
                <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4" y="8" width="12" height="8" rx="2"/>
                    <path d="M7 8V6a3 3 0 0 1 6 0v2"/>
                </svg>
                <span>Nomor rekening disimpan terenkripsi dan tidak ditampilkan kembali secara penuh.</span>
            </div>
        </div>

        <form wire:submit="save" class="owner-payout-form">
            <div>
                <label for="payout-bank-code" class="ui-label">Kode bank</label>
                <input id="payout-bank-code" type="text" wire:model="bankCode" maxlength="30" class="ui-input" placeholder="Contoh: BCA" required>
                @error('bankCode') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="payout-bank-name" class="ui-label">Nama bank</label>
                <input id="payout-bank-name" type="text" wire:model="bankName" maxlength="100" class="ui-input" placeholder="Bank Central Asia" required>
                @error('bankName') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="payout-account-number" class="ui-label">Nomor rekening</label>
                <input id="payout-account-number" type="password" inputmode="numeric" autocomplete="off" wire:model="accountNumber" maxlength="30" class="ui-input" placeholder="Masukkan nomor rekening" required>
                @error('accountNumber') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="payout-account-holder" class="ui-label">Nama pemilik rekening</label>
                <input id="payout-account-holder" type="text" wire:model="accountHolderName" maxlength="140" class="ui-input" placeholder="Sesuai nama pada rekening" required>
                @error('accountHolderName') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
            </div>
            <div class="owner-payout-form-actions">
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary">
                    <span wire:loading.remove wire:target="save">Kirim untuk verifikasi</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </button>
            </div>
        </form>
    </section>
</div>
