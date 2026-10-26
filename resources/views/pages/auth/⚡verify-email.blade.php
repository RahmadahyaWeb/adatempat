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

        session()->flash('status', 'Tautan verifikasi baru sudah dikirim ke emailmu.');
    }
};
?>
<div class="site-container py-8 sm:py-12 lg:py-16">
    <section class="auth-shell">
        <div class="mb-5 text-center sm:mb-6">
            <p class="auth-page-kicker">Verifikasi akun</p>
            <h1 class="auth-page-title">Verifikasi alamat emailmu</h1>
            <p class="auth-page-description mx-auto max-w-md">Selesaikan verifikasi agar akunmu dapat menggunakan fitur yang memerlukannya.</p>
        </div>

        <div class="auth-card">
            <div class="flex size-12 items-center justify-center rounded-xl bg-[var(--surface-lime)] text-[var(--brand-forest)]">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
            </div>

            <p class="mt-5 text-sm leading-6 text-[var(--ink-soft)]">Kami mengirim tautan verifikasi ke alamat email berikut.</p>
            <div class="mt-3 rounded-xl border border-[var(--border)] bg-[var(--surface-soft)] px-4 py-3">
                <p class="break-all text-sm font-bold text-[var(--ink)]">{{ auth()->user()->email }}</p>
            </div>
            <p class="mt-4 text-sm leading-6 text-[var(--muted)]">Buka tautan di email tersebut. Jika belum tiba, periksa folder spam sebelum meminta pengiriman ulang.</p>

            @if (session('status'))
                <div role="status" class="status-note status-success mt-5">{{ session('status') }}</div>
            @endif
            @error('resend')
                <p role="alert" class="ui-error mt-4">{{ $message }}</p>
            @enderror

            <button type="button" wire:click="resend" wire:loading.attr="disabled" class="btn btn-primary mt-6 w-full">
                <span wire:loading.remove wire:target="resend">Kirim ulang tautan verifikasi</span>
                <span wire:loading wire:target="resend">Mengirim email...</span>
            </button>

            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="btn btn-secondary w-full">Keluar dari akun</button>
            </form>

            <p class="mt-5 border-t border-[var(--border)] pt-5 text-xs leading-5 text-[var(--muted)]">Jangan bagikan tautan verifikasi kepada orang lain. Tautan hanya ditujukan untuk akunmu.</p>
        </div>
    </section>
</div>
