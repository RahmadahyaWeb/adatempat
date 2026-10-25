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

<div class="site-container max-w-3xl py-8 sm:py-12 lg:py-16">
    <section class="ui-panel mx-auto max-w-2xl p-5 sm:p-8 lg:p-10">
        <span class="flex size-12 items-center justify-center rounded-xl bg-[var(--brand-lime)] text-[var(--ink)]">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
        </span>
        <p class="eyebrow mt-6"><span class="eyebrow-dot"></span> Verifikasi email</p>
        <h1 class="mt-3 text-3xl font-extrabold leading-tight tracking-[-0.055em] sm:text-4xl">Periksa kotak masukmu.</h1>
        <p class="mt-3 max-w-xl text-sm leading-7 text-[var(--ink-soft)] sm:text-base">Buka tautan verifikasi yang dikirim ke email terdaftar untuk menyelesaikan langkah ini. Jika email belum tiba, periksa folder spam sebelum meminta pengiriman ulang.</p>

        @if (session('status'))
            <div role="status" class="status-note status-success mt-6">{{ session('status') }}</div>
        @endif
        @error('resend')
            <p role="alert" class="ui-error mt-4">{{ $message }}</p>
        @enderror

        <button type="button" wire:click="resend" wire:loading.attr="disabled" class="btn btn-primary mt-6 w-full sm:w-auto">
            <span wire:loading.remove wire:target="resend">Kirim ulang email verifikasi</span>
            <span wire:loading wire:target="resend">Mengirim email...</span>
        </button>
        <p class="mt-5 border-t border-[var(--border)] pt-5 text-xs leading-6 text-[var(--muted)]">Untuk keamanan akun, tautan verifikasi hanya berlaku sesuai ketentuan sistem.</p>
    </section>
</div>