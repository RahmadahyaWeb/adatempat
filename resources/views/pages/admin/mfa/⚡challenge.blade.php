<?php

use App\Services\MfaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

new class extends Component {
    public string $code = '';

    public function verify(MfaService $mfaService): void
    {
        $this->validate(
            [
                'code' => ['required', 'digits:6'],
            ],
            [
                'code.required' => 'Masukkan kode authenticator.',
                'code.digits' => 'Kode harus terdiri dari enam digit.',
            ],
        );

        $user = Auth::user();

        abort_unless($user?->isSuperAdmin(), 403);

        if (!$user->mfa_secret || !$user->mfa_enabled_at) {
            $this->redirectRoute('mfa.setup');

            return;
        }

        $key = 'admin-mfa:' . $user->getKey() . ':' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            $this->addError('code', "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.");

            return;
        }

        RateLimiter::hit($key, 60);

        if (!$mfaService->verify($user->mfa_secret, $this->code)) {
            $this->addError('code', 'Kode tidak valid atau sudah kedaluwarsa.');

            return;
        }

        RateLimiter::clear($key);

        session([
            'admin_mfa_verified_user_id' => (string) $user->getKey(),
            'admin_mfa_verified_at' => now()->timestamp,
        ]);

        $this->redirectRoute('home');
    }
};
?>
<div class="site-container py-8 sm:py-12 lg:py-16">
    <section class="auth-shell">
        <div class="mb-5 text-center sm:mb-6">
            <p class="auth-page-kicker">Keamanan administrator</p>
            <h1 class="auth-page-title">Verifikasi akses</h1>
            <p class="auth-page-description mx-auto max-w-md">Konfirmasi bahwa kamu memiliki akses ke aplikasi autentikasi sebelum melanjutkan ke area Super Admin.</p>
        </div>

        <div class="auth-card">
            <div class="flex size-12 items-center justify-center rounded-xl bg-[var(--surface-lime)] text-[var(--brand-forest)]">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 14v3"/></svg>
            </div>

            <h2 class="mt-5 text-xl font-extrabold tracking-[-0.04em] sm:text-2xl">Masukkan kode verifikasi</h2>
            <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Buka aplikasi autentikasi yang sudah terhubung dan masukkan kode enam digit terbaru.</p>

            <form wire:submit="verify" class="mt-6 space-y-5">
                <div>
                    <label for="code" class="ui-label">Kode verifikasi (6 digit)</label>
                    <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code" class="ui-input font-mono text-lg tracking-[0.35em]" placeholder="000000" required autofocus aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}">
                    @error('code') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full">
                    <span wire:loading.remove wire:target="verify">Verifikasi dan lanjutkan</span>
                    <span wire:loading wire:target="verify">Memeriksa kode...</span>
                </button>
            </form>

            <div class="mt-6 flex items-start gap-3 border-t border-[var(--border)] pt-5">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0 text-[var(--brand-forest)]"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3"/></svg>
                <p class="text-xs leading-5 text-[var(--muted)]">Jangan bagikan kode kepada siapa pun. Jika kode ditolak, pastikan waktu perangkat sudah benar dan gunakan kode terbaru.</p>
            </div>
        </div>
    </section>
</div>
