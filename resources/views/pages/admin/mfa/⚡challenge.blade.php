<?php

use App\Services\MfaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

new class extends Component {
    public string $code = '';

    public function verify(MfaService $mfaService): void
    {
        $this->validate(
            [
                'code' => ['required', 'digits:6'],
            ],
            [
                'code.required' => 'Masukkan kode authenticator.',
                'code.digits' => 'Kode harus terdiri dari enam digit.',
            ],
        );

        $user = Auth::user();

        abort_unless($user?->isSuperAdmin(), 403);

        if (!$user->mfa_secret || !$user->mfa_enabled_at) {
            $this->redirectRoute('mfa.setup');

            return;
        }

        $key = 'admin-mfa:' . $user->getKey() . ':' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            $this->addError('code', "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.");

            return;
        }

        RateLimiter::hit($key, 60);

        if (!$mfaService->verify($user->mfa_secret, $this->code)) {
            $this->addError('code', 'Kode tidak valid atau sudah kedaluwarsa.');

            return;
        }

        RateLimiter::clear($key);

        session([
            'admin_mfa_verified_user_id' => (string) $user->getKey(),
            'admin_mfa_verified_at' => now()->timestamp,
        ]);

        $this->redirectRoute('home');
    }
};
?>

<div class="mx-auto max-w-md space-y-6 p-6">
    <div>
        <h1 class="text-2xl font-semibold">Verifikasi administrator</h1>
        <p class="mt-2 text-sm text-gray-600">
            Masukkan kode enam digit dari aplikasi authenticator untuk
            melanjutkan.
        </p>
    </div>

    <form wire:submit="verify" class="space-y-4 rounded-xl border p-5">
        <div>
            <label for="code" class="mb-1 block text-sm font-medium">
                Kode authenticator
            </label>

            <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                wire:model="code" class="w-full rounded-lg border px-3 py-2" placeholder="123456" required autofocus>

            @error('code')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled"
            class="w-full rounded-lg bg-black px-4 py-3 font-medium text-white disabled:opacity-50">
            <span wire:loading.remove>Verifikasi</span>
            <span wire:loading>Memverifikasi...</span>
        </button>
    </form>

    <p class="text-xs text-gray-500">
        Jika kode ditolak, periksa waktu perangkat dan gunakan kode terbaru
        dari aplikasi authenticator.
    </p>
</div>
