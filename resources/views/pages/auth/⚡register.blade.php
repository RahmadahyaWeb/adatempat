<?php

use App\Models\LegalDocument;
use App\Models\TermsAcceptance;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component {
    public string $registrationType = 'customer';

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $acceptedLegal = false;

    public function selectRegistrationType(string $type): void
    {
        if (!in_array($type, ['customer', 'owner'], true)) {
            return;
        }

        $this->registrationType = $type;
        $this->resetValidation();
    }

    public function registerCustomer(): void
    {
        $this->createAccount('customer');
    }

    public function registerOwner(): void
    {
        $this->createAccount('owner');
    }

    private function createAccount(string $role): void
    {
        if (!in_array($role, ['customer', 'owner'], true)) {
            abort(403);
        }

        $this->registrationType = $role;

        // Batasi percobaan registrasi berdasarkan alamat IP.
        $rateLimitKey = 'registration:' . (string) request()->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan registrasi. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        RateLimiter::hit($rateLimitKey, 3600);

        $validated = $this->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'confirmed', Password::defaults()],
                'acceptedLegal' => ['accepted'],
            ],
            [
                'name.required' => 'Nama wajib diisi.',
                'name.max' => 'Nama maksimal 255 karakter.',
                'email.required' => 'Alamat email wajib diisi.',
                'email.email' => 'Masukkan alamat email yang valid.',
                'email.unique' => 'Email ini sudah terdaftar. Silakan masuk.',
                'password.required' => 'Password wajib diisi.',
                'password.confirmed' => 'Konfirmasi password tidak cocok.',
                'acceptedLegal.accepted' => 'Persetujuan Terms dan Privacy wajib diberikan.',
            ],
        );

        $documents = LegalDocument::query()
            ->published()
            ->whereIn('type', ['terms_and_conditions', 'privacy_policy'])
            ->get()
            ->keyBy('type');

        if (!$documents->has('terms_and_conditions') || !$documents->has('privacy_policy')) {
            $this->addError('legal', 'Registrasi belum tersedia karena dokumen Terms dan Privacy ' . 'belum dipublikasikan secara resmi. Silakan coba kembali nanti.');

            return;
        }

        $user = DB::transaction(function () use ($validated, $documents, $role) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $role,
                'password' => $validated['password'],
            ]);

            foreach (['terms_and_conditions', 'privacy_policy'] as $type) {
                TermsAcceptance::create([
                    'user_id' => $user->id,
                    'legal_document_id' => $documents[$type]->id,
                    'context' => 'registration',
                    'accepted_at' => now(),
                    'ip_address' => request()->ip(),
                    'user_agent' => substr((string) request()->userAgent(), 0, 1000),
                ]);
            }

            return $user;
        });

        event(new Registered($user));

        auth()->login($user);

        request()->session()->regenerate();

        $this->redirect($user->hasVerifiedEmail() ? route('home') : route('verification.notice'), navigate: false);
    }
};
?>
<div class="auth-v2-page">
    <section class="site-container auth-v2-shell auth-v2-shell-register">
        <div class="auth-v2-story">
            <a href="{{ route('home') }}" class="auth-v2-back">← Kembali menjelajah</a>
            <div><p class="eyebrow"><span class="eyebrow-dot"></span> Bergabung dengan Adatempat</p><h1>Satu akun, sesuai kebutuhanmu.</h1><p>Pilih sebagai pelanggan untuk memesan venue, atau sebagai pemilik untuk mengelola usaha dan tempat.</p></div>
            <div class="auth-v2-story-note">Jika kamu membuat akun saat proses booking, intended checkout tetap dipertahankan setelah verifikasi.</div>
        </div>
        <div class="auth-v2-panel auth-v2-register-panel">
            @error('legal') <div role="alert" class="status-note">{{ $message }}</div> @enderror
            <div class="auth-v2-panel-head"><h2>Buat akun</h2><p>Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p></div>

            <div class="auth-v2-role-switch" role="group" aria-label="Jenis akun">
                <button type="button" wire:click="selectRegistrationType('customer')" aria-pressed="{{ $registrationType === 'customer' ? 'true' : 'false' }}">Pelanggan</button>
                <button type="button" wire:click="selectRegistrationType('owner')" aria-pressed="{{ $registrationType === 'owner' ? 'true' : 'false' }}">Pemilik usaha</button>
            </div>

            <div class="auth-v2-role-note">
                {{ $registrationType === 'customer' ? 'Untuk mencari, memesan, dan mengelola booking venue.' : 'Untuk mempublikasikan usaha, venue, harga, dan menerima booking.' }}
            </div>

            <form wire:submit="{{ $registrationType === 'customer' ? 'registerCustomer' : 'registerOwner' }}" class="auth-v2-form">
                <div><label for="register-name" class="ui-label">{{ $registrationType === 'customer' ? 'Nama lengkap' : 'Nama pemilik' }}</label><input id="register-name" type="text" wire:model="name" autocomplete="name" required maxlength="255" class="ui-input" placeholder="Nama lengkap">@error('name') <p role="alert" class="ui-error">{{ $message }}</p> @enderror</div>
                <div><label for="register-email" class="ui-label">Alamat email</label><input id="register-email" type="email" wire:model="email" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" required maxlength="255" class="ui-input" placeholder="nama@email.com">@error('email') <p role="alert" class="ui-error">{{ $message }}</p> @enderror</div>
                <div class="auth-v2-password-grid">
                    <div><label for="register-password" class="ui-label">Kata sandi</label><input id="register-password" type="password" wire:model="password" autocomplete="new-password" required class="ui-input" placeholder="Buat kata sandi">@error('password') <p role="alert" class="ui-error">{{ $message }}</p> @enderror</div>
                    <div><label for="register-password-confirmation" class="ui-label">Konfirmasi</label><input id="register-password-confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="ui-input" placeholder="Ulangi kata sandi"></div>
                </div>
                <label class="auth-v2-check"><input type="checkbox" wire:model="acceptedLegal" required><span>Saya menyetujui Syarat dan Ketentuan serta Kebijakan Privasi Adatempat.</span></label>
                @error('acceptedLegal') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full"><span wire:loading.remove>{{ $registrationType === 'customer' ? 'Buat akun pelanggan' : 'Buat akun pemilik' }}</span><span wire:loading>Menyiapkan akun...</span></button>
            </form>
        </div>
    </section>
</div>