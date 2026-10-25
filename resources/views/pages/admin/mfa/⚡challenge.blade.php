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

<div class="site-container max-w-5xl py-6 sm:py-10 lg:py-14">
    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,.78fr)_minmax(0,1.22fr)] lg:gap-8">
        <aside class="rounded-[1.5rem] bg-[var(--brand-forest)] p-5 text-white sm:p-7 lg:sticky lg:top-24 lg:p-8">
            <p class="eyebrow !text-[var(--brand-lime)]"><span class="eyebrow-dot !bg-[var(--brand-lime)]"></span> Ruang administrator</p>
            <h1 class="mt-4 text-3xl font-extrabold leading-tight tracking-[-0.05em] text-white sm:text-4xl">Satu langkah verifikasi lagi.</h1>
            <p class="mt-4 text-sm leading-7 text-white/80">Gunakan aplikasi authenticator yang sudah terhubung ke akun untuk menghasilkan kode keamanan.</p>
            <div class="mt-6 flex items-start gap-3 border-t border-white/20 pt-5">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-5 shrink-0 text-[var(--brand-lime)]"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3"/></svg>
                <p class="text-sm leading-6 text-white/85">Jangan bagikan kode enam digit kepada siapa pun, termasuk pihak yang mengaku sebagai administrator.</p>
            </div>
        </aside>

        <section class="ui-panel min-w-0 p-5 sm:p-8 lg:p-10">
            <span class="flex size-12 items-center justify-center rounded-xl bg-[var(--brand-lime)] text-[var(--ink)]">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3"/><path d="M12 14v3"/></svg>
            </span>
            <p class="eyebrow mt-6"><span class="eyebrow-dot"></span> Verifikasi dua faktor</p>
            <h2 class="mt-3 text-3xl font-extrabold leading-tight tracking-[-0.055em] sm:text-4xl">Masukkan kode keamanan.</h2>
            <p class="mt-3 text-sm leading-6 text-[var(--ink-soft)] sm:text-base">Masukkan kode enam digit dari aplikasi authenticator untuk melanjutkan.</p>

            <form wire:submit="verify" class="mt-6 space-y-5 sm:mt-7">
                <div>
                    <label for="code" class="ui-label">Kode authenticator</label>
                    <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code" class="ui-input font-mono text-lg tracking-[0.35em]" placeholder="000000" required autofocus aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}">
                    @error('code') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full">
                    <span wire:loading.remove wire:target="verify">Verifikasi dan lanjutkan</span>
                    <span wire:loading wire:target="verify">Memverifikasi...</span>
                </button>
            </form>

            <p class="mt-6 border-t border-[var(--border)] pt-5 text-xs leading-6 text-[var(--muted)]">Jika kode ditolak, pastikan waktu perangkat authenticator sudah benar dan gunakan kode terbaru.</p>
        </section>
    </div>
</div>