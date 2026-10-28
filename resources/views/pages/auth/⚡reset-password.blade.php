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
            $this->addError('email', 'Tautan pemulihan tidak valid atau sudah kedaluwarsa. Periksa kembali tautan dari emailmu atau minta tautan baru.');

            return;
        }

        session()->flash('status', 'Kata sandi berhasil diperbarui. Silakan masuk menggunakan kata sandi baru.');

        $this->redirect(route('login'), navigate: false);
    }
};
?>
<div class="auth-v2-page">
    <section class="site-container auth-v2-shell">
        <div class="auth-v2-story">
            <a href="{{ route('login') }}" class="auth-v2-back">← Kembali masuk</a>
            <div><p class="eyebrow"><span class="eyebrow-dot"></span> Kata sandi baru</p><h1>Buat akses baru yang aman.</h1><p>Gunakan kata sandi baru untuk kembali ke akun Adatempat.</p></div>
        </div>
        <div class="auth-v2-panel">
            <div class="auth-v2-panel-head"><h2>Atur ulang kata sandi</h2><p>Lengkapi data di bawah.</p></div>
            <form wire:submit="resetPassword" class="auth-v2-form">
                <div><label for="email" class="ui-label">Alamat email</label><input id="email" type="email" wire:model="email" autocomplete="email" required class="ui-input">@error('email') <p role="alert" class="ui-error">{{ $message }}</p> @enderror</div>
                <div><label for="password" class="ui-label">Kata sandi baru</label><input id="password" type="password" wire:model="password" autocomplete="new-password" required class="ui-input" placeholder="Buat kata sandi baru">@error('password') <p role="alert" class="ui-error">{{ $message }}</p> @enderror</div>
                <div><label for="password_confirmation" class="ui-label">Konfirmasi kata sandi</label><input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="ui-input" placeholder="Ulangi kata sandi baru"></div>
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full"><span wire:loading.remove wire:target="resetPassword">Simpan kata sandi</span><span wire:loading wire:target="resetPassword">Memperbarui...</span></button>
            </form>
        </div>
    </section>
</div>