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

<div class="min-h-screen bg-[var(--bg-canvas)] px-4 py-12 text-[var(--text-primary)] sm:px-6">
    <div class="mx-auto max-w-md">
        <div class="rounded-2xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] p-6 shadow-sm sm:p-8">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--accent-primary)]">
                Pemulihan akun
            </p>

            <h1 class="mt-3 font-serif text-3xl">
                Buat password baru.
            </h1>

            <p class="mt-3 text-sm leading-6 text-[var(--text-secondary)]">
                Masukkan password baru untuk akun Anda.
            </p>

            <form wire:submit="resetPassword" class="mt-6 space-y-5">
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium">
                        Alamat email
                    </label>

                    <input id="email" type="email" wire:model="email" autocomplete="email" required
                        class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--bg-surface)] px-4 py-3 text-sm outline-none focus:border-[var(--accent-primary)] focus:ring-2 focus:ring-[var(--accent-primary)]/15">

                    @error('email')
                        <p role="alert" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-medium">
                        Password baru
                    </label>

                    <input id="password" type="password" wire:model="password" autocomplete="new-password" required
                        class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--bg-surface)] px-4 py-3 text-sm outline-none focus:border-[var(--accent-primary)] focus:ring-2 focus:ring-[var(--accent-primary)]/15">

                    @error('password')
                        <p role="alert" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-2 block text-sm font-medium">
                        Konfirmasi password baru
                    </label>

                    <input id="password_confirmation" type="password" wire:model="password_confirmation"
                        autocomplete="new-password" required
                        class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--bg-surface)] px-4 py-3 text-sm outline-none focus:border-[var(--accent-primary)] focus:ring-2 focus:ring-[var(--accent-primary)]/15">
                </div>

                <button type="submit" wire:loading.attr="disabled"
                    class="w-full rounded-xl bg-[var(--accent-primary)] px-4 py-3 text-sm font-semibold text-white hover:bg-[var(--accent-primary-hover)] disabled:opacity-60">
                    <span wire:loading.remove wire:target="resetPassword">
                        Simpan password baru
                    </span>

                    <span wire:loading wire:target="resetPassword">
                        Memproses...
                    </span>
                </button>
            </form>
        </div>
    </div>
</div>
