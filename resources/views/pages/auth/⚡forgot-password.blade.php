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

<div class="min-h-screen bg-[var(--bg-canvas)] px-4 py-12 text-[var(--text-primary)] sm:px-6">
    <div class="mx-auto max-w-md">
        <div class="rounded-2xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] p-6 shadow-sm sm:p-8">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--accent-primary)]">
                Pemulihan akun
            </p>

            <h1 class="mt-3 font-serif text-3xl">
                Lupa password?
            </h1>

            <p class="mt-3 text-sm leading-6 text-[var(--text-secondary)]">
                Masukkan email akun Anda. Jika terdaftar, kami akan mengirimkan tautan untuk membuat password baru.
            </p>

            @if (session('status'))
                <p role="status"
                    class="mt-5 rounded-xl bg-[var(--accent-sage-bg)] p-3 text-sm leading-6 text-[var(--accent-sage)]">
                    {{ session('status') }}
                </p>
            @endif

            <form wire:submit="sendResetLink" class="mt-6 space-y-4">
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium">
                        Alamat email
                    </label>

                    <input id="email" type="email" wire:model="email" autocomplete="email" required
                        class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--bg-surface)] px-4 py-3 text-sm outline-none focus:border-[var(--accent-primary)] focus:ring-2 focus:ring-[var(--accent-primary)]/15"
                        placeholder="nama@email.com">

                    @error('email')
                        <p role="alert" class="mt-1.5 text-sm text-red-700">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled"
                    class="w-full rounded-xl bg-[var(--accent-primary)] px-4 py-3 text-sm font-semibold text-white hover:bg-[var(--accent-primary-hover)] disabled:opacity-60">
                    <span wire:loading.remove wire:target="sendResetLink">
                        Kirim tautan reset
                    </span>

                    <span wire:loading wire:target="sendResetLink">
                        Memproses...
                    </span>
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-[var(--text-secondary)]">
                Ingat password?
                <a href="{{ route('login') }}" class="font-semibold text-[var(--accent-primary)] hover:underline">
                    Kembali masuk
                </a>
            </p>
        </div>
    </div>
</div>
