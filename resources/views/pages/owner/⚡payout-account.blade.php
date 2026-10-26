<?php

use App\Models\AuditLog;
use App\Models\PayoutAccount;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rekening payout')] class extends Component {
    public string $bankCode = '';

    public string $bankName = '';

    public string $accountNumber = '';

    public string $accountHolderName = '';

    public string $currentPassword = '';

    #[Computed]
    public function accounts()
    {
        $owner = $this->requireOwner();

        return PayoutAccount::query()
            ->where('owner_id', $owner->getKey())
            ->with('verifier:id,name')
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function payouts()
    {
        $owner = $this->requireOwner();

        return Payout::query()
            ->where('owner_id', $owner->getKey())
            ->with(['booking.venue:id,name'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    private function requireOwner(): User
    {
        $owner = auth()->user();

        abort_unless($owner instanceof User && $owner->isOwner() && $owner->status === 'active' && $owner->hasVerifiedEmail(), 403);

        return $owner;
    }

    public function save(): void
    {
        $owner = $this->requireOwner();

        $data = $this->validate([
            'currentPassword' => ['required', 'string', 'max:255'],
            'bankCode' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'],
            'bankName' => ['required', 'string', 'max:100'],
            'accountNumber' => ['required', 'string', 'min:5', 'max:40', 'regex:/^[0-9]+$/'],
            'accountHolderName' => ['required', 'string', 'max:140'],
        ], [
            'bankCode.required' => 'Kode bank wajib diisi.',
            'bankCode.regex' => 'Kode bank hanya boleh berisi huruf, angka, garis bawah, atau tanda hubung.',
            'bankName.required' => 'Nama bank wajib diisi.',
            'accountNumber.required' => 'Nomor rekening wajib diisi.',
            'accountNumber.min' => 'Nomor rekening minimal 5 digit.',
            'accountNumber.max' => 'Nomor rekening maksimal 40 digit.',
            'accountNumber.regex' => 'Nomor rekening hanya boleh berisi angka.',
            'accountHolderName.required' => 'Nama pemilik rekening wajib diisi.',
        ]);

        $stepUpKey = 'payout-account-step-up:' . $owner->getKey() . ':' . hash('sha256', (string) request()->ip());

        if (RateLimiter::tooManyAttempts($stepUpKey, 5)) {
            throw ValidationException::withMessages([
                'currentPassword' => 'Terlalu banyak percobaan konfirmasi password. Coba lagi dalam ' . RateLimiter::availableIn($stepUpKey) . ' detik.',
            ]);
        }

        RateLimiter::hit($stepUpKey, 60);

        if (! Hash::check($data['currentPassword'], (string) $owner->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => 'Password tidak sesuai; rekening tidak disimpan.',
            ]);
        }

        RateLimiter::clear($stepUpKey);

        DB::transaction(function () use ($owner, $data): void {
            $account = PayoutAccount::query()->create([
                'owner_id' => $owner->getKey(),
                'bank_code' => strtoupper(trim($data['bankCode'])),
                'bank_name' => trim($data['bankName']),
                'account_number_encrypted' => trim($data['accountNumber']),
                'account_number_last4' => substr(trim($data['accountNumber']), -4),
                'account_holder_name' => trim($data['accountHolderName']),
                'verification_status' => PayoutAccount::STATUS_PENDING,
                'verified_by' => null,
                'verified_at' => null,
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $owner->getKey(),
                'actor_role' => $owner->role,
                'action' => 'payout_account.submitted',
                'auditable_type' => PayoutAccount::class,
                'auditable_id' => $account->getKey(),
                'reason' => 'Owner submitted a payout account for manual verification.',
                'before_state_json' => null,
                'after_state_json' => [
                    'bank_code' => $account->bank_code,
                    'bank_name' => $account->bank_name,
                    'account_number_last4' => $account->account_number_last4,
                    'account_holder_name' => $account->account_holder_name,
                    'verification_status' => $account->verification_status,
                ],
                'ip_hash' => request()->ip() ? hash('sha256', (string) request()->ip()) : null,
                'created_at' => now('UTC'),
            ]);
        });

        $this->reset(['bankCode', 'bankName', 'accountNumber', 'accountHolderName', 'currentPassword']);
        unset($this->accounts);
        session()->flash('status', 'Rekening berhasil diajukan dan menunggu verifikasi Super Admin.');
    }
};
?>

<div class="site-container space-y-6 py-6 sm:py-10">
    <div>
        <p class="eyebrow"><span class="eyebrow-dot"></span> Keuangan / Rekening Owner</p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-[-0.05em] sm:text-4xl">Rekening payout</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-[var(--ink-soft)]">Tambahkan rekening atas nama yang berhak menerima dana booking. Rekening baru harus diverifikasi Super Admin sebelum digunakan untuk payout.</p>
    </div>

    @if (session()->has('status'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <section class="ui-panel p-5 sm:p-7">
        <h2 class="text-xl font-extrabold">Ajukan rekening baru</h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Nomor rekening disimpan terenkripsi. Setelah disimpan, nomor lengkap tidak ditampilkan kembali di halaman.</p>

        <form wire:submit="save" class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="ui-label" for="bank-code">Kode bank</label>
                <input id="bank-code" wire:model="bankCode" class="ui-input w-full" maxlength="30" required placeholder="Contoh: BCA">
                @error('bankCode') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="ui-label" for="bank-name">Nama bank</label>
                <input id="bank-name" wire:model="bankName" class="ui-input w-full" maxlength="100" required placeholder="Nama bank sesuai rekening">
                @error('bankName') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="ui-label" for="account-number">Nomor rekening</label>
                <input id="account-number" type="password" inputmode="numeric" autocomplete="off" wire:model="accountNumber" class="ui-input w-full" maxlength="40" required>
                @error('accountNumber') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="ui-label" for="current-password">Konfirmasi password saat ini</label>
                <input id="current-password" type="password" autocomplete="current-password" wire:model="currentPassword" class="ui-input w-full" required>
                @error('currentPassword') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="ui-label" for="account-holder">Nama pemilik rekening</label>
                <input id="account-holder" wire:model="accountHolderName" class="ui-input w-full" maxlength="140" required>
                @error('accountHolderName') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Ajukan verifikasi</button>
            </div>
        </form>
    </section>

    <section class="ui-panel p-5 sm:p-7">
        <h2 class="text-xl font-extrabold">Rekening terdaftar</h2>
        <div class="mt-4 space-y-3">
            @forelse ($this->accounts as $account)
                <article wire:key="payout-account-{{ $account->id }}" class="rounded-xl border border-[var(--border)] p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-bold">{{ $account->bank_name }} <span class="font-normal text-[var(--ink-soft)]">· {{ $account->bank_code }}</span></p>
                            <p class="mt-1 text-sm text-[var(--ink-soft)]">Rekening berakhiran {{ $account->account_number_last4 }} · {{ $account->account_holder_name }}</p>
                            <p class="mt-1 text-xs text-[var(--ink-soft)]">Diajukan {{ $account->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>
                            @if ($account->verified_at)
                                <p class="mt-1 text-xs text-[var(--ink-soft)]">Diverifikasi {{ $account->verified_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB oleh {{ $account->verifier?->name ?? 'Super Admin' }}</p>
                            @endif
                        </div>
                        <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold {{ $account->verification_status === 'verified' ? 'bg-emerald-100 text-emerald-800' : ($account->verification_status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-900') }}">{{ $account->verification_status === 'verified' ? 'Terverifikasi' : ($account->verification_status === 'rejected' ? 'Ditolak' : 'Menunggu verifikasi') }}</span>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-[var(--border-strong)] p-6 text-center text-sm text-[var(--ink-soft)]">Belum ada rekening payout yang diajukan.</div>
            @endforelse
        </div>
    </section>

    <section class="ui-panel p-5 sm:p-7">
        <h2 class="text-xl font-extrabold">Riwayat payout</h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Nominal bersih dan status pembayaran ditampilkan untuk rekening Owner ini. Payout yang ditahan memerlukan penyelesaian pemeriksaan sebelum dapat dibayarkan.</p>
        <div class="mt-4 space-y-3">
            @forelse ($this->payouts as $payout)
                <article wire:key="owner-payout-{{ $payout->id }}" class="rounded-xl border border-[var(--border)] p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-bold">{{ $payout->reference_code }}</p>
                            <p class="mt-1 text-sm">{{ $payout->booking?->venue?->name ?? 'Venue tidak tersedia' }}</p>
                            <p class="mt-1 text-xs text-[var(--ink-soft)]">Booking #{{ $payout->booking_id }} · {{ $payout->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>
                            @if ($payout->transfer_reference)
                                <p class="mt-2 text-xs text-[var(--ink-soft)]">Referensi transfer: {{ $payout->transfer_reference }}</p>
                            @endif
                        </div>
                        <div class="sm:text-right">
                            <p class="text-xl font-extrabold">Rp{{ number_format($payout->net_amount, 0, ',', '.') }}</p>
                            <span class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $payout->status === 'paid' ? 'bg-emerald-100 text-emerald-800' : ($payout->status === 'eligible' ? 'bg-lime-100 text-lime-900' : ($payout->status === 'held' || $payout->status === 'on_hold' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700')) }}">{{ str($payout->status)->replace('_', ' ')->title() }}</span>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-[var(--border-strong)] p-6 text-center text-sm text-[var(--ink-soft)]">Belum ada payout untuk ditampilkan.</div>
            @endforelse
        </div>
    </section>
</div>
