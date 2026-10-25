<?php

use App\Services\MfaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $code = '';

    public function mount(MfaService $mfaService): void
    {
        $user = Auth::user();

        abort_unless($user?->isSuperAdmin(), 403);

        if ($user->mfa_enabled_at && $user->mfa_secret) {
            $this->redirectRoute('mfa.challenge');

            return;
        }

        if (!$user->mfa_secret) {
            $user->mfa_secret = $mfaService->generateSecret();
            $user->save();
        }
    }

    #[Computed]
    public function secret(): string
    {
        $user = Auth::user();

        abort_unless($user?->isSuperAdmin(), 403);

        if ($user->mfa_enabled_at || !$user->mfa_secret) {
            abort(403);
        }

        return $user->mfa_secret;
    }

    public function activate(MfaService $mfaService): void
    {
        $this->validate(
            [
                'code' => ['required', 'digits:6'],
            ],
            [
                'code.required' => 'Masukkan kode dari aplikasi authenticator.',
                'code.digits' => 'Kode harus terdiri dari enam digit.',
            ],
        );

        $user = Auth::user();

        abort_unless($user && $user->isSuperAdmin() && $user->mfa_secret && !$user->mfa_enabled_at, 403);

        if (!$mfaService->verify($user->mfa_secret, $this->code)) {
            $this->addError('code', 'Kode tidak valid atau sudah kedaluwarsa.');

            return;
        }

        $user->mfa_enabled_at = now();
        $user->save();

        session([
            'admin_mfa_verified_user_id' => (string) $user->getKey(),
            'admin_mfa_verified_at' => now()->timestamp,
        ]);

        $this->redirectRoute('home');
    }
};
?>

<div class="site-container max-w-5xl py-6 sm:py-10 lg:py-14">
    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,.78fr)_minmax(0,1.22fr)] lg:gap-8">
        <aside class="rounded-[1.5rem] bg-[var(--brand-forest)] p-5 text-white sm:p-7 lg:sticky lg:top-24 lg:p-8">
            <p class="eyebrow !text-[var(--brand-lime)]"><span class="eyebrow-dot !bg-[var(--brand-lime)]"></span> Keamanan administrator</p>
            <h1 class="mt-4 text-3xl font-extrabold leading-tight tracking-[-0.05em] text-white sm:text-4xl">Tambahkan lapisan perlindungan.</h1>
            <p class="mt-4 text-sm leading-7 text-white/80">Hubungkan akun dengan aplikasi authenticator yang mendukung TOTP, lalu konfirmasi menggunakan kode enam digit.</p>
            <div class="mt-6 space-y-4 border-t border-white/20 pt-5">
                <div class="flex items-start gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-[var(--brand-lime)] text-xs font-extrabold text-[var(--ink)]">1</span><p class="text-sm leading-6 text-white/90">Simpan secret di aplikasi authenticator milikmu.</p></div>
                <div class="flex items-start gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-[var(--brand-lime)] text-xs font-extrabold text-[var(--ink)]">2</span><p class="text-sm leading-6 text-white/90">Masukkan kode terbaru untuk mengaktifkan MFA.</p></div>
            </div>
            <p class="mt-6 border-t border-white/20 pt-5 text-xs leading-5 text-white/65">Secret TOTP bersifat rahasia. Siapa pun yang memilikinya dapat membuat kode autentikasi.</p>
        </aside>

        <section class="ui-panel min-w-0 p-5 sm:p-7 lg:p-8">
            <p class="eyebrow"><span class="eyebrow-dot"></span> Pengaturan keamanan / 01</p>
            <h2 class="mt-3 text-2xl font-extrabold leading-tight tracking-[-0.05em] sm:text-3xl">Hubungkan aplikasi authenticator.</h2>
            <p class="mt-3 text-sm leading-6 text-[var(--ink-soft)]">Tambahkan akun secara manual menggunakan secret berikut.</p>

            <div class="mt-5 rounded-xl border border-[var(--border)] bg-[var(--surface-soft)] p-4 sm:p-5">
                <label class="ui-label">Secret TOTP</label>
                <div class="break-all rounded-lg border border-[var(--border-strong)] bg-white p-3 font-mono text-sm leading-6 text-[var(--ink)]" aria-label="Secret TOTP">{{ $this->secret }}</div>
                <button type="button" x-data x-on:click="navigator.clipboard.writeText(@js($this->secret))" class="btn btn-secondary mt-3 w-full sm:w-auto">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><rect x="8" y="8" width="13" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></svg>
                    Salin secret
                </button>
                <p class="form-note mt-3">Jangan kirim secret ini melalui chat atau email.</p>
            </div>

            <div class="mt-6 border-t border-[var(--border)] pt-6">
                <p class="eyebrow"><span class="eyebrow-dot"></span> Pengaturan keamanan / 02</p>
                <h3 class="mt-3 text-xl font-extrabold tracking-[-0.04em]">Konfirmasi kode</h3>
                <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Masukkan enam digit yang sedang ditampilkan aplikasi authenticator.</p>
                <form wire:submit="activate" class="mt-5 space-y-4">
                    <div>
                        <label for="code" class="ui-label">Kode authenticator</label>
                        <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code" class="ui-input font-mono text-base tracking-[0.28em]" placeholder="000000" required aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}">
                        @error('code') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full">
                        <span wire:loading.remove wire:target="activate">Aktifkan MFA</span>
                        <span wire:loading wire:target="activate">Memverifikasi...</span>
                    </button>
                </form>
            </div>
        </section>
    </div>
</div>