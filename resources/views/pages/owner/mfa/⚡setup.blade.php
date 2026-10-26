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
        abort_unless($user?->isOwner() && $user->status === 'active', 403);

        if ($user->mfa_enabled_at && $user->mfa_secret) {
            $this->redirectRoute('owner.mfa.challenge');

            return;
        }

        if (! $user->mfa_secret) {
            $user->mfa_secret = $mfaService->generateSecret();
            $user->save();
        }
    }

    #[Computed]
    public function secret(): string
    {
        $user = Auth::user();
        abort_unless($user?->isOwner() && $user->status === 'active', 403);
        abort_if($user->mfa_enabled_at || ! $user->mfa_secret, 403);

        return $user->mfa_secret;
    }

    public function activate(MfaService $mfaService): void
    {
        $this->validate(['code' => ['required', 'digits:6']]);

        $user = Auth::user();
        abort_unless($user?->isOwner() && $user->status === 'active' && $user->mfa_secret && ! $user->mfa_enabled_at, 403);

        if (! $mfaService->verify($user->mfa_secret, $this->code)) {
            $this->addError('code', 'Kode authenticator tidak valid atau sudah kedaluwarsa.');

            return;
        }

        $user->mfa_enabled_at = now();
        $user->save();

        session([
            'owner_mfa_verified_user_id' => (string) $user->getKey(),
            'owner_mfa_verified_at' => now()->timestamp,
        ]);

        $this->redirectRoute('owner.payout-accounts.index');
    }
};
?>
<div class="site-container max-w-3xl py-8 sm:py-12">
    <section class="ui-panel p-5 sm:p-8">
        <p class="eyebrow"><span class="eyebrow-dot"></span> Keamanan Owner</p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-[-0.05em]">Aktifkan MFA untuk rekening payout.</h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ink-soft)]">Perubahan rekening payout memerlukan autentikasi dua faktor. Simpan secret berikut di aplikasi authenticator TOTP Anda.</p>
        <div class="mt-6 rounded-xl border border-[var(--border)] bg-[var(--surface-soft)] p-4">
            <label class="ui-label">Secret TOTP</label>
            <p class="mt-2 break-all rounded-lg border border-[var(--border)] bg-[var(--surface)] p-3 font-mono text-sm">{{ $this->secret }}</p>
            <button type="button" x-data x-on:click="navigator.clipboard.writeText(@js($this->secret))" class="btn btn-secondary mt-3">Salin secret</button>
        </div>
        <form wire:submit="activate" class="mt-6 space-y-3">
            <label for="owner-mfa-code" class="ui-label">Kode authenticator 6 digit</label>
            <input id="owner-mfa-code" type="text" inputmode="numeric" autocomplete="one-time-code" wire:model="code" maxlength="6" class="ui-input" required>
            @error('code') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
            <button type="submit" class="btn btn-primary w-full">Aktifkan MFA</button>
        </form>
    </section>
</div>
