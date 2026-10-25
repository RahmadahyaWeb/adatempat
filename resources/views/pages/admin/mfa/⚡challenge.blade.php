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

<div class="site-container grid max-w-5xl items-center gap-8 py-8 sm:py-12 lg:min-h-[calc(100vh-9rem)] lg:grid-cols-[minmax(0,.85fr)_minmax(0,1.15fr)] lg:gap-14 lg:py-14">
    <aside class="rounded-[1.75rem] bg-[#E9F3EC] p-6 sm:p-8 lg:p-10">
        <p class="editorial-eyebrow text-[#49634E]"><span class="size-2 rounded-full bg-[var(--accent-sage)]"></span> Ruang administrator</p>
        <h1 class="mt-5 font-editorial text-4xl leading-tight tracking-[-0.035em] text-[#203E2A] sm:text-5xl">Pastikan ini benar-benar kamu.</h1>
        <p class="mt-4 text-sm leading-7 text-[#49634E]">Gunakan kode sementara dari aplikasi authenticator yang sudah terhubung ke akun administrator.</p>
        <div class="mt-7 border-t border-[#C8D9CA] pt-5 text-xs leading-5 text-[#49634E]">Jangan membagikan kode autentikasi kepada siapa pun.</div>
    </aside>

    <section class="ui-surface p-6 sm:p-8 lg:p-10">
        <span class="flex size-12 items-center justify-center rounded-2xl bg-[var(--accent-sage-bg)] text-[var(--accent-sage)]">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="size-6"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3"/><path d="M12 14v3"/></svg>
        </span>
        <p class="editorial-eyebrow mt-6 text-[var(--accent-primary)]">Verifikasi dua faktor</p>
        <h2 class="mt-4 font-editorial text-4xl leading-tight tracking-[-0.035em] sm:text-5xl">Masukkan kode keamanan.</h2>
        <p class="mt-3 text-sm leading-6 text-[var(--text-secondary)] sm:text-base">Masukkan kode enam digit dari aplikasi authenticator untuk melanjutkan.</p>

        <form wire:submit="verify" class="mt-7 space-y-5">
            <div>
                <label for="code" class="ui-label">Kode authenticator</label>
                <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code" class="ui-input font-metadata text-lg tracking-[0.35em]" placeholder="000000" required autofocus>
                @error('code') <p role="alert" class="mt-2 text-sm leading-5 text-[#B42318]">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" class="ui-button ui-button-primary w-full">
                <span wire:loading.remove wire:target="verify">Verifikasi dan lanjutkan</span>
                <span wire:loading wire:target="verify">Memverifikasi...</span>
            </button>
        </form>

        <p class="mt-6 border-t border-[var(--border-subtle)] pt-5 text-xs leading-5 text-[var(--text-muted)]">Jika kode ditolak, periksa waktu perangkat dan gunakan kode terbaru.</p>
    </section>
</div>
