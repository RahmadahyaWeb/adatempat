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

<div class="site-container grid max-w-6xl items-start gap-6 py-6 sm:gap-10 sm:py-10 lg:grid-cols-[minmax(0,.9fr)_minmax(25rem,1fr)] lg:gap-14 lg:py-14">
    <section class="order-1 mx-auto w-full max-w-xl lg:order-2">
        <div class="ui-panel p-5 sm:p-8 lg:p-10">
            <p class="eyebrow"><span class="eyebrow-dot"></span> Akun Adatempat</p>
            <h1 class="mt-3 text-3xl font-extrabold leading-tight tracking-[-0.055em] sm:text-4xl">Selamat datang kembali.</h1>
            <p class="mt-3 text-sm leading-6 text-[var(--ink-soft)] sm:text-base">Masuk untuk melanjutkan aktivitas dengan akun Adatempat-mu.</p>

            @if (session('status'))
                <div role="status" class="status-note status-success mt-5">{{ session('status') }}</div>
            @endif

            <form wire:submit="login" class="mt-6 space-y-5 sm:mt-7">
                <div>
                    <label for="email" class="ui-label">Alamat email</label>
                    <input id="email" type="email" wire:model="email" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" required class="ui-input" placeholder="nama@email.com" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                    @error('email') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label for="password" class="ui-label !mb-0">Password</label>
                        <a href="{{ route('password.request') }}" class="text-xs font-bold text-[var(--brand-forest)] underline-offset-4 hover:underline">Lupa password?</a>
                    </div>
                    <input id="password" type="password" wire:model="password" autocomplete="current-password" required class="ui-input" placeholder="Masukkan password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                    @error('password') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <label class="flex cursor-pointer items-start gap-3 text-sm leading-6 text-[var(--ink-soft)]">
                    <input type="checkbox" wire:model="remember" class="mt-1 size-4 shrink-0 rounded border-[var(--border-strong)] accent-[var(--brand-forest)]">
                    <span>Ingat saya di perangkat ini</span>
                </label>
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full">
                    <span wire:loading.remove wire:target="login">Masuk ke Adatempat</span>
                    <span wire:loading wire:target="login">Memeriksa akun...</span>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M4 12h15m-6-6 6 6-6 6"/></svg>
                </button>
            </form>

            <p class="mt-6 border-t border-[var(--border)] pt-5 text-center text-sm leading-6 text-[var(--ink-soft)]">
                Belum punya akun?
                <a href="{{ route('register') }}" class="font-bold text-[var(--brand-forest)] underline-offset-4 hover:underline">Buat akun</a>
            </p>
        </div>
        <p class="mt-4 px-3 text-center text-xs leading-5 text-[var(--muted)]">Gunakan email dan password yang terdaftar. Akun yang tidak aktif tidak dapat digunakan untuk masuk.</p>
    </section>

    <aside class="auth-promo order-2 hidden min-h-[28rem] flex-col justify-between p-7 lg:order-1 lg:flex lg:p-9">
        <div class="auth-promo-accent" aria-hidden="true"></div>
        <div class="relative">
            <p class="eyebrow !text-[var(--brand-lime)]"><span class="eyebrow-dot !bg-[var(--brand-lime)]"></span> Adatempat / Temukan tempat</p>
        </div>
        <div class="relative max-w-md">
            <p class="text-xs font-bold uppercase tracking-[0.1em] text-white/65">Untuk berbagai kebutuhan</p>
            <h2 class="mt-4 text-4xl font-extrabold leading-[1.12] tracking-[-0.055em] text-white xl:text-5xl">Pilihan tempat yang jelas, untuk keputusan yang lebih mudah.</h2>
            <p class="mt-5 max-w-sm text-sm leading-7 text-white/80">Temukan tempat berdasarkan kebutuhan, kategori, dan lokasi. Informasi yang jelas membantu kamu membandingkan pilihan.</p>
        </div>
        <div class="relative flex flex-wrap gap-2 text-xs font-semibold text-white/90">
            <span class="rounded-md border border-white/25 px-3 py-2">Pencarian tempat</span>
            <span class="rounded-md border border-white/25 px-3 py-2">Kategori</span>
            <span class="rounded-md border border-white/25 px-3 py-2">Lokasi</span>
        </div>
    </aside>
</div>