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
<div class="auth-v2-page">
    <section class="site-container auth-v2-shell">
        <div class="auth-v2-story">
            <a href="{{ route('home') }}" class="auth-v2-back">← Kembali menjelajah</a>
            <div>
                <p class="eyebrow"><span class="eyebrow-dot"></span> Selamat datang kembali</p>
                <h1>Masuk dan lanjutkan dari tempat terakhir.</h1>
                <p>Gunakan akun Adatempat untuk melanjutkan booking, melihat pesanan, atau mengelola usaha.</p>
            </div>
            <div class="auth-v2-story-note">Jika kamu datang dari proses booking, setelah login kamu akan dikembalikan ke checkout yang sama.</div>
        </div>
        <div class="auth-v2-panel">
            <div class="auth-v2-panel-head">
                <h2>Masuk</h2>
                <p>Belum punya akun? <a href="{{ route('register') }}">Buat akun</a></p>
            </div>
            @if (session('status')) <div role="status" class="status-note status-success">{{ session('status') }}</div> @endif
            <form wire:submit="login" class="auth-v2-form">
                <div><label for="email" class="ui-label">Alamat email</label><input id="email" type="email" wire:model="email" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" required class="ui-input" placeholder="nama@email.com">@error('email') <p role="alert" class="ui-error">{{ $message }}</p> @enderror</div>
                <div>
                    <div class="auth-v2-label-row"><label for="password" class="ui-label !mb-0">Kata sandi</label><a href="{{ route('password.request') }}">Lupa kata sandi?</a></div>
                    <input id="password" type="password" wire:model="password" autocomplete="current-password" required class="ui-input" placeholder="Masukkan kata sandi">@error('password') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <label class="auth-v2-check"><input type="checkbox" wire:model="remember"><span>Ingat saya di perangkat ini</span></label>
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full"><span wire:loading.remove wire:target="login">Masuk</span><span wire:loading wire:target="login">Memeriksa akun...</span></button>
            </form>
        </div>
    </section>
</div>