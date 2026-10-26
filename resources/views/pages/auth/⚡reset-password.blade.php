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
<div class="site-container py-8 sm:py-12 lg:py-16">
    <section class="auth-shell">
        <div class="mb-5 text-center sm:mb-6">
            <p class="auth-page-kicker">Pemulihan akun</p>
            <h1 class="auth-page-title">Buat kata sandi baru</h1>
            <p class="auth-page-description mx-auto max-w-md">Lengkapi data di bawah untuk menyelesaikan pemulihan akun Adatempat.</p>
        </div>

        <div class="auth-card">
            @if (session('status'))
                <div role="status" class="status-note status-success mb-5">{{ session('status') }}</div>
            @endif

            <form wire:submit="resetPassword" class="space-y-5">
                <div>
                    <label for="email" class="ui-label">Alamat email</label>
                    <input id="email" type="email" wire:model="email" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" required class="ui-input" placeholder="nama@email.com" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                    @error('email') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password" class="ui-label">Kata sandi baru</label>
                    <input id="password" type="password" wire:model="password" autocomplete="new-password" required class="ui-input" placeholder="Buat kata sandi baru" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                    @error('password') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    <p class="mt-2 text-xs leading-5 text-[var(--muted)]">Gunakan kata sandi yang memenuhi persyaratan keamanan.</p>
                </div>
                <div>
                    <label for="password_confirmation" class="ui-label">Konfirmasi kata sandi baru</label>
                    <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="ui-input" placeholder="Ulangi kata sandi baru" aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}">
                    @error('password_confirmation') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full">
                    <span wire:loading.remove wire:target="resetPassword">Simpan kata sandi baru</span>
                    <span wire:loading wire:target="resetPassword">Memperbarui...</span>
                </button>
            </form>

            <p class="mt-6 border-t border-[var(--border)] pt-5 text-center text-sm leading-6 text-[var(--ink-soft)]">
                Tautan tidak bisa digunakan?
                <a href="{{ route('password.request') }}" class="font-bold text-[var(--brand-forest)] underline-offset-4 hover:underline">Minta tautan baru</a>
            </p>
        </div>
    </section>
</div>
