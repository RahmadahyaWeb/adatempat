<?php

use App\Services\MfaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $code = '';

    public function mount(MfaService $mfaService): void
    {
        $user = Auth::user();

        abort_unless($user?->isSuperAdmin(), 403);

        if ($user->mfa_enabled_at && $user->mfa_secret) {
            $this->redirectRoute('mfa.challenge');

            return;
        }

        if (!$user->mfa_secret) {
            $user->mfa_secret = $mfaService->generateSecret();
            $user->save();
        }
    }

    #[Computed]
    public function secret(): string
    {
        $user = Auth::user();

        abort_unless($user?->isSuperAdmin(), 403);

        if ($user->mfa_enabled_at || !$user->mfa_secret) {
            abort(403);
        }

        return $user->mfa_secret;
    }

    public function activate(MfaService $mfaService): void
    {
        $this->validate(
            [
                'code' => ['required', 'digits:6'],
            ],
            [
                'code.required' => 'Masukkan kode dari aplikasi authenticator.',
                'code.digits' => 'Kode harus terdiri dari enam digit.',
            ],
        );

        $user = Auth::user();

        abort_unless($user && $user->isSuperAdmin() && $user->mfa_secret && !$user->mfa_enabled_at, 403);

        if (!$mfaService->verify($user->mfa_secret, $this->code)) {
            $this->addError('code', 'Kode tidak valid atau sudah kedaluwarsa.');

            return;
        }

        $user->mfa_enabled_at = now();
        $user->save();

        session([
            'admin_mfa_verified_user_id' => (string) $user->getKey(),
            'admin_mfa_verified_at' => now()->timestamp,
        ]);

        $this->redirectRoute('home');
    }
};
?>

<div class="mx-auto max-w-lg space-y-6 p-6">
    <div>
        <h1 class="text-2xl font-semibold">
            Aktifkan autentikasi dua faktor
        </h1>

        <p class="mt-2 text-sm text-gray-600">
            Gunakan aplikasi authenticator yang mendukung TOTP, seperti Google
            Authenticator, Microsoft Authenticator, atau 1Password.
        </p>
    </div>

    <section class="space-y-3 rounded-xl border p-4">
        <h2 class="font-semibold">1. Tambahkan akun ke authenticator</h2>

        <p class="text-sm text-gray-600">
            Pilih opsi untuk menambahkan akun secara manual, lalu masukkan
            secret berikut.
        </p>

        <div class="break-all rounded-lg bg-gray-100 p-3 font-mono text-sm" aria-label="Secret TOTP">
            {{ $this->secret }}
        </div>

        <button type="button" class="rounded-lg border px-4 py-2 text-sm font-medium" x-data
            x-on:click="navigator.clipboard.writeText(@js($this->secret))">
            Salin secret
        </button>

        <p class="text-xs text-gray-500">
            Secret ini bersifat rahasia. Jangan bagikan kepada orang lain.
        </p>
    </section>

    <section class="space-y-3 rounded-xl border p-4">
        <h2 class="font-semibold">2. Verifikasi kode</h2>

        <p class="text-sm text-gray-600">
            Masukkan kode enam digit yang saat ini ditampilkan oleh aplikasi
            authenticator untuk menyelesaikan aktivasi.
        </p>

        <form wire:submit="activate" class="space-y-4">
            <div>
                <label for="code" class="mb-1 block text-sm font-medium">
                    Kode authenticator
                </label>

                <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                    wire:model="code" class="w-full rounded-lg border px-3 py-2" placeholder="123456" required>

                @error('code')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled"
                class="w-full rounded-lg bg-black px-4 py-3 font-medium text-white disabled:opacity-50">
                <span wire:loading.remove>Aktifkan MFA</span>
                <span wire:loading>Memverifikasi...</span>
            </button>
        </form>
    </section>
</div>
