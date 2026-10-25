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

<div class="site-container grid max-w-6xl items-start gap-6 py-6 sm:gap-10 sm:py-10 lg:grid-cols-[minmax(0,.9fr)_minmax(25rem,1fr)] lg:gap-14 lg:py-14">
    <section class="order-1 mx-auto w-full max-w-xl lg:order-2">
        <div class="ui-panel p-5 sm:p-8 lg:p-10">
            <p class="eyebrow"><span class="eyebrow-dot"></span> Pemulihan akun</p>
            <h1 class="mt-3 text-3xl font-extrabold leading-tight tracking-[-0.055em] sm:text-4xl">Buat password baru.</h1>
            <p class="mt-3 text-sm leading-6 text-[var(--ink-soft)] sm:text-base">Gunakan password yang kuat dan konfirmasi sebelum menyimpan perubahan.</p>

            @if (session('status'))
                <div role="status" class="status-note status-success mt-5">{{ session('status') }}</div>
            @endif

            <form wire:submit="resetPassword" class="mt-6 space-y-5">
                <div>
                    <label for="email" class="ui-label">Alamat email</label>
                    <input id="email" type="email" wire:model="email" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" required class="ui-input" placeholder="nama@email.com" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                    @error('email') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password" class="ui-label">Password baru</label>
                    <input id="password" type="password" wire:model="password" autocomplete="new-password" required class="ui-input" placeholder="Buat password baru" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                    @error('password') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="ui-label">Konfirmasi password baru</label>
                    <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="ui-input" placeholder="Ulangi password baru">
                </div>
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full">
                    <span wire:loading.remove wire:target="resetPassword">Simpan password baru</span>
                    <span wire:loading wire:target="resetPassword">Memperbarui...</span>
                </button>
            </form>
            <p class="mt-6 text-center text-sm leading-6 text-[var(--ink-soft)]">Tautan bermasalah? <a href="{{ route('password.request') }}" class="font-bold text-[var(--brand-forest)] underline-offset-4 hover:underline">Minta tautan baru</a></p>
        </div>
    </section>

    <aside class="auth-promo order-2 hidden min-h-[25rem] flex-col justify-between p-7 lg:order-1 lg:flex lg:p-9">
        <div class="auth-promo-accent" aria-hidden="true"></div>
        <p class="relative eyebrow !text-[var(--brand-lime)]"><span class="eyebrow-dot !bg-[var(--brand-lime)]"></span> Keamanan akun</p>
        <div class="relative max-w-md">
            <h2 class="text-4xl font-extrabold leading-[1.12] tracking-[-0.055em] text-white xl:text-5xl">Atur aksesmu dengan aman.</h2>
            <p class="mt-5 text-sm leading-7 text-white/80">Setelah password diperbarui, masuk kembali menggunakan kredensial terbaru.</p>
        </div>
        <p class="relative border-t border-white/20 pt-5 text-xs leading-6 text-white/70">Jangan gunakan password yang sama dengan akun penting lainnya.</p>
    </aside>
</div>