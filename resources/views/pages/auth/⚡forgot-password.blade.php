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

<div class="site-container grid max-w-6xl items-center gap-8 py-8 sm:py-12 lg:min-h-[calc(100vh-9rem)] lg:grid-cols-[minmax(0,.9fr)_minmax(22rem,1fr)] lg:gap-14 lg:py-14">
    <aside class="relative hidden min-h-[30rem] overflow-hidden rounded-[1.75rem] bg-[#203E2A] lg:block">
        <img src="https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=1100&q=80" alt="" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover opacity-75">
        <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-t from-[#121210]/90 via-[#121210]/20 to-transparent"></div>
        <div class="relative flex min-h-[30rem] flex-col justify-end p-8 xl:p-10">
            <p class="font-metadata text-[10px] uppercase tracking-[0.16em] text-white/70">Akses akun</p>
            <h2 class="mt-3 max-w-sm font-editorial text-5xl leading-[.98] text-white xl:text-6xl">Kita bantu kamu kembali.</h2>
            <p class="mt-4 max-w-sm text-sm leading-7 text-white/75">Tautan pemulihan akan dikirim ke email yang kamu masukkan jika akun terdaftar.</p>
        </div>
    </aside>

    <section class="mx-auto w-full max-w-lg">
        <div class="ui-surface p-6 sm:p-8 lg:p-10">
            <p class="editorial-eyebrow text-[var(--accent-primary)]">Pemulihan akun</p>
            <h1 class="mt-4 font-editorial text-4xl leading-tight tracking-[-0.035em] sm:text-5xl">Lupa password?</h1>
            <p class="mt-3 text-sm leading-6 text-[var(--text-secondary)] sm:text-base">Masukkan email akunmu. Jika terdaftar, kami akan mengirim tautan untuk membuat password baru.</p>

            @if (session('status'))
                <div role="status" class="mt-6 rounded-xl border border-[#C8D9CA] bg-[var(--accent-sage-bg)] p-4 text-sm leading-6 text-[var(--accent-sage)]">{{ session('status') }}</div>
            @endif

            <form wire:submit="sendResetLink" class="mt-7 space-y-5">
                <div>
                    <label for="email" class="ui-label">Alamat email</label>
                    <input id="email" type="email" wire:model="email" autocomplete="email" required class="ui-input" placeholder="nama@email.com">
                    @error('email')
                        <p role="alert" class="mt-2 text-sm leading-5 text-[#B42318]">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" class="ui-button ui-button-primary w-full">
                    <span wire:loading.remove wire:target="sendResetLink">Kirim tautan reset</span>
                    <span wire:loading wire:target="sendResetLink">Mengirim...</span>
                </button>
            </form>

            <p class="mt-7 border-t border-[var(--border-subtle)] pt-5 text-center text-sm leading-6 text-[var(--text-secondary)]">
                Ingat password-mu?
                <a href="{{ route('login') }}" class="font-semibold text-[var(--accent-primary)] underline-offset-4 hover:underline">Kembali masuk</a>
            </p>
        </div>
    </section>
</div>
