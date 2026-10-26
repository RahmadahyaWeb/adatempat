<?php

use App\Services\MfaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

new class extends Component {
    public string $code = '';

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user?->isOwner() && $user->status === 'active', 403);

        if (! $user->mfa_secret || ! $user->mfa_enabled_at) {
            $this->redirectRoute('owner.mfa.setup');
        }
    }

    public function verify(MfaService $mfaService): void
    {
        $this->validate(['code' => ['required', 'digits:6']]);

        $user = Auth::user();
        abort_unless($user?->isOwner() && $user->status === 'active', 403);

        if (! $user->mfa_secret || ! $user->mfa_enabled_at) {
            $this->redirectRoute('owner.mfa.setup');

            return;
        }

        $key = 'owner-mfa:' . $user->getKey() . ':' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError('code', "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.");

            return;
        }

        RateLimiter::hit($key, 60);

        if (! $mfaService->verify($user->mfa_secret, $this->code)) {
            $this->addError('code', 'Kode authenticator tidak valid atau sudah kedaluwarsa.');

            return;
        }

        RateLimiter::clear($key);

        session([
            'owner_mfa_verified_user_id' => (string) $user->getKey(),
            'owner_mfa_verified_at' => now()->timestamp,
        ]);

        $this->redirectRoute('owner.payout-accounts.index');
    }
};
?>
<div class="site-container max-w-2xl py-8 sm:py-12">
    <section class="ui-panel p-5 sm:p-8">
        <p class="eyebrow"><span class="eyebrow-dot"></span> Verifikasi keamanan Owner</p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-[-0.05em]">Verifikasi sebelum mengelola rekening.</h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ink-soft)]">Masukkan kode enam digit dari aplikasi authenticator. Verifikasi berlaku selama 10 menit.</p>
        <form wire:submit="verify" class="mt-6 space-y-3">
            <label for="owner-mfa-code" class="ui-label">Kode authenticator</label>
            <input id="owner-mfa-code" type="text" inputmode="numeric" autocomplete="one-time-code" wire:model="code" maxlength="6" class="ui-input" required>
            @error('code') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
            <button type="submit" class="btn btn-primary w-full">Verifikasi</button>
        </form>
    </section>
</div>
