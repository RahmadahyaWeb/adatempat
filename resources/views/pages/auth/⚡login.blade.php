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
<div class="site-container py-8 sm:py-12 lg:py-16">
    <section class="auth-shell">
        <div class="mb-5 text-center sm:mb-6">
            <p class="auth-page-kicker">Temukan tempat yang tepat</p>
            <h1 class="auth-page-title">Masuk ke Adatempat</h1>
            <p class="auth-page-description mx-auto max-w-md">Lanjutkan pencarian tempat atau kelola informasi usaha melalui akunmu.</p>
        </div>

        <div class="auth-card">
            @if (session('status'))
                <div role="status" class="status-note status-success mb-5">{{ session('status') }}</div>
            @endif

            <form wire:submit="login" class="space-y-5">
                <div>
                    <label for="email" class="ui-label">Alamat email</label>
                    <input id="email" type="email" wire:model="email" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" required class="ui-input" placeholder="nama@email.com" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                    @error('email') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label for="password" class="ui-label !mb-0">Kata sandi</label>
                        <a href="{{ route('password.request') }}" class="text-xs font-bold text-[var(--brand-forest)] underline-offset-4 hover:underline">Lupa kata sandi?</a>
                    </div>
                    <input id="password" type="password" wire:model="password" autocomplete="current-password" required class="ui-input" placeholder="Masukkan kata sandi" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                    @error('password') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>

                <label class="flex cursor-pointer items-start gap-3 text-sm leading-6 text-[var(--ink-soft)]">
                    <input type="checkbox" wire:model="remember" class="mt-1 size-4 shrink-0 rounded border-[var(--border-strong)] accent-[var(--brand-forest)]">
                    <span>Ingat saya di perangkat ini</span>
                </label>

                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full">
                    <span wire:loading.remove wire:target="login">Masuk</span>
                    <span wire:loading wire:target="login">Memeriksa akun...</span>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M4 12h15m-6-6 6 6-6 6"/></svg>
                </button>
            </form>

            <p class="mt-6 border-t border-[var(--border)] pt-5 text-center text-sm leading-6 text-[var(--ink-soft)]">
                Belum punya akun?
                <a href="{{ route('register') }}" class="font-bold text-[var(--brand-forest)] underline-offset-4 hover:underline">Buat akun</a>
            </p>
        </div>

        <p class="mx-auto mt-4 max-w-md px-3 text-center text-xs leading-5 text-[var(--muted)]">Gunakan email yang terdaftar dan pastikan akunmu aktif. Jika lupa kata sandi, gunakan tautan pemulihan di atas.</p>
    </section>
</div>
