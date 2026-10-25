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

<div class="site-container grid max-w-6xl items-start gap-8 py-8 sm:py-12 lg:grid-cols-[minmax(0,.8fr)_minmax(0,1.2fr)] lg:gap-14 lg:py-14">
    <aside class="relative hidden min-h-[42rem] overflow-hidden rounded-[1.75rem] bg-[#E9F3EC] lg:flex lg:flex-col lg:justify-between lg:p-8 xl:p-10">
        <div aria-hidden="true" class="absolute -right-16 -top-12 size-64 rounded-full border border-[#C8D9CA]"></div>
        <div aria-hidden="true" class="absolute -right-8 top-8 size-44 rounded-full border-[24px] border-[#D6E6D8]"></div>
        <div class="relative z-10">
            <p class="editorial-eyebrow text-[#49634E]"><span class="size-2 rounded-full bg-[var(--accent-sage)]"></span> Adatempat / Mulai di sini</p>
        </div>
        <div class="relative z-10 max-w-md">
            <p class="font-metadata text-[10px] uppercase tracking-[0.16em] text-[#49634E]">Satu akun, banyak kemungkinan</p>
            <h2 class="mt-4 font-editorial text-5xl leading-[.98] tracking-[-0.035em] text-[#203E2A] xl:text-6xl">Ada tempat untuk rencana berikutnya.</h2>
            <p class="mt-5 text-sm leading-7 text-[#49634E]">Bergabung sebagai pelanggan yang sedang mencari tempat, atau sebagai pemilik usaha yang ingin memperkenalkan venuenya.</p>
        </div>
        <div class="relative z-10 overflow-hidden rounded-2xl">
            <img src="https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=900&q=80" alt="" loading="lazy" decoding="async" class="h-48 w-full object-cover">
        </div>
    </aside>

    <section class="mx-auto w-full max-w-2xl">
        <div class="ui-surface p-6 sm:p-8 lg:p-9">
            <p class="editorial-eyebrow text-[var(--accent-primary)]">Buat akun Adatempat</p>
            <h1 class="mt-4 font-editorial text-4xl leading-tight tracking-[-0.035em] sm:text-5xl">Mulai dari sini.</h1>
            <p class="mt-3 text-sm leading-6 text-[var(--text-secondary)] sm:text-base">Pilih jenis akun yang paling sesuai dengan kebutuhanmu.</p>

            @error('legal')
                <div role="alert" class="mt-5 rounded-xl border border-[var(--border-strong)] bg-[var(--bg-subtle)] p-4 text-sm leading-6 text-[var(--text-secondary)]">
                    {{ $message }}
                </div>
            @enderror

            <div class="mt-7 grid grid-cols-2 gap-2 rounded-2xl bg-[var(--bg-subtle)] p-1.5" aria-label="Jenis akun">
                <button type="button" wire:click="selectRegistrationType('customer')" aria-pressed="{{ $registrationType === 'customer' ? 'true' : 'false' }}" class="rounded-xl px-3 py-3 text-sm font-semibold transition-colors {{ $registrationType === 'customer' ? 'bg-[var(--bg-surface)] text-[var(--accent-primary)] shadow-card' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]' }}">
                    Saya mencari tempat
                </button>
                <button type="button" wire:click="selectRegistrationType('owner')" aria-pressed="{{ $registrationType === 'owner' ? 'true' : 'false' }}" class="rounded-xl px-3 py-3 text-sm font-semibold transition-colors {{ $registrationType === 'owner' ? 'bg-[var(--bg-surface)] text-[var(--accent-primary)] shadow-card' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]' }}">
                    Saya punya usaha
                </button>
            </div>

            @if ($registrationType === 'customer')
                <section class="mt-7">
                    <h2 class="font-editorial text-2xl leading-tight sm:text-3xl">Buat akun pelanggan</h2>
                    <p class="mt-2 text-sm leading-6 text-[var(--text-secondary)]">Jelajahi venue, simpan tempat favorit, dan lanjutkan proses pemesanan.</p>

                    <form wire:submit="registerCustomer" class="mt-6 space-y-4">
                        <div>
                            <label for="customer-name" class="ui-label">Nama lengkap</label>
                            <input id="customer-name" type="text" wire:model="name" autocomplete="name" required maxlength="255" class="ui-input" placeholder="Nama lengkapmu">
                            @error('name') <p role="alert" class="mt-2 text-sm text-[#B42318]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="customer-email" class="ui-label">Alamat email</label>
                            <input id="customer-email" type="email" wire:model="email" autocomplete="email" required maxlength="255" class="ui-input" placeholder="nama@email.com">
                            @error('email') <p role="alert" class="mt-2 text-sm text-[#B42318]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="customer-password" class="ui-label">Password</label>
                            <input id="customer-password" type="password" wire:model="password" autocomplete="new-password" required class="ui-input" placeholder="Buat password">
                            @error('password') <p role="alert" class="mt-2 text-sm text-[#B42318]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="customer-password-confirmation" class="ui-label">Konfirmasi password</label>
                            <input id="customer-password-confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="ui-input" placeholder="Ulangi password">
                        </div>
                        <label class="flex cursor-pointer items-start gap-3 text-xs leading-5 text-[var(--text-secondary)]">
                            <input type="checkbox" wire:model="acceptedLegal" required class="mt-0.5 size-4 shrink-0 rounded border-[var(--border-strong)] accent-[var(--accent-primary)]">
                            <span>Saya menyetujui Terms and Conditions dan Privacy Policy Adatempat.</span>
                        </label>
                        @error('acceptedLegal') <p role="alert" class="text-sm text-[#B42318]">{{ $message }}</p> @enderror
                        <button type="submit" wire:loading.attr="disabled" wire:target="registerCustomer" class="ui-button ui-button-primary w-full">
                            <span wire:loading.remove wire:target="registerCustomer">Buat akun pelanggan</span>
                            <span wire:loading wire:target="registerCustomer">Menyiapkan akun...</span>
                        </button>
                    </form>
                </section>
            @else
                <section class="mt-7">
                    <h2 class="font-editorial text-2xl leading-tight sm:text-3xl">Daftarkan akun pemilik usaha</h2>
                    <p class="mt-2 text-sm leading-6 text-[var(--text-secondary)]">Siapkan akun untuk memperkenalkan usaha dan mengelola venue-mu di Adatempat.</p>

                    <form wire:submit="registerOwner" class="mt-6 space-y-4">
                        <div>
                            <label for="owner-name" class="ui-label">Nama pemilik</label>
                            <input id="owner-name" type="text" wire:model="name" autocomplete="name" required maxlength="255" class="ui-input" placeholder="Nama lengkapmu">
                            @error('name') <p role="alert" class="mt-2 text-sm text-[#B42318]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="owner-email" class="ui-label">Alamat email</label>
                            <input id="owner-email" type="email" wire:model="email" autocomplete="email" required maxlength="255" class="ui-input" placeholder="nama@email.com">
                            @error('email') <p role="alert" class="mt-2 text-sm text-[#B42318]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="owner-password" class="ui-label">Password</label>
                            <input id="owner-password" type="password" wire:model="password" autocomplete="new-password" required class="ui-input" placeholder="Buat password">
                            @error('password') <p role="alert" class="mt-2 text-sm text-[#B42318]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="owner-password-confirmation" class="ui-label">Konfirmasi password</label>
                            <input id="owner-password-confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="ui-input" placeholder="Ulangi password">
                        </div>
                        <label class="flex cursor-pointer items-start gap-3 text-xs leading-5 text-[var(--text-secondary)]">
                            <input type="checkbox" wire:model="acceptedLegal" required class="mt-0.5 size-4 shrink-0 rounded border-[var(--border-strong)] accent-[var(--accent-primary)]">
                            <span>Saya menyetujui Terms and Conditions dan Privacy Policy Adatempat.</span>
                        </label>
                        @error('acceptedLegal') <p role="alert" class="text-sm text-[#B42318]">{{ $message }}</p> @enderror
                        <button type="submit" wire:loading.attr="disabled" wire:target="registerOwner" class="ui-button ui-button-primary w-full">
                            <span wire:loading.remove wire:target="registerOwner">Buat akun pemilik usaha</span>
                            <span wire:loading wire:target="registerOwner">Menyiapkan akun...</span>
                        </button>
                    </form>
                </section>
            @endif

            <p class="mt-7 border-t border-[var(--border-subtle)] pt-5 text-center text-sm leading-6 text-[var(--text-secondary)]">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="font-semibold text-[var(--accent-primary)] underline-offset-4 hover:underline">Masuk</a>
            </p>
        </div>
    </section>
</div>
