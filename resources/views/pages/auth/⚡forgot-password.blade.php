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
            session()->flash('status', 'Jika email terdaftar, instruksi reset password akan dikirim ke alamat tersebut.');

            return;
        }

        if ($status === Password::RESET_THROTTLED) {
            $this->addError('email', 'Permintaan reset terlalu sering. Silakan tunggu sebelum mencoba lagi.');

            return;
        }

        // Pesan generik untuk tidak membocorkan keberadaan akun.
        session()->flash('status', 'Jika email terdaftar, instruksi reset password akan dikirim ke alamat tersebut.');
    }
};
?>

<div class="site-container grid max-w-6xl items-start gap-6 py-6 sm:gap-10 sm:py-10 lg:grid-cols-[minmax(0,.9fr)_minmax(25rem,1fr)] lg:gap-14 lg:py-14">
    <section class="order-1 mx-auto w-full max-w-xl lg:order-2">
        <div class="ui-panel p-5 sm:p-8 lg:p-10">
            <p class="eyebrow"><span class="eyebrow-dot"></span> Pemulihan akun</p>
            <h1 class="mt-3 text-3xl font-extrabold leading-tight tracking-[-0.055em] sm:text-4xl">Lupa password?</h1>
            <p class="mt-3 text-sm leading-6 text-[var(--ink-soft)] sm:text-base">Masukkan alamat email akunmu. Jika terdaftar, instruksi untuk membuat password baru akan dikirim ke alamat tersebut.</p>

            @if (session('status'))
                <div role="status" class="status-note status-success mt-5">{{ session('status') }}</div>
            @endif

            <form wire:submit="sendResetLink" class="mt-6 space-y-5">
                <div>
                    <label for="email" class="ui-label">Alamat email</label>
                    <input id="email" type="email" wire:model="email" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" required class="ui-input" placeholder="nama@email.com" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                    @error('email') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full">
                    <span wire:loading.remove wire:target="sendResetLink">Kirim tautan reset</span>
                    <span wire:loading wire:target="sendResetLink">Mengirim...</span>
                </button>
            </form>

            <p class="mt-6 border-t border-[var(--border)] pt-5 text-center text-sm leading-6 text-[var(--ink-soft)]">
                Ingat password-mu?
                <a href="{{ route('login') }}" class="font-bold text-[var(--brand-forest)] underline-offset-4 hover:underline">Kembali masuk</a>
            </p>
        </div>
    </section>

    <aside class="auth-promo order-2 hidden min-h-[25rem] flex-col justify-between p-7 lg:order-1 lg:flex lg:p-9">
        <div class="auth-promo-accent" aria-hidden="true"></div>
        <p class="relative eyebrow !text-[var(--brand-lime)]"><span class="eyebrow-dot !bg-[var(--brand-lime)]"></span> Akses akun</p>
        <div class="relative max-w-md">
            <h2 class="text-4xl font-extrabold leading-[1.12] tracking-[-0.055em] text-white xl:text-5xl">Kita bantu kamu kembali masuk.</h2>
            <p class="mt-5 text-sm leading-7 text-white/80">Untuk menjaga privasi, pesan konfirmasi tidak mengungkap apakah alamat email tertentu sudah terdaftar.</p>
        </div>
        <p class="relative border-t border-white/20 pt-5 text-xs leading-6 text-white/70">Gunakan email yang pernah kamu daftarkan ke Adatempat.</p>
    </aside>
</div>