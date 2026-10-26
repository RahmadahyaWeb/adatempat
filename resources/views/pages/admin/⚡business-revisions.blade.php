<?php

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Peninjauan revisi usaha')] class extends Component {
    public string $rejectingRevisionPublicId = '';

    public string $reviewReason = '';

    #[Computed]
    public function pendingRevisions(): Collection
    {
        return BusinessRevision::query()
            ->where('status', BusinessRevision::STATUS_PENDING)
            ->with(['business.owner', 'submittedBy'])
            ->orderBy('created_at')
            ->get();
    }

    private function requireSuperAdmin(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);

        return $user;
    }

    public function approve(string $revisionPublicId): void
    {
        $actor = $this->requireSuperAdmin();

        DB::transaction(function () use ($actor, $revisionPublicId): void {
            $revision = BusinessRevision::query()
                ->where('public_id', $revisionPublicId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($revision->status === BusinessRevision::STATUS_PENDING, 404);

            $business = Business::query()
                ->whereKey($revision->business_id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($business->status === Business::STATUS_ACTIVE, 404);

            $beforeState = [
                'business' => [
                    'name' => $business->name,
                    'slug' => $business->slug,
                    'address' => $business->address,
                    'latitude' => $business->latitude,
                    'longitude' => $business->longitude,
                ],
                'revision_status' => $revision->status,
            ];

            $proposedName = trim($revision->proposed_name);

            $business->fill([
                'name' => $proposedName,
                'address' => $revision->proposed_address,
                'latitude' => $revision->proposed_latitude,
                'longitude' => $revision->proposed_longitude,
            ])->save();

            $revision->fill([
                'status' => BusinessRevision::STATUS_APPROVED,
                'review_reason' => null,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
            ])->save();

            AuditLog::create([
                'actor_user_id' => $actor->getKey(),
                'actor_role' => $actor->role,
                'action' => 'business_revision.approved',
                'auditable_type' => BusinessRevision::class,
                'auditable_id' => $revision->getKey(),
                'reason' => null,
                'before_state_json' => $beforeState,
                'after_state_json' => [
                    'business' => [
                        'name' => $business->name,
                        'slug' => $business->slug,
                        'address' => $business->address,
                        'latitude' => $business->latitude,
                        'longitude' => $business->longitude,
                    ],
                    'revision_status' => $revision->status,
                ],
                'ip_hash' => hash_hmac('sha256', (string) request()->ip(), (string) config('app.key')),
                'created_at' => now(),
            ]);
        });

        unset($this->pendingRevisions);
        $this->cancelReject();
        session()->flash('status', 'Revisi identitas disetujui. Data usaha yang dipublikasikan telah diperbarui.');
    }

    public function beginReject(string $revisionPublicId): void
    {
        $this->requireSuperAdmin();

        BusinessRevision::query()
            ->where('public_id', $revisionPublicId)
            ->where('status', BusinessRevision::STATUS_PENDING)
            ->firstOrFail();

        $this->rejectingRevisionPublicId = $revisionPublicId;
        $this->reviewReason = '';
        $this->resetValidation();
    }

    public function cancelReject(): void
    {
        $this->rejectingRevisionPublicId = '';
        $this->reviewReason = '';
        $this->resetValidation();
    }

    public function reject(): void
    {
        $actor = $this->requireSuperAdmin();

        $validated = $this->validate([
            'rejectingRevisionPublicId' => ['required', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
            'reviewReason' => ['required', 'string', 'max:5000'],
        ], [
            'reviewReason.required' => 'Alasan penolakan wajib diisi.',
            'reviewReason.max' => 'Alasan penolakan maksimal 5.000 karakter.',
        ]);

        DB::transaction(function () use ($actor, $validated): void {
            $revision = BusinessRevision::query()
                ->where('public_id', $validated['rejectingRevisionPublicId'])
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($revision->status === BusinessRevision::STATUS_PENDING, 404);

            $beforeState = [
                'status' => $revision->status,
                'review_reason' => $revision->review_reason,
                'business_id' => $revision->business_id,
            ];

            $revision->fill([
                'status' => BusinessRevision::STATUS_REJECTED,
                'review_reason' => trim($validated['reviewReason']),
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
            ])->save();

            AuditLog::create([
                'actor_user_id' => $actor->getKey(),
                'actor_role' => $actor->role,
                'action' => 'business_revision.rejected',
                'auditable_type' => BusinessRevision::class,
                'auditable_id' => $revision->getKey(),
                'reason' => $revision->review_reason,
                'before_state_json' => $beforeState,
                'after_state_json' => [
                    'status' => $revision->status,
                    'review_reason' => $revision->review_reason,
                    'business_id' => $revision->business_id,
                ],
                'ip_hash' => hash_hmac('sha256', (string) request()->ip(), (string) config('app.key')),
                'created_at' => now(),
            ]);
        });

        unset($this->pendingRevisions);
        $this->cancelReject();
        session()->flash('status', 'Revisi identitas ditolak. Data publik yang lama tidak berubah.');
    }
};
?>

<div class="site-container py-7 sm:py-10 lg:py-12">
    <header class="max-w-3xl">
        <p class="eyebrow"><span class="eyebrow-dot"></span> Ruang Super Admin</p>
        <h1 class="mt-3 text-3xl font-extrabold leading-tight tracking-[-0.055em] sm:text-4xl">Peninjauan revisi usaha</h1>
        <p class="mt-3 text-sm leading-7 text-[var(--ink-soft)] sm:text-base">
            Tinjau perubahan identitas usaha yang sudah aktif. Data publik tetap memakai identitas yang disetujui sebelumnya sampai revisi disetujui.
        </p>
    </header>

    @if (session('status'))
        <div role="status" class="status-note status-success mt-6">{{ session('status') }}</div>
    @endif

    @php($revisions = $this->pendingRevisions)

    <section aria-labelledby="pending-revisions-heading" class="mt-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="pending-revisions-heading" class="text-xl font-extrabold tracking-[-0.04em] sm:text-2xl">Menunggu peninjauan</h2>
                <p class="mt-1 text-sm text-[var(--muted)]">Setiap keputusan akan dicatat pada audit log.</p>
            </div>
            <span class="text-sm font-semibold text-[var(--muted)]">{{ $revisions->count() }} revisi</span>
        </div>

        @if ($revisions->isEmpty())
            <div class="mt-5 rounded-2xl bg-white px-5 py-10 text-center sm:px-8 sm:py-12">
                <h3 class="text-lg font-extrabold tracking-[-0.03em]">Tidak ada revisi yang menunggu.</h3>
                <p class="mt-2 text-sm leading-6 text-[var(--muted)]">Pengajuan revisi identitas usaha yang baru akan muncul di halaman ini.</p>
            </div>
        @else
            <div class="mt-5 space-y-4">
                @foreach ($revisions as $revision)
                    <article wire:key="revision-{{ $revision->public_id }}" class="rounded-2xl bg-white p-5 sm:p-7">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-[0.08em] text-[var(--muted)]">Usaha saat ini</p>
                                <h3 class="mt-2 break-words text-lg font-extrabold tracking-[-0.035em]">{{ $revision->business->name }}</h3>
                                <p class="mt-1 break-words text-sm leading-6 text-[var(--ink-soft)]">{{ $revision->business->address ?: 'Alamat belum diisi' }}</p>
                                <p class="mt-2 text-xs leading-5 text-[var(--muted)]">
                                    Pemilik: {{ $revision->business->owner?->name ?? $revision->submittedBy->name }}
                                    · Diajukan {{ $revision->created_at?->format('d/m/Y H:i') }}
                                </p>
                            </div>
                            <span class="inline-flex w-fit items-center rounded-md bg-[var(--warning-bg)] px-2.5 py-1 text-xs font-bold text-[var(--warning-ink)]">Menunggu peninjauan</span>
                        </div>

                        <div class="mt-5 grid gap-4 rounded-xl bg-[var(--surface-soft)] p-4 sm:grid-cols-2 sm:p-5">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.07em] text-[var(--muted)]">Usulan nama</p>
                                <p class="mt-2 break-words text-sm font-semibold">{{ $revision->proposed_name }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.07em] text-[var(--muted)]">Usulan alamat</p>
                                <p class="mt-2 break-words text-sm font-semibold">{{ $revision->proposed_address ?: 'Alamat dikosongkan' }}</p>
                            </div>
                        </div>

                        @if ($rejectingRevisionPublicId === $revision->public_id)
                            <form wire:submit="reject" class="mt-5 space-y-3">
                                <div>
                                    <label for="review-reason-{{ $revision->public_id }}" class="ui-label">Alasan penolakan <span class="text-[var(--danger-ink)]">*</span></label>
                                    <textarea id="review-reason-{{ $revision->public_id }}" wire:model="reviewReason" rows="3" maxlength="5000" required class="ui-input resize-y" placeholder="Jelaskan bagian yang perlu diperbaiki."></textarea>
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
                                <button type="button" wire:click="beginReject('{{ $revision->public_id }}')" class="btn btn-secondary w-full sm:w-auto">Tolak revisi</button>
                                <button type="button" wire:click="approve('{{ $revision->public_id }}')" wire:confirm="Setujui perubahan identitas ini? Informasi usaha publik akan diperbarui." wire:loading.attr="disabled" class="btn btn-primary w-full sm:w-auto">
                                    <span wire:loading.remove wire:target="approve">Setujui revisi</span>
                                    <span wire:loading wire:target="approve">Memproses...</span>
                                </button>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
