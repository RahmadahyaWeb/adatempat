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
<div class="auth-v2-page">
    <section class="site-container auth-v2-shell">
        <div class="auth-v2-story">
            <a href="{{ route('home') }}" class="auth-v2-back">← Beranda</a>
            <div><p class="eyebrow"><span class="eyebrow-dot"></span> Satu langkah lagi</p><h1>Verifikasi emailmu.</h1><p>Verifikasi diperlukan sebelum fitur akun tertentu dapat digunakan.</p></div>
            <div class="auth-v2-story-note">Setelah verifikasi berhasil, sistem akan melanjutkan ke halaman yang sebelumnya ingin kamu buka.</div>
        </div>
        <div class="auth-v2-panel">
            <div class="auth-v2-mail-icon"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></div>
            <div class="auth-v2-panel-head"><h2>Cek email</h2><p>Tautan verifikasi dikirim ke <strong>{{ auth()->user()->email }}</strong>.</p></div>
            @if (session('status')) <div role="status" class="status-note status-success">{{ session('status') }}</div> @endif
            @error('resend') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
            <div class="auth-v2-actions">
                <button type="button" wire:click="resend" wire:loading.attr="disabled" class="btn btn-primary w-full"><span wire:loading.remove wire:target="resend">Kirim ulang tautan</span><span wire:loading wire:target="resend">Mengirim...</span></button>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="btn btn-secondary w-full">Keluar dari akun</button></form>
            </div>
        </div>
    </section>
</div>