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
<div class="site-container py-8 sm:py-12 lg:py-16">
    <section class="auth-shell auth-shell-wide">
        <div class="mb-5 text-center sm:mb-6">
            <p class="auth-page-kicker">Temukan tempat yang tepat</p>
            <h1 class="auth-page-title">Buat akun Adatempat</h1>
            <p class="auth-page-description mx-auto max-w-xl">Pilih tujuan penggunaan akunmu. Setelah itu, lengkapi data untuk memulai.</p>
        </div>

        <div class="auth-card">
            @error('legal')
                <div role="alert" class="status-note mb-5">{{ $message }}</div>
            @enderror

            <fieldset class="min-w-0">
                <legend class="mb-3 text-sm font-extrabold text-[var(--ink)]">Saya ingin...</legend>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2" aria-label="Tujuan penggunaan akun">
                    <button type="button" wire:click="selectRegistrationType('customer')" aria-pressed="{{ $registrationType === 'customer' ? 'true' : 'false' }}" class="auth-role-option">
                        <span class="flex size-10 items-center justify-center rounded-xl {{ $registrationType === 'customer' ? 'bg-[var(--brand-lime)] text-[var(--ink)]' : 'bg-[var(--surface-soft)] text-[var(--ink-soft)]' }}">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.5-3.6 2.8-5.5 7-5.5s6.5 1.9 7 5.5"/></svg>
                        </span>
                        <span class="mt-3 block text-sm font-extrabold text-[var(--ink)]">Mencari tempat</span>
                        <span class="mt-1 block text-xs leading-5 text-[var(--muted)]">Jelajahi pilihan untuk acara, pertemuan, produksi, dan kebutuhan lainnya.</span>
                    </button>

                    <button type="button" wire:click="selectRegistrationType('owner')" aria-pressed="{{ $registrationType === 'owner' ? 'true' : 'false' }}" class="auth-role-option">
                        <span class="flex size-10 items-center justify-center rounded-xl {{ $registrationType === 'owner' ? 'bg-[var(--brand-lime)] text-[var(--ink)]' : 'bg-[var(--surface-soft)] text-[var(--ink-soft)]' }}">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M10 21v-3h4v3"/></svg>
                        </span>
                        <span class="mt-3 block text-sm font-extrabold text-[var(--ink)]">Mengelola usaha</span>
                        <span class="mt-1 block text-xs leading-5 text-[var(--muted)]">Perkenalkan tempatmu dan kelola informasi usaha di Adatempat.</span>
                    </button>
                </div>
            </fieldset>

            @if ($registrationType === 'customer')
                <section class="mt-6 border-t border-[var(--border)] pt-6">
                    <h2 class="text-lg font-extrabold tracking-[-0.035em] sm:text-xl">Data akun pelanggan</h2>
                    <p class="mt-1 text-sm leading-6 text-[var(--ink-soft)]">Gunakan email aktif agar kamu dapat menyelesaikan verifikasi akun.</p>
                    <form wire:submit="registerCustomer" class="mt-5 space-y-4">
                        <div>
                            <label for="customer-name" class="ui-label">Nama lengkap</label>
                            <input id="customer-name" type="text" wire:model="name" autocomplete="name" required maxlength="255" class="ui-input" placeholder="Nama lengkap" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                            @error('name') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="customer-email" class="ui-label">Alamat email</label>
                            <input id="customer-email" type="email" wire:model="email" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" required maxlength="255" class="ui-input" placeholder="nama@email.com" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                            @error('email') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="customer-password" class="ui-label">Kata sandi</label>
                            <input id="customer-password" type="password" wire:model="password" autocomplete="new-password" required class="ui-input" placeholder="Buat kata sandi" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                            @error('password') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="customer-password-confirmation" class="ui-label">Konfirmasi kata sandi</label>
                            <input id="customer-password-confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="ui-input" placeholder="Ulangi kata sandi">
                        </div>

                        <label class="flex cursor-pointer items-start gap-3 text-xs leading-5 text-[var(--ink-soft)]">
                            <input type="checkbox" wire:model="acceptedLegal" required class="mt-1 size-4 shrink-0 rounded border-[var(--border-strong)] accent-[var(--brand-forest)]">
                            <span>Saya menyetujui Syarat dan Ketentuan serta Kebijakan Privasi Adatempat.</span>
                        </label>
                        @error('acceptedLegal') <p role="alert" class="ui-error">{{ $message }}</p> @enderror

                        <button type="submit" wire:loading.attr="disabled" wire:target="registerCustomer" class="btn btn-primary w-full">
                            <span wire:loading.remove wire:target="registerCustomer">Buat akun pelanggan</span>
                            <span wire:loading wire:target="registerCustomer">Menyiapkan akun...</span>
                        </button>
                    </form>
                </section>
            @else
                <section class="mt-6 border-t border-[var(--border)] pt-6">
                    <h2 class="text-lg font-extrabold tracking-[-0.035em] sm:text-xl">Data akun pemilik usaha</h2>
                    <p class="mt-1 text-sm leading-6 text-[var(--ink-soft)]">Setelah akun dibuat, verifikasi email diperlukan sebelum mengelola usaha.</p>
                    <form wire:submit="registerOwner" class="mt-5 space-y-4">
                        <div>
                            <label for="owner-name" class="ui-label">Nama pemilik</label>
                            <input id="owner-name" type="text" wire:model="name" autocomplete="name" required maxlength="255" class="ui-input" placeholder="Nama lengkap" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                            @error('name') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="owner-email" class="ui-label">Alamat email</label>
                            <input id="owner-email" type="email" wire:model="email" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" required maxlength="255" class="ui-input" placeholder="nama@email.com" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                            @error('email') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="owner-password" class="ui-label">Kata sandi</label>
                            <input id="owner-password" type="password" wire:model="password" autocomplete="new-password" required class="ui-input" placeholder="Buat kata sandi" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                            @error('password') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="owner-password-confirmation" class="ui-label">Konfirmasi kata sandi</label>
                            <input id="owner-password-confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="ui-input" placeholder="Ulangi kata sandi">
                        </div>

                        <label class="flex cursor-pointer items-start gap-3 text-xs leading-5 text-[var(--ink-soft)]">
                            <input type="checkbox" wire:model="acceptedLegal" required class="mt-1 size-4 shrink-0 rounded border-[var(--border-strong)] accent-[var(--brand-forest)]">
                            <span>Saya menyetujui Syarat dan Ketentuan serta Kebijakan Privasi Adatempat.</span>
                        </label>
                        @error('acceptedLegal') <p role="alert" class="ui-error">{{ $message }}</p> @enderror

                        <button type="submit" wire:loading.attr="disabled" wire:target="registerOwner" class="btn btn-primary w-full">
                            <span wire:loading.remove wire:target="registerOwner">Buat akun pemilik usaha</span>
                            <span wire:loading wire:target="registerOwner">Menyiapkan akun...</span>
                        </button>
                    </form>
                </section>
            @endif

            <p class="mt-6 border-t border-[var(--border)] pt-5 text-center text-sm leading-6 text-[var(--ink-soft)]">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="font-bold text-[var(--brand-forest)] underline-offset-4 hover:underline">Masuk</a>
            </p>
        </div>
    </section>
</div>
