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

<div class="site-container grid max-w-5xl items-center gap-8 py-8 sm:py-12 lg:min-h-[calc(100vh-9rem)] lg:grid-cols-[minmax(0,.85fr)_minmax(0,1.15fr)] lg:gap-14 lg:py-14">
    <aside class="rounded-[1.75rem] bg-[#E9F3EC] p-6 sm:p-8 lg:p-10">
        <p class="editorial-eyebrow text-[#49634E]"><span class="size-2 rounded-full bg-[var(--accent-sage)]"></span> Keamanan administrator</p>
        <h1 class="mt-5 font-editorial text-4xl leading-tight tracking-[-0.035em] text-[#203E2A] sm:text-5xl">Satu lapis perlindungan tambahan.</h1>
        <p class="mt-4 text-sm leading-7 text-[#49634E]">Autentikasi dua faktor menambahkan langkah verifikasi saat masuk ke area administrasi Adatempat.</p>
        <div class="mt-7 flex items-start gap-3 border-t border-[#C8D9CA] pt-5 text-sm leading-6 text-[#49634E]">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-5 shrink-0"><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/></svg>
            <p>Simpan secret dengan aman. Siapa pun yang memilikinya dapat membuat kode autentikasi.</p>
        </div>
    </aside>

    <section class="ui-surface p-6 sm:p-8 lg:p-10">
        <p class="editorial-eyebrow text-[var(--accent-primary)]">Pengaturan keamanan / 01</p>
        <h2 class="mt-4 font-editorial text-3xl leading-tight sm:text-4xl">Aktifkan autentikasi dua faktor.</h2>
        <p class="mt-3 text-sm leading-6 text-[var(--text-secondary)]">Gunakan aplikasi authenticator yang mendukung TOTP, seperti Google Authenticator, Microsoft Authenticator, atau 1Password.</p>

        <section class="mt-7 rounded-2xl border border-[var(--border-subtle)] bg-[var(--bg-canvas)] p-4 sm:p-5">
            <p class="font-metadata text-[10px] uppercase tracking-[0.14em] text-[var(--text-muted)]">01 / Hubungkan aplikasi</p>
            <h3 class="mt-2 font-semibold">Tambahkan akun secara manual</h3>
            <p class="mt-2 text-sm leading-6 text-[var(--text-secondary)]">Masukkan secret ini ke aplikasi authenticator milikmu.</p>
            <div class="mt-4 break-all rounded-xl border border-[var(--border-strong)] bg-[var(--bg-surface)] p-4 font-metadata text-sm leading-6 text-[var(--text-primary)]" aria-label="Secret TOTP">{{ $this->secret }}</div>
            <button type="button" x-data x-on:click="navigator.clipboard.writeText(@js($this->secret))" class="ui-button ui-button-secondary mt-3 min-h-10 w-full sm:w-auto">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="size-4"><rect x="8" y="8" width="13" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></svg>
                Salin secret
            </button>
            <p class="mt-3 text-xs leading-5 text-[var(--text-muted)]">Jangan bagikan secret ini kepada siapa pun.</p>
        </section>

        <section class="mt-5 rounded-2xl border border-[var(--border-subtle)] p-4 sm:p-5">
            <p class="font-metadata text-[10px] uppercase tracking-[0.14em] text-[var(--text-muted)]">02 / Konfirmasi</p>
            <h3 class="mt-2 font-semibold">Masukkan kode enam digit</h3>
            <p class="mt-2 text-sm leading-6 text-[var(--text-secondary)]">Gunakan kode terbaru yang ditampilkan aplikasi authenticator.</p>
            <form wire:submit="activate" class="mt-4 space-y-4">
                <div>
                    <label for="code" class="ui-label">Kode authenticator</label>
                    <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code" class="ui-input font-metadata text-base tracking-[0.3em]" placeholder="000000" required>
                    @error('code') <p role="alert" class="mt-2 text-sm leading-5 text-[#B42318]">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" class="ui-button ui-button-primary w-full">
                    <span wire:loading.remove wire:target="activate">Aktifkan MFA</span>
                    <span wire:loading wire:target="activate">Memverifikasi...</span>
                </button>
            </form>
        </section>
    </section>
</div>
