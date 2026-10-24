<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component {
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $validated = $this->validate(
            [
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string'],
                'remember' => ['boolean'],
            ],
            [
                'email.required' => 'Alamat email wajib diisi.',
                'email.email' => 'Masukkan alamat email yang valid.',
                'password.required' => 'Password wajib diisi.',
            ],
        );

        $key = Str::lower($validated['email']) . '|' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $authenticated = Auth::attempt(
            [
                'email' => Str::lower($validated['email']),
                'password' => $validated['password'],
                'status' => 'active',
            ],
            $this->remember,
        );

        if (!$authenticated) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => 'Email, password, atau status akun tidak valid.',
            ]);
        }

        RateLimiter::clear($key);

        // Hapus penanda MFA dari sesi sebelumnya.
        request()
            ->session()
            ->forget(['admin_mfa_verified_user_id', 'admin_mfa_verified_at']);

        request()->session()->regenerate();

        $user = Auth::user();

        // MFA wajib untuk super_admin di production.
        if ($user->isSuperAdmin() && app()->environment('production')) {
            if (!$user->mfa_secret || !$user->mfa_enabled_at) {
                $this->redirect(route('mfa.setup'), navigate: false);

                return;
            }

            $this->redirect(route('mfa.challenge'), navigate: false);

            return;
        }

        // Pertahankan alur verifikasi email untuk owner.
        if ($user->isOwner() && !$user->hasVerifiedEmail()) {
            $this->redirect(route('verification.notice'), navigate: false);

            return;
        }

        $this->redirectIntended(default: route('home'), navigate: false);
    }
};
?>

<div class="min-h-screen bg-[var(--bg-canvas)] px-4 py-12 text-[var(--text-primary)] sm:px-6">
    <div class="mx-auto w-full max-w-md">
        <div class="mt-10 rounded-2xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] p-6 shadow-sm sm:p-8">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--accent-primary)]">
                Selamat datang kembali
            </p>

            <h1 class="mt-3 font-serif text-3xl tracking-tight">
                Masuk ke akun Anda.
            </h1>

            <p class="mt-2 text-sm leading-6 text-[var(--text-secondary)]">
                Masuk untuk melanjutkan aktivitas Anda di Adatempat.
            </p>

            @if (session('status'))
                <div role="status"
                    class="mt-5 rounded-xl border border-[var(--border-subtle)] bg-[var(--accent-sage-bg)] p-3 text-sm leading-6 text-[var(--accent-sage)]">
                    {{ session('status') }}
                </div>
            @endif

            <form wire:submit="login" class="mt-7 space-y-5">
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium">
                        Alamat email
                    </label>

                    <input id="email" type="email" wire:model="email" autocomplete="username" required autofocus
                        class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--bg-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--accent-primary)] focus:ring-2 focus:ring-[var(--accent-primary)]/15"
                        placeholder="nama@email.com" aria-describedby="email-error">

                    @error('email')
                        <p id="email-error" role="alert" class="mt-1.5 text-sm text-red-700">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label for="password" class="text-sm font-medium">
                            Password
                        </label>

                        <a href="{{ route('password.request') }}"
                            class="text-sm font-medium text-[var(--accent-primary)] underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent-primary)]">
                            Lupa password?
                        </a>
                    </div>

                    <input id="password" type="password" wire:model="password" autocomplete="current-password" required
                        class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--bg-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--accent-primary)] focus:ring-2 focus:ring-[var(--accent-primary)]/15"
                        placeholder="Masukkan password">

                    @error('password')
                        <p role="alert" class="mt-1.5 text-sm text-red-700">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-[var(--text-secondary)]">
                    <input type="checkbox" wire:model="remember"
                        class="h-4 w-4 rounded border-[var(--border-strong)] accent-[var(--accent-primary)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent-primary)]">

                    Ingat saya
                </label>

                <button type="submit" wire:loading.attr="disabled"
                    class="w-full rounded-xl bg-[var(--accent-primary)] px-4 py-3.5 text-sm font-semibold text-white transition hover:bg-[var(--accent-primary-hover)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent-primary)] disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="login">
                        Masuk
                    </span>

                    <span wire:loading wire:target="login">
                        Memeriksa akun...
                    </span>
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-[var(--text-secondary)]">
                Belum punya akun?

                <a href="{{ route('register') }}"
                    class="font-semibold text-[var(--accent-primary)] underline-offset-4 hover:underline">
                    Daftar
                </a>
            </p>
        </div>
    </div>
</div>
