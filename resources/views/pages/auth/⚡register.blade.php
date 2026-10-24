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

<div class="min-h-screen bg-[var(--bg-canvas)] px-4 py-12 text-[var(--text-primary)] sm:px-6">
    <div class="mx-auto w-full max-w-md">
        <div class="mt-4 rounded-2xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] p-6 shadow-sm sm:p-8">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--accent-primary)]">
                Buat akun
            </p>

            <h1 class="mt-3 font-serif text-3xl tracking-tight">
                Mulai dari sini.
            </h1>

            <p class="mt-2 text-sm leading-6 text-[var(--text-secondary)]">
                Pilih jenis akun sesuai kebutuhan Anda.
            </p>

            @error('legal')
                <div role="alert"
                    class="mt-6 rounded-xl border border-[var(--border-strong)] bg-[var(--bg-subtle)] p-4 text-sm leading-6 text-[var(--text-secondary)]">
                    {{ $message }}
                </div>
            @enderror

            <div class="mt-6 grid grid-cols-2 gap-2 rounded-xl bg-[var(--bg-subtle)] p-1" aria-label="Jenis registrasi">
                <button type="button" wire:click="selectRegistrationType('customer')"
                    aria-pressed="{{ $registrationType === 'customer' ? 'true' : 'false' }}"
                    class="rounded-lg px-3 py-3 text-sm transition {{ $registrationType === 'customer' ? 'bg-[var(--bg-surface)] font-semibold text-[var(--accent-primary)] shadow-sm' : 'text-[var(--text-secondary)]' }}">
                    Pelanggan
                </button>

                <button type="button" wire:click="selectRegistrationType('owner')"
                    aria-pressed="{{ $registrationType === 'owner' ? 'true' : 'false' }}"
                    class="rounded-lg px-3 py-3 text-sm transition {{ $registrationType === 'owner' ? 'bg-[var(--bg-surface)] font-semibold text-[var(--accent-primary)] shadow-sm' : 'text-[var(--text-secondary)]' }}">
                    Pemilik usaha
                </button>
            </div>

            @if ($registrationType === 'customer')
                <section class="mt-6">
                    <h2 class="font-serif text-xl">
                        Daftar sebagai pelanggan
                    </h2>

                    <p class="mt-2 text-sm leading-6 text-[var(--text-secondary)]">
                        Buat akun untuk menyimpan favorit, menghubungi pemilik
                        usaha, dan melakukan pemesanan venue.
                    </p>

                    <form wire:submit="registerCustomer" class="mt-6 space-y-5">
                        <div>
                            <label for="customer-name" class="mb-2 block text-sm font-medium">
                                Nama lengkap
                            </label>

                            <input id="customer-name" type="text" wire:model="name" autocomplete="name" required
                                maxlength="255"
                                class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--bg-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--accent-primary)] focus:ring-2 focus:ring-[var(--accent-primary)]/15"
                                placeholder="Nama Anda">

                            @error('name')
                                <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="customer-email" class="mb-2 block text-sm font-medium">
                                Alamat email
                            </label>

                            <input id="customer-email" type="email" wire:model="email" autocomplete="email" required
                                maxlength="255"
                                class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--bg-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--accent-primary)] focus:ring-2 focus:ring-[var(--accent-primary)]/15"
                                placeholder="nama@email.com">

                            @error('email')
                                <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="customer-password" class="mb-2 block text-sm font-medium">
                                Password
                            </label>

                            <input id="customer-password" type="password" wire:model="password"
                                autocomplete="new-password" required
                                class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--bg-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--accent-primary)] focus:ring-2 focus:ring-[var(--accent-primary)]/15"
                                placeholder="Buat password">

                            @error('password')
                                <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="customer-password-confirmation" class="mb-2 block text-sm font-medium">
                                Konfirmasi password
                            </label>

                            <input id="customer-password-confirmation" type="password"
                                wire:model="password_confirmation" autocomplete="new-password" required
                                class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--bg-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--accent-primary)] focus:ring-2 focus:ring-[var(--accent-primary)]/15"
                                placeholder="Ulangi password">
                        </div>

                        <div>
                            <label class="flex items-start gap-3 text-sm leading-6 text-[var(--text-secondary)]">
                                <input type="checkbox" wire:model="acceptedLegal" required
                                    class="mt-1 h-4 w-4 rounded border-[var(--border-strong)] accent-[var(--accent-primary)]">

                                <span>
                                    Saya telah membaca dan menyetujui Terms and
                                    Conditions serta Kebijakan Privasi yang berlaku.
                                </span>
                            </label>

                            @error('acceptedLegal')
                                <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" wire:loading.attr="disabled" wire:target="registerCustomer"
                            class="w-full rounded-xl bg-[var(--accent-primary)] px-4 py-3.5 text-sm font-semibold text-white transition hover:bg-[var(--accent-primary-hover)] disabled:cursor-not-allowed disabled:opacity-60">
                            <span wire:loading.remove wire:target="registerCustomer">
                                Buat akun pelanggan
                            </span>

                            <span wire:loading wire:target="registerCustomer">
                                Memproses...
                            </span>
                        </button>
                    </form>
                </section>
            @else
                <section class="mt-6">
                    <h2 class="font-serif text-xl">
                        Daftar sebagai pemilik usaha
                    </h2>

                    <p class="mt-2 text-sm leading-6 text-[var(--text-secondary)]">
                        Buat akun untuk mendaftarkan usaha dan mengelola venue.
                        Email harus diverifikasi sebelum melanjutkan proses
                        pengelolaan usaha.
                    </p>

                    <form wire:submit="registerOwner" class="mt-6 space-y-5">
                        <div>
                            <label for="owner-name" class="mb-2 block text-sm font-medium">
                                Nama pemilik
                            </label>

                            <input id="owner-name" type="text" wire:model="name" autocomplete="name" required
                                maxlength="255"
                                class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--bg-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--accent-primary)] focus:ring-2 focus:ring-[var(--accent-primary)]/15"
                                placeholder="Nama lengkap pemilik">

                            @error('name')
                                <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="owner-email" class="mb-2 block text-sm font-medium">
                                Email pemilik usaha
                            </label>

                            <input id="owner-email" type="email" wire:model="email" autocomplete="email" required
                                maxlength="255"
                                class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--bg-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--accent-primary)] focus:ring-2 focus:ring-[var(--accent-primary)]/15"
                                placeholder="nama@email.com">

                            @error('email')
                                <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="owner-password" class="mb-2 block text-sm font-medium">
                                Password
                            </label>

                            <input id="owner-password" type="password" wire:model="password"
                                autocomplete="new-password" required
                                class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--bg-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--accent-primary)] focus:ring-2 focus:ring-[var(--accent-primary)]/15"
                                placeholder="Buat password">

                            @error('password')
                                <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="owner-password-confirmation" class="mb-2 block text-sm font-medium">
                                Konfirmasi password
                            </label>

                            <input id="owner-password-confirmation" type="password"
                                wire:model="password_confirmation" autocomplete="new-password" required
                                class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--bg-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--accent-primary)] focus:ring-2 focus:ring-[var(--accent-primary)]/15"
                                placeholder="Ulangi password">
                        </div>

                        <div>
                            <label class="flex items-start gap-3 text-sm leading-6 text-[var(--text-secondary)]">
                                <input type="checkbox" wire:model="acceptedLegal" required
                                    class="mt-1 h-4 w-4 rounded border-[var(--border-strong)] accent-[var(--accent-primary)]">

                                <span>
                                    Saya telah membaca dan menyetujui Terms and
                                    Conditions serta Kebijakan Privasi yang berlaku.
                                </span>
                            </label>

                            @error('acceptedLegal')
                                <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" wire:loading.attr="disabled" wire:target="registerOwner"
                            class="w-full rounded-xl bg-[var(--accent-primary)] px-4 py-3.5 text-sm font-semibold text-white transition hover:bg-[var(--accent-primary-hover)] disabled:cursor-not-allowed disabled:opacity-60">
                            <span wire:loading.remove wire:target="registerOwner">
                                Daftar sebagai pemilik usaha
                            </span>

                            <span wire:loading wire:target="registerOwner">
                                Memproses...
                            </span>
                        </button>
                    </form>
                </section>
            @endif

            <p class="mt-6 text-center text-sm text-[var(--text-secondary)]">
                Sudah punya akun?
                <a href="{{ route('login') }}"
                    class="font-semibold text-[var(--accent-primary)] underline-offset-4 hover:underline">
                    Masuk
                </a>
            </p>
        </div>

        <p class="mt-6 text-center text-xs leading-5 text-[var(--text-muted)]">
            Dengan mendaftar, Anda membuat akun Adatempat sesuai ketentuan yang berlaku.
        </p>
    </div>
</div>
