<?php

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component {
    public function resend(): void
    {
        $user = auth()->user();

        abort_unless($user, 403);

        if ($user->hasVerifiedEmail()) {
            $this->redirect(route('home'), navigate: false);

            return;
        }

        $key = 'email-verification-resend:' . $user->getAuthIdentifier();

        if (RateLimiter::tooManyAttempts($key, 6)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'resend' => "Terlalu banyak permintaan. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        RateLimiter::hit($key, 60);

        $user->sendEmailVerificationNotification();

        session()->flash('status', 'Tautan verifikasi baru telah dikirim ke email Anda.');
    }
};
?>

<div class="site-container grid max-w-6xl items-center gap-8 py-8 sm:py-12 lg:min-h-[calc(100vh-9rem)] lg:grid-cols-[minmax(0,.9fr)_minmax(22rem,1fr)] lg:gap-14 lg:py-14">
    <aside class="relative hidden min-h-[30rem] overflow-hidden rounded-[1.75rem] bg-[#203E2A] lg:block">
        <img src="https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=1100&q=80" alt="" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover opacity-75">
        <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-t from-[#121210]/90 via-[#121210]/20 to-transparent"></div>
        <div class="relative flex min-h-[30rem] flex-col justify-end p-8 xl:p-10">
            <p class="font-metadata text-[10px] uppercase tracking-[0.16em] text-white/70">Satu langkah lagi</p>
            <h2 class="mt-3 max-w-sm font-editorial text-5xl leading-[.98] text-white xl:text-6xl">Pastikan akun ini milikmu.</h2>
            <p class="mt-4 max-w-sm text-sm leading-7 text-white/75">Verifikasi email membantu menjaga akses akun tetap berada di tangan yang tepat.</p>
        </div>
    </aside>

    <section class="mx-auto w-full max-w-lg">
        <div class="ui-surface p-6 sm:p-8 lg:p-10">
            <span class="flex size-12 items-center justify-center rounded-2xl bg-[var(--accent-sage-bg)] text-[var(--accent-sage)]">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="size-6"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
            </span>
            <p class="editorial-eyebrow mt-6 text-[var(--accent-primary)]">Verifikasi email</p>
            <h1 class="mt-4 font-editorial text-4xl leading-tight tracking-[-0.035em] sm:text-5xl">Periksa kotak masukmu.</h1>
            <p class="mt-3 text-sm leading-6 text-[var(--text-secondary)] sm:text-base">Kami perlu memverifikasi alamat email sebelum proses akun dilanjutkan. Buka tautan yang dikirim ke email terdaftar.</p>

            @if (session('status'))
                <div role="status" class="mt-6 rounded-xl border border-[#C8D9CA] bg-[var(--accent-sage-bg)] p-4 text-sm leading-6 text-[var(--accent-sage)]">{{ session('status') }}</div>
            @endif
            @error('resend')
                <p role="alert" class="mt-4 text-sm leading-5 text-[#B42318]">{{ $message }}</p>
            @enderror

            <button type="button" wire:click="resend" wire:loading.attr="disabled" class="ui-button ui-button-primary mt-6 w-full">
                <span wire:loading.remove wire:target="resend">Kirim ulang email verifikasi</span>
                <span wire:loading wire:target="resend">Mengirim...</span>
            </button>
        </div>
    </section>
</div>
