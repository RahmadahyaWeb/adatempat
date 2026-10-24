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

<div class="min-h-screen bg-[var(--bg-canvas)] px-4 py-16 text-[var(--text-primary)]">
    <div class="mx-auto max-w-md rounded-2xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] p-8">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--accent-primary)]">
            Verifikasi email
        </p>

        <h1 class="mt-3 font-serif text-3xl">
            Periksa kotak masuk Anda.
        </h1>

        <p class="mt-4 text-sm leading-6 text-[var(--text-secondary)]">
            Kami perlu memverifikasi alamat email Anda sebelum proses akun dilanjutkan. Buka tautan verifikasi yang
            dikirim ke email terdaftar.
        </p>

        @if (session('status'))
            <p role="status" class="mt-4 rounded-xl bg-[var(--accent-sage-bg)] p-3 text-sm text-[var(--accent-sage)]">
                {{ session('status') }}
            </p>
        @endif

        @error('resend')
            <p role="alert" class="mt-4 text-sm text-red-700">{{ $message }}</p>
        @enderror

        <button type="button" wire:click="resend" wire:loading.attr="disabled"
            class="mt-6 w-full rounded-xl bg-[var(--accent-primary)] px-4 py-3 text-sm font-semibold text-white hover:bg-[var(--accent-primary-hover)] disabled:opacity-60">
            Kirim ulang email verifikasi
        </button>
    </div>
</div>
