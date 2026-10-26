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
                'code.required' => 'Masukkan kode dari aplikasi autentikasi.',
                'code.digits' => 'Kode harus terdiri dari enam digit.',
            ],
        );

        $user = Auth::user();

        abort_unless($user && $user->isSuperAdmin() && $user->mfa_secret && !$user->mfa_enabled_at, 403);

        if (!$mfaService->verify($user->mfa_secret, $this->code)) {
            $this->addError('code', 'Kode tidak valid atau sudah kedaluwarsa. Periksa kode terbaru dari aplikasi autentikasi.');

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
<div class="site-container py-8 sm:py-12 lg:py-16">
    <section class="auth-shell auth-shell-wide">
        <div class="mb-5 text-center sm:mb-6">
            <p class="auth-page-kicker">Keamanan administrator</p>
            <h1 class="auth-page-title">Amankan akun Super Admin</h1>
            <p class="auth-page-description mx-auto max-w-xl">Hubungkan akun dengan aplikasi autentikasi yang mendukung TOTP, lalu konfirmasi menggunakan kode enam digit.</p>
        </div>

        <div class="auth-card">
            <div class="flex items-start gap-3 rounded-xl border border-[var(--border)] bg-[var(--surface-soft)] p-4">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[var(--brand-lime)] text-[var(--ink)]">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-[var(--ink)]">Secret ini bersifat rahasia</p>
                    <p class="mt-1 text-sm leading-6 text-[var(--ink-soft)]">Jangan kirim secret TOTP atau kode enam digit kepada siapa pun. Simpan secret hanya di aplikasi autentikasi milikmu.</p>
                </div>
            </div>

            <section aria-labelledby="setup-step-one" class="mt-6">
                <p class="auth-page-kicker">Langkah 1 dari 2</p>
                <h2 id="setup-step-one" class="mt-2 text-xl font-extrabold tracking-[-0.04em] sm:text-2xl">Hubungkan aplikasi autentikasi</h2>
                <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Buat entri baru untuk Adatempat di aplikasi autentikasi, lalu masukkan secret berikut.</p>

                <div class="mt-4 rounded-xl border border-[var(--border)] bg-white p-4 sm:p-5">
                    <p id="totp-secret-label" class="ui-label">Secret TOTP</p>
                    <div role="group" aria-labelledby="totp-secret-label" class="break-all rounded-lg border border-[var(--border-strong)] bg-[var(--surface-soft)] p-3 font-mono text-sm leading-6 text-[var(--ink)]">{{ $this->secret }}</div>
                    <div x-data="{ copied: false }" class="mt-3">
                        <button type="button" x-on:click="navigator.clipboard.writeText(@js($this->secret)).then(() => copied = true).catch(() => copied = false)" class="btn btn-secondary w-full sm:w-auto">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><rect x="8" y="8" width="13" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></svg>
                            <span x-show="!copied">Salin secret</span>
                            <span x-cloak x-show="copied">Secret disalin</span>
                        </button>
                        <p class="form-note mt-3" role="status">Jika tombol salin tidak berhasil, pilih dan salin secret secara manual.</p>
                    </div>
                </div>
            </section>

            <section aria-labelledby="setup-step-two" class="mt-7 border-t border-[var(--border)] pt-6">
                <p class="auth-page-kicker">Langkah 2 dari 2</p>
                <h2 id="setup-step-two" class="mt-2 text-xl font-extrabold tracking-[-0.04em] sm:text-2xl">Konfirmasi pengaturan</h2>
                <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Masukkan kode terbaru yang ditampilkan aplikasi autentikasi untuk mengaktifkan verifikasi dua langkah.</p>

                <form wire:submit="activate" class="mt-5 space-y-4">
                    <div>
                        <label for="code" class="ui-label">Kode verifikasi (6 digit)</label>
                        <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code" class="ui-input font-mono text-base tracking-[0.28em]" placeholder="000000" required aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}">
                        @error('code') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full">
                        <span wire:loading.remove wire:target="activate">Aktifkan verifikasi dua langkah</span>
                        <span wire:loading wire:target="activate">Memeriksa kode...</span>
                    </button>
                </form>
            </section>
        </div>
    </section>
</div>
