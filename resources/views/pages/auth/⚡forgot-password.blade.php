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
<div class="auth-v2-page">
    <section class="site-container auth-v2-shell">
        <div class="auth-v2-story">
            <a href="{{ route('login') }}" class="auth-v2-back">← Kembali masuk</a>
            <div><p class="eyebrow"><span class="eyebrow-dot"></span> Pemulihan akun</p><h1>Kembalikan akses ke akunmu.</h1><p>Kami akan mengirim instruksi jika alamat email tersebut terhubung dengan akun Adatempat.</p></div>
            <div class="auth-v2-story-note">Demi privasi, kami tidak akan mengungkap apakah sebuah email terdaftar.</div>
        </div>
        <div class="auth-v2-panel">
            <div class="auth-v2-panel-head"><h2>Lupa kata sandi</h2><p>Masukkan email akunmu.</p></div>
            @if (session('status')) <div role="status" class="status-note status-success">{{ session('status') }}</div> @endif
            <form wire:submit="sendResetLink" class="auth-v2-form">
                <div><label for="email" class="ui-label">Alamat email</label><input id="email" type="email" wire:model="email" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" required class="ui-input" placeholder="nama@email.com">@error('email') <p role="alert" class="ui-error">{{ $message }}</p> @enderror</div>
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full"><span wire:loading.remove wire:target="sendResetLink">Kirim instruksi</span><span wire:loading wire:target="sendResetLink">Mengirim...</span></button>
            </form>
        </div>
    </section>
</div>