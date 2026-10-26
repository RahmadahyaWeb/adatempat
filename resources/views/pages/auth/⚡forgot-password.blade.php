<?php

use Illuminate\Support\Facades\Password;
use Livewire\Component;

new class extends Component {
    public string $email = '';

    public function sendResetLink(): void
    {
        $validated = $this->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $status = Password::sendResetLink([
            'email' => $validated['email'],
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            session()->flash('status', 'Jika email terdaftar, instruksi untuk membuat kata sandi baru akan dikirim ke alamat tersebut.');

            return;
        }

        if ($status === Password::RESET_THROTTLED) {
            $this->addError('email', 'Permintaan terlalu sering. Tunggu sebentar sebelum meminta tautan pemulihan lagi.');

            return;
        }

        // Pesan generik untuk tidak membocorkan keberadaan akun.
        session()->flash('status', 'Jika email terdaftar, instruksi untuk membuat kata sandi baru akan dikirim ke alamat tersebut.');
    }
};
?>
<div class="site-container py-8 sm:py-12 lg:py-16">
    <section class="auth-shell">
        <div class="mb-5 text-center sm:mb-6">
            <p class="auth-page-kicker">Pemulihan akun</p>
            <h1 class="auth-page-title">Lupa kata sandi?</h1>
            <p class="auth-page-description mx-auto max-w-md">Masukkan email yang terhubung dengan akun Adatempat. Jika alamat terdaftar, kami akan mengirim instruksi untuk membuat kata sandi baru.</p>
        </div>

        <div class="auth-card">
            @if (session('status'))
                <div role="status" class="status-note status-success mb-5">{{ session('status') }}</div>
            @endif

            <form wire:submit="sendResetLink" class="space-y-5">
                <div>
                    <label for="email" class="ui-label">Alamat email</label>
                    <input id="email" type="email" wire:model="email" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" required class="ui-input" placeholder="nama@email.com" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                    @error('email') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full">
                    <span wire:loading.remove wire:target="sendResetLink">Kirim instruksi pemulihan</span>
                    <span wire:loading wire:target="sendResetLink">Mengirim...</span>
                </button>
            </form>

            <p class="mt-6 border-t border-[var(--border)] pt-5 text-center text-sm leading-6 text-[var(--ink-soft)]">
                Ingat kata sandi?
                <a href="{{ route('login') }}" class="font-bold text-[var(--brand-forest)] underline-offset-4 hover:underline">Kembali masuk</a>
            </p>
        </div>

        <p class="mx-auto mt-4 max-w-md px-3 text-center text-xs leading-5 text-[var(--muted)]">Demi menjaga privasi, halaman ini tidak mengungkap apakah alamat email tertentu terdaftar.</p>
    </section>
</div>
