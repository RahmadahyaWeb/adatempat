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

<div class="site-container grid max-w-6xl items-start gap-6 py-6 sm:gap-10 sm:py-10 lg:grid-cols-[minmax(0,.85fr)_minmax(28rem,1.15fr)] lg:gap-14 lg:py-12">
    <section class="order-1 mx-auto w-full max-w-2xl lg:order-2">
        <div class="ui-panel p-5 sm:p-8 lg:p-9">
            <p class="eyebrow"><span class="eyebrow-dot"></span> Buat akun baru</p>
            <h1 class="mt-3 text-3xl font-extrabold leading-tight tracking-[-0.055em] sm:text-4xl">Mulai dengan memilih kebutuhanmu.</h1>
            <p class="mt-3 text-sm leading-6 text-[var(--ink-soft)] sm:text-base">Pilih jenis akun yang sesuai. Kamu bisa mendaftar sebagai pelanggan atau pemilik usaha.</p>

            @error('legal')
                <div role="alert" class="status-note mt-5">{{ $message }}</div>
            @enderror

            <div class="mt-6 grid grid-cols-1 gap-2 sm:grid-cols-2" aria-label="Jenis akun">
                <button type="button" wire:click="selectRegistrationType('customer')" aria-pressed="{{ $registrationType === 'customer' ? 'true' : 'false' }}" class="rounded-xl border p-4 text-left transition-colors {{ $registrationType === 'customer' ? 'border-[var(--brand-forest)] bg-[var(--surface-soft)] ring-2 ring-[var(--brand-forest)]/15' : 'border-[var(--border)] bg-white hover:border-[var(--border-strong)]' }}">
                    <span class="flex size-9 items-center justify-center rounded-lg {{ $registrationType === 'customer' ? 'bg-[var(--brand-lime)] text-[var(--ink)]' : 'bg-[var(--surface-soft)] text-[var(--ink-soft)]' }}">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.5-3.6 2.8-5.5 7-5.5s6.5 1.9 7 5.5"/></svg>
                    </span>
                    <span class="mt-3 block text-sm font-extrabold">Saya mencari tempat</span>
                    <span class="mt-1 block text-xs leading-5 text-[var(--muted)]">Untuk menemukan opsi tempat sesuai rencana.</span>
                </button>
                <button type="button" wire:click="selectRegistrationType('owner')" aria-pressed="{{ $registrationType === 'owner' ? 'true' : 'false' }}" class="rounded-xl border p-4 text-left transition-colors {{ $registrationType === 'owner' ? 'border-[var(--brand-forest)] bg-[var(--surface-soft)] ring-2 ring-[var(--brand-forest)]/15' : 'border-[var(--border)] bg-white hover:border-[var(--border-strong)]' }}">
                    <span class="flex size-9 items-center justify-center rounded-lg {{ $registrationType === 'owner' ? 'bg-[var(--brand-lime)] text-[var(--ink)]' : 'bg-[var(--surface-soft)] text-[var(--ink-soft)]' }}">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M10 21v-3h4v3"/></svg>
                    </span>
                    <span class="mt-3 block text-sm font-extrabold">Saya punya tempat</span>
                    <span class="mt-1 block text-xs leading-5 text-[var(--muted)]">Untuk menyiapkan profil venue atau usaha.</span>
                </button>
            </div>

            @if ($registrationType === 'customer')
                <section class="mt-7 border-t border-[var(--border)] pt-6">
                    <h2 class="text-xl font-extrabold tracking-[-0.04em] sm:text-2xl">Akun pelanggan</h2>
                    <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Isi data berikut untuk membuat akun.</p>
                    <form wire:submit="registerCustomer" class="mt-5 space-y-4">
                        <div>
                            <label for="customer-name" class="ui-label">Nama lengkap</label>
                            <input id="customer-name" type="text" wire:model="name" autocomplete="name" required maxlength="255" class="ui-input" placeholder="Nama lengkapmu" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                            @error('name') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="customer-email" class="ui-label">Alamat email</label>
                            <input id="customer-email" type="email" wire:model="email" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" required maxlength="255" class="ui-input" placeholder="nama@email.com" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                            @error('email') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="customer-password" class="ui-label">Password</label>
                            <input id="customer-password" type="password" wire:model="password" autocomplete="new-password" required class="ui-input" placeholder="Buat password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                            @error('password') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="customer-password-confirmation" class="ui-label">Konfirmasi password</label>
                            <input id="customer-password-confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="ui-input" placeholder="Ulangi password">
                        </div>
                        <label class="flex cursor-pointer items-start gap-3 text-xs leading-5 text-[var(--ink-soft)]">
                            <input type="checkbox" wire:model="acceptedLegal" required class="mt-1 size-4 shrink-0 rounded border-[var(--border-strong)] accent-[var(--brand-forest)]">
                            <span>Saya menyetujui Terms and Conditions dan Privacy Policy Adatempat.</span>
                        </label>
                        @error('acceptedLegal') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        <button type="submit" wire:loading.attr="disabled" wire:target="registerCustomer" class="btn btn-primary w-full">
                            <span wire:loading.remove wire:target="registerCustomer">Buat akun pelanggan</span>
                            <span wire:loading wire:target="registerCustomer">Menyiapkan akun...</span>
                        </button>
                    </form>
                </section>
            @else
                <section class="mt-7 border-t border-[var(--border)] pt-6">
                    <h2 class="text-xl font-extrabold tracking-[-0.04em] sm:text-2xl">Akun pemilik usaha</h2>
                    <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Daftarkan data pemilik untuk mulai menyiapkan listing tempat.</p>
                    <form wire:submit="registerOwner" class="mt-5 space-y-4">
                        <div>
                            <label for="owner-name" class="ui-label">Nama pemilik</label>
                            <input id="owner-name" type="text" wire:model="name" autocomplete="name" required maxlength="255" class="ui-input" placeholder="Nama lengkapmu" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                            @error('name') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="owner-email" class="ui-label">Alamat email</label>
                            <input id="owner-email" type="email" wire:model="email" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" required maxlength="255" class="ui-input" placeholder="nama@email.com" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                            @error('email') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="owner-password" class="ui-label">Password</label>
                            <input id="owner-password" type="password" wire:model="password" autocomplete="new-password" required class="ui-input" placeholder="Buat password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                            @error('password') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="owner-password-confirmation" class="ui-label">Konfirmasi password</label>
                            <input id="owner-password-confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="ui-input" placeholder="Ulangi password">
                        </div>
                        <label class="flex cursor-pointer items-start gap-3 text-xs leading-5 text-[var(--ink-soft)]">
                            <input type="checkbox" wire:model="acceptedLegal" required class="mt-1 size-4 shrink-0 rounded border-[var(--border-strong)] accent-[var(--brand-forest)]">
                            <span>Saya menyetujui Terms and Conditions dan Privacy Policy Adatempat.</span>
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

    <aside class="auth-promo order-2 hidden min-h-[36rem] flex-col justify-between p-7 lg:order-1 lg:flex lg:p-9">
        <div class="auth-promo-accent" aria-hidden="true"></div>
        <p class="relative eyebrow !text-[var(--brand-lime)]"><span class="eyebrow-dot !bg-[var(--brand-lime)]"></span> Adatempat / Buat akun</p>
        <div class="relative max-w-md">
            <h2 class="text-4xl font-extrabold leading-[1.12] tracking-[-0.055em] text-white xl:text-5xl">Satu langkah untuk mulai mencari atau memperkenalkan tempat.</h2>
            <p class="mt-5 text-sm leading-7 text-white/80">Pilih peran yang sesuai. Informasi akun akan digunakan untuk mengelola akses Adatempat.</p>
        </div>
        <p class="relative border-t border-white/20 pt-5 text-xs leading-6 text-white/70">Pastikan email yang kamu gunakan aktif agar proses verifikasi akun dapat dilanjutkan.</p>
    </aside>
</div>