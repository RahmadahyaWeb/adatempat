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

<div class="site-container grid max-w-6xl items-center gap-8 py-8 sm:py-12 lg:min-h-[calc(100vh-9rem)] lg:grid-cols-[minmax(0,.9fr)_minmax(22rem,1fr)] lg:gap-14 lg:py-14">
    <aside class="relative hidden min-h-[34rem] overflow-hidden rounded-[1.75rem] bg-[#203E2A] lg:block">
        <img src="https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=1200&q=85" alt="" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover opacity-80">
        <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-t from-[#121210]/90 via-[#121210]/20 to-[#121210]/10"></div>
        <div class="relative flex min-h-[34rem] flex-col justify-between p-8 xl:p-10">
            <p class="editorial-eyebrow text-white/80"><span class="size-2 rounded-full bg-[#9CC3A2]"></span> Adatempat / Ruang untuk rencana</p>
            <div class="max-w-md text-white">
                <p class="font-metadata text-[10px] uppercase tracking-[0.16em] text-white/65">Senang melihatmu kembali</p>
                <h2 class="mt-4 font-editorial text-5xl leading-[.98] tracking-[-0.035em] text-white xl:text-6xl">Rencanamu belum selesai.</h2>
                <p class="mt-5 max-w-sm text-sm leading-7 text-white/75">Masuk untuk kembali ke aktivitasmu dan menemukan tempat yang kamu butuhkan.</p>
            </div>
        </div>
    </aside>

    <section class="mx-auto w-full max-w-lg">
        <div class="ui-surface p-6 sm:p-8 lg:p-10">
            <p class="editorial-eyebrow text-[var(--accent-primary)]">Masuk ke akun</p>
            <h1 class="mt-4 font-editorial text-4xl leading-tight tracking-[-0.035em] sm:text-5xl">Selamat datang kembali.</h1>
            <p class="mt-3 text-sm leading-6 text-[var(--text-secondary)] sm:text-base">Masuk untuk melanjutkan aktivitasmu di Adatempat.</p>

            @if (session('status'))
                <div role="status" class="mt-6 rounded-xl border border-[#C8D9CA] bg-[var(--accent-sage-bg)] p-4 text-sm leading-6 text-[var(--accent-sage)]">
                    {{ session('status') }}
                </div>
            @endif

            <form wire:submit="login" class="mt-7 space-y-5">
                <div>
                    <label for="email" class="ui-label">Alamat email</label>
                    <input id="email" type="email" wire:model="email" autocomplete="username" required autofocus aria-describedby="email-error" class="ui-input" placeholder="nama@email.com">
                    @error('email')
                        <p id="email-error" role="alert" class="mt-2 text-sm leading-5 text-[#B42318]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label for="password" class="ui-label mb-0">Password</label>
                        <a href="{{ route('password.request') }}" class="text-xs font-semibold text-[var(--accent-primary)] underline-offset-4 hover:underline sm:text-sm">Lupa password?</a>
                    </div>
                    <input id="password" type="password" wire:model="password" autocomplete="current-password" required class="ui-input" placeholder="Masukkan password">
                    @error('password')
                        <p role="alert" class="mt-2 text-sm leading-5 text-[#B42318]">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex cursor-pointer items-start gap-3 text-sm leading-6 text-[var(--text-secondary)]">
                    <input type="checkbox" wire:model="remember" class="mt-1 size-4 rounded border-[var(--border-strong)] accent-[var(--accent-primary)]">
                    <span>Ingat saya di perangkat ini</span>
                </label>

                <button type="submit" wire:loading.attr="disabled" class="ui-button ui-button-primary w-full">
                    <span wire:loading.remove wire:target="login">Masuk</span>
                    <span wire:loading wire:target="login">Memeriksa akun...</span>
                    <svg wire:loading.remove wire:target="login" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M5 12h14m-7-7 7 7-7 7"/></svg>
                </button>
            </form>

            <p class="mt-7 border-t border-[var(--border-subtle)] pt-5 text-center text-sm leading-6 text-[var(--text-secondary)]">
                Belum punya akun?
                <a href="{{ route('register') }}" class="font-semibold text-[var(--accent-primary)] underline-offset-4 hover:underline">Buat akun</a>
            </p>
        </div>
        <p class="mt-4 text-center text-xs leading-5 text-[var(--text-muted)]">Dengan masuk, kamu bisa melanjutkan aktivitas sesuai jenis akunmu.</p>
    </section>
</div>
