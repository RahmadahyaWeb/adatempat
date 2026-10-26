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
<div class="site-container py-7 sm:py-10 lg:py-14">
    <div class="mx-auto grid max-w-5xl items-start gap-8 lg:grid-cols-[minmax(0,0.9fr)_minmax(25rem,0.8fr)] lg:items-center lg:gap-16">
        <aside class="hidden max-w-lg lg:block">
            <p class="eyebrow"><span class="eyebrow-dot"></span> Temukan tempat yang tepat</p>
            <h2 class="mt-5 text-3xl font-extrabold leading-tight tracking-[-0.055em] xl:text-4xl">Cari tempat yang sesuai, dengan informasi yang jelas.</h2>
            <p class="mt-4 max-w-md text-base leading-7 text-[var(--ink-soft)]">Jelajahi pilihan berdasarkan kebutuhan, kategori, dan lokasi. Jika kamu mengelola usaha, gunakan akunmu untuk memperbarui informasi tempat.</p>
            <span aria-hidden="true" class="mt-7 block h-1 w-16 rounded-full bg-[var(--brand-lime)]"></span>
        </aside>

        <section class="mx-auto w-full max-w-md">
            <div class="mb-5 lg:hidden">
                <p class="eyebrow"><span class="eyebrow-dot"></span> Adatempat</p>
                <p class="mt-1 text-sm font-semibold tracking-wide text-[var(--muted)]">TEMUKAN TEMPAT YANG TEPAT</p>
            </div>

            <div class="ui-panel p-5 sm:p-7 lg:p-8">
                <p class="eyebrow"><span class="eyebrow-dot"></span> Masuk</p>
                <h1 class="mt-3 text-2xl font-extrabold leading-tight tracking-[-0.045em] sm:text-3xl">Masuk ke akunmu</h1>
                <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Lanjutkan pencarian tempat atau kelola informasi usaha melalui akun Adatempat.</p>

                @if (session('status'))
                    <div role="status" class="status-note status-success mt-5">{{ session('status') }}</div>
                @endif

                <form wire:submit="login" class="mt-6 space-y-5">
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

            <p class="mt-4 px-2 text-center text-xs leading-5 text-[var(--muted)]">Akun harus aktif untuk dapat digunakan. Jika mengalami kendala masuk, periksa kembali email dan kata sandimu.</p>
        </section>
    </div>
</div>
