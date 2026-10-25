<?php

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Component;

new class extends Component {
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $validated = $this->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            [
                'email' => $validated['email'],
                'token' => $validated['token'],
                'password' => $validated['password'],
                'password_confirmation' => $this->password_confirmation,
            ],
            function (User $user, string $password): void {
                $user
                    ->forceFill([
                        'password' => $password,
                        'remember_token' => Str::random(60),
                    ])
                    ->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', 'Tautan reset tidak valid atau sudah kedaluwarsa. Periksa email dan tautan Anda, atau minta tautan baru.');

            return;
        }

        session()->flash('status', 'Password berhasil diperbarui. Silakan masuk menggunakan password baru.');

        $this->redirect(route('login'), navigate: false);
    }
};
?>

<div class="site-container grid max-w-6xl items-center gap-8 py-8 sm:py-12 lg:min-h-[calc(100vh-9rem)] lg:grid-cols-[minmax(0,.9fr)_minmax(22rem,1fr)] lg:gap-14 lg:py-14">
    <aside class="relative hidden min-h-[30rem] overflow-hidden rounded-[1.75rem] bg-[#203E2A] lg:block">
        <img src="https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=1100&q=80" alt="" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover opacity-75">
        <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-t from-[#121210]/90 via-[#121210]/20 to-transparent"></div>
        <div class="relative flex min-h-[30rem] flex-col justify-end p-8 xl:p-10">
            <p class="font-metadata text-[10px] uppercase tracking-[0.16em] text-white/70">Keamanan akun</p>
            <h2 class="mt-3 max-w-sm font-editorial text-5xl leading-[.98] text-white xl:text-6xl">Aksesmu, kendalimu.</h2>
            <p class="mt-4 max-w-sm text-sm leading-7 text-white/75">Pilih password baru yang kuat untuk melanjutkan menggunakan Adatempat.</p>
        </div>
    </aside>

    <section class="mx-auto w-full max-w-lg">
        <div class="ui-surface p-6 sm:p-8 lg:p-10">
            <p class="editorial-eyebrow text-[var(--accent-primary)]">Pemulihan akun</p>
            <h1 class="mt-4 font-editorial text-4xl leading-tight tracking-[-0.035em] sm:text-5xl">Buat password baru.</h1>
            <p class="mt-3 text-sm leading-6 text-[var(--text-secondary)] sm:text-base">Masukkan email dan password baru untuk menyelesaikan pemulihan akun.</p>

            <form wire:submit="resetPassword" class="mt-7 space-y-5">
                <div>
                    <label for="email" class="ui-label">Alamat email</label>
                    <input id="email" type="email" wire:model="email" autocomplete="email" required class="ui-input" placeholder="nama@email.com">
                    @error('email') <p role="alert" class="mt-2 text-sm leading-5 text-[#B42318]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password" class="ui-label">Password baru</label>
                    <input id="password" type="password" wire:model="password" autocomplete="new-password" required class="ui-input" placeholder="Buat password baru">
                    @error('password') <p role="alert" class="mt-2 text-sm leading-5 text-[#B42318]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="ui-label">Konfirmasi password baru</label>
                    <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="ui-input" placeholder="Ulangi password baru">
                </div>
                <button type="submit" wire:loading.attr="disabled" class="ui-button ui-button-primary w-full">
                    <span wire:loading.remove wire:target="resetPassword">Simpan password baru</span>
                    <span wire:loading wire:target="resetPassword">Memperbarui...</span>
                </button>
            </form>
        </div>
    </section>
</div>
