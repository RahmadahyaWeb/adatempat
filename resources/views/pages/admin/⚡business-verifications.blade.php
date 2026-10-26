<?php

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Verifikasi usaha')] class extends Component {
    public string $rejectingBusinessPublicId = '';

    public string $reviewReason = '';

    #[Computed]
    public function pendingBusinesses(): Collection
    {
        return Business::query()
            ->where('status', Business::STATUS_PENDING_VERIFICATION)
            ->with('owner')
            ->withCount([
                'venues',
                'venues as draft_venues_count' => fn ($query) => $query->where('status', Venue::STATUS_DRAFT),
            ])
            ->orderBy('verification_submitted_at')
            ->orderBy('id')
            ->get();
    }

    private function requireSuperAdmin(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);

        return $user;
    }

    public function approve(string $businessPublicId): void
    {
        $actor = $this->requireSuperAdmin();

        DB::transaction(function () use ($actor, $businessPublicId): void {
            $business = Business::query()
                ->where('public_id', $businessPublicId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($business->status === Business::STATUS_PENDING_VERIFICATION, 404);

            abort_unless(
                trim((string) $business->name) !== ''
                    && trim((string) $business->address) !== ''
                    && trim((string) $business->city) !== ''
                    && trim((string) $business->province) !== ''
                    && $business->latitude !== null
                    && $business->longitude !== null
                    && $business->venues()->where('status', Venue::STATUS_DRAFT)->exists(),
                422,
                'Data usaha belum memenuhi persyaratan verifikasi.',
            );

            $beforeState = [
                'status' => $business->status,
                'name' => $business->name,
                'address' => $business->address,
                'city' => $business->city,
                'province' => $business->province,
                'latitude' => $business->latitude,
                'longitude' => $business->longitude,
                'verification_submitted_at' => $business->verification_submitted_at?->toISOString(),
            ];

            $business->fill([
                'status' => Business::STATUS_ACTIVE,
                'rejection_reason' => null,
                'verification_reviewed_at' => now(),
                'verification_reviewed_by' => $actor->getKey(),
                'verified_at' => now(),
                'verified_by' => $actor->getKey(),
                // Public visibility remains gated until the subscription lifecycle is implemented.
                'published_at' => null,
            ])->save();

            AuditLog::create([
                'actor_user_id' => $actor->getKey(),
                'actor_role' => $actor->role,
                'action' => 'business_verification.approved',
                'auditable_type' => Business::class,
                'auditable_id' => $business->getKey(),
                'reason' => null,
                'before_state_json' => $beforeState,
                'after_state_json' => [
                    'status' => $business->status,
                    'name' => $business->name,
                    'slug' => $business->slug,
                    'address' => $business->address,
                    'city' => $business->city,
                    'province' => $business->province,
                    'latitude' => $business->latitude,
                    'longitude' => $business->longitude,
                    'rejection_reason' => $business->rejection_reason,
                    'verification_reviewed_at' => $business->verification_reviewed_at?->toISOString(),
                    'verification_reviewed_by' => $business->verification_reviewed_by,
                    'verified_at' => $business->verified_at?->toISOString(),
                    'verified_by' => $business->verified_by,
                    'published_at' => $business->published_at?->toISOString(),
                ],
                'ip_hash' => hash_hmac('sha256', (string) request()->ip(), (string) config('app.key')),
                'created_at' => now(),
            ]);
        });

        unset($this->pendingBusinesses);
        $this->cancelReject();
        session()->flash('status', 'Usaha berhasil diverifikasi. Publikasi tetap menunggu persyaratan langganan dan kesiapan venue.');
    }

    public function beginReject(string $businessPublicId): void
    {
        $this->requireSuperAdmin();

        Business::query()
            ->where('public_id', $businessPublicId)
            ->where('status', Business::STATUS_PENDING_VERIFICATION)
            ->firstOrFail();

        $this->rejectingBusinessPublicId = $businessPublicId;
        $this->reviewReason = '';
        $this->resetValidation();
    }

    public function cancelReject(): void
    {
        $this->rejectingBusinessPublicId = '';
        $this->reviewReason = '';
        $this->resetValidation();
    }

    public function reject(): void
    {
        $actor = $this->requireSuperAdmin();

        $validated = $this->validate([
            'rejectingBusinessPublicId' => ['required', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
            'reviewReason' => ['required', 'string', 'max:5000'],
        ], [
            'reviewReason.required' => 'Alasan penolakan wajib diisi.',
            'reviewReason.max' => 'Alasan penolakan maksimal 5.000 karakter.',
        ]);

        DB::transaction(function () use ($actor, $validated): void {
            $business = Business::query()
                ->where('public_id', $validated['rejectingBusinessPublicId'])
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($business->status === Business::STATUS_PENDING_VERIFICATION, 404);

            $reason = trim($validated['reviewReason']);
            abort_if($reason === '', 422, 'Alasan penolakan wajib diisi.');

            $beforeState = [
                'status' => $business->status,
                'name' => $business->name,
                'slug' => $business->slug,
                'address' => $business->address,
                'city' => $business->city,
                'province' => $business->province,
                'latitude' => $business->latitude,
                'longitude' => $business->longitude,
                'rejection_reason' => $business->rejection_reason,
                'verification_submitted_at' => $business->verification_submitted_at?->toISOString(),
            ];

            $business->fill([
                'status' => Business::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'verification_reviewed_at' => now(),
                'verification_reviewed_by' => $actor->getKey(),
                'verified_at' => null,
                'verified_by' => null,
                'published_at' => null,
            ])->save();

            AuditLog::create([
                'actor_user_id' => $actor->getKey(),
                'actor_role' => $actor->role,
                'action' => 'business_verification.rejected',
                'auditable_type' => Business::class,
                'auditable_id' => $business->getKey(),
                'reason' => $reason,
                'before_state_json' => $beforeState,
                'after_state_json' => [
                    'status' => $business->status,
                    'name' => $business->name,
                    'slug' => $business->slug,
                    'address' => $business->address,
                    'city' => $business->city,
                    'province' => $business->province,
                    'latitude' => $business->latitude,
                    'longitude' => $business->longitude,
                    'rejection_reason' => $business->rejection_reason,
                    'verification_reviewed_at' => $business->verification_reviewed_at?->toISOString(),
                    'verification_reviewed_by' => $business->verification_reviewed_by,
                    'verified_at' => $business->verified_at?->toISOString(),
                    'verified_by' => $business->verified_by,
                    'published_at' => $business->published_at?->toISOString(),
                ],
                'ip_hash' => hash_hmac('sha256', (string) request()->ip(), (string) config('app.key')),
                'created_at' => now(),
            ]);
        });

        unset($this->pendingBusinesses);
        $this->cancelReject();
        session()->flash('status', 'Pengajuan verifikasi ditolak. Alasan penolakan tersedia di halaman profil usaha Owner.');
    }
};
?>

<div class="site-container py-7 sm:py-10 lg:py-12">
    <header class="max-w-3xl">
        <p class="eyebrow"><span class="eyebrow-dot"></span> Administrasi platform</p>
        <h1 class="mt-3 text-3xl font-extrabold leading-tight tracking-[-0.055em] sm:text-4xl">Verifikasi usaha</h1>
        <p class="mt-3 text-sm leading-7 text-[var(--ink-soft)] sm:text-base">Tinjau kelengkapan identitas dan lokasi usaha sebelum menyetujui verifikasi. Persetujuan verifikasi tidak otomatis menerbitkan profil ke publik; publikasi tetap mengikuti persyaratan langganan dan kesiapan venue.</p>
    </header>

    @if (session('status'))
        <div role="status" class="status-note status-success mt-6">{{ session('status') }}</div>
    @endif

    @php($businesses = $this->pendingBusinesses)

    <section aria-labelledby="business-verification-queue-heading" class="mt-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="business-verification-queue-heading" class="text-xl font-extrabold tracking-[-0.04em] sm:text-2xl">Antrean peninjauan</h2>
                <p class="mt-1 text-sm text-[var(--muted)]">Penolakan wajib disertai alasan yang dapat dilihat Owner.</p>
            </div>
            <span class="text-sm font-semibold text-[var(--muted)]">{{ $businesses->count() }} pengajuan</span>
        </div>

        @if ($businesses->isEmpty())
            <div class="mt-5 rounded-2xl bg-white px-5 py-10 text-center sm:px-8 sm:py-12">
                <span class="mx-auto flex size-12 items-center justify-center rounded-xl bg-[var(--surface-lime)] text-[var(--brand-forest)]">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6"><path d="M5 12l4 4L19 6"/></svg>
                </span>
                <h3 class="mt-4 text-lg font-extrabold tracking-[-0.03em]">Tidak ada pengajuan yang menunggu.</h3>
                <p class="mt-2 text-sm leading-6 text-[var(--muted)]">Pengajuan baru dari Owner akan muncul di antrean ini.</p>
            </div>
        @else
            <div class="mt-5 space-y-4">
                @foreach ($businesses as $business)
                    <article wire:key="business-verification-{{ $business->public_id }}" class="rounded-2xl bg-white p-5 sm:p-7">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <span class="inline-flex items-center rounded-md bg-[var(--warning-bg)] px-2.5 py-1 text-xs font-bold text-[var(--warning-ink)]">Menunggu verifikasi</span>
                                <h3 class="mt-3 break-words text-xl font-extrabold tracking-[-0.035em]">{{ $business->name }}</h3>
                                <p class="mt-1 break-words text-sm leading-6 text-[var(--ink-soft)]">{{ $business->address }}</p>
                                <p class="mt-1 break-words text-sm leading-6 text-[var(--muted)]">{{ $business->city }}, {{ $business->province }}</p>
                                <p class="mt-3 text-xs leading-5 text-[var(--muted)]">Pemilik: {{ $business->owner?->name ?? 'Akun Owner tidak tersedia' }} · Diajukan {{ $business->verification_submitted_at?->format('d/m/Y H:i') ?? $business->updated_at?->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-3 rounded-xl bg-[var(--surface-soft)] p-4 sm:grid-cols-2 sm:p-5">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.07em] text-[var(--muted)]">Koordinat</p>
                                <p class="mt-2 break-all text-sm font-semibold">{{ $business->latitude }}, {{ $business->longitude }}</p>
                                <a href="https://www.openstreetmap.org/?mlat={{ $business->latitude }}&mlon={{ $business->longitude }}#map=16/{{ $business->latitude }}/{{ $business->longitude }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex text-xs font-bold text-[var(--brand-forest)] underline-offset-4 hover:underline">Periksa titik di peta</a>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.07em] text-[var(--muted)]">Kesiapan data</p>
                                <p class="mt-2 text-sm font-semibold">{{ $business->draft_venues_count }} venue Draft · {{ $business->venues_count }} total venue</p>
                                <p class="mt-1 text-xs leading-5 text-[var(--muted)]">Publikasi profil belum dilakukan oleh keputusan ini.</p>
                            </div>
                        </div>

                        @if ($rejectingBusinessPublicId === $business->public_id)
                            <form wire:submit="reject" class="mt-5 space-y-3">
                                <div>
                                    <label for="verification-reason-{{ $business->public_id }}" class="ui-label">Alasan penolakan <span class="text-[var(--danger-ink)]">*</span></label>
                                    <textarea id="verification-reason-{{ $business->public_id }}" wire:model="reviewReason" rows="3" maxlength="5000" required class="ui-input resize-y" placeholder="Jelaskan informasi yang harus diperbaiki oleh Owner."></textarea>
                                    @error('reviewReason') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                    <button type="button" wire:click="cancelReject" class="btn btn-secondary w-full sm:w-auto">Batal</button>
                                    <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full sm:w-auto">
                                        <span wire:loading.remove wire:target="reject">Konfirmasi penolakan</span>
                                        <span wire:loading wire:target="reject">Menyimpan...</span>
                                    </button>
                                </div>
                            </form>
                        @else
                            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                <button type="button" wire:click="beginReject('{{ $business->public_id }}')" class="btn btn-secondary w-full sm:w-auto">Tolak pengajuan</button>
                                <button type="button" wire:click="approve('{{ $business->public_id }}')" wire:confirm="Setujui verifikasi usaha ini? Profil tidak langsung diterbitkan ke publik." wire:loading.attr="disabled" class="btn btn-primary w-full sm:w-auto">
                                    <span wire:loading.remove wire:target="approve">Setujui verifikasi</span>
                                    <span wire:loading wire:target="approve">Memverifikasi...</span>
                                </button>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
