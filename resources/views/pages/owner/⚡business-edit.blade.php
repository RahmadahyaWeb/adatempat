<?php

use App\Models\Business;
use App\Models\BusinessRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profil usaha')] class extends Component {
    public string $businessPublicId;

    public string $name = '';

    public string $address = '';

    public function mount(string $business): void
    {
        $record = auth()->user()
            ->businesses()
            ->where('public_id', $business)
            ->firstOrFail();

        $this->businessPublicId = (string) $record->public_id;
        $this->name = $record->name;
        $this->address = (string) ($record->address ?? '');
    }

    #[Computed]
    public function currentBusiness(): Business
    {
        return auth()->user()
            ->businesses()
            ->withCount('venues')
            ->where('public_id', $this->businessPublicId)
            ->firstOrFail();
    }

    #[Computed]
    public function pendingRevision(): ?BusinessRevision
    {
        return $this->currentBusiness
            ->revisions()
            ->where('status', BusinessRevision::STATUS_PENDING)
            ->latest('created_at')
            ->first();
    }

    #[Computed]
    public function latestRejectedRevision(): ?BusinessRevision
    {
        return $this->currentBusiness
            ->revisions()
            ->where('status', BusinessRevision::STATUS_REJECTED)
            ->latest('created_at')
            ->first();
    }

    public function canEditIdentity(): bool
    {
        return in_array(
            $this->currentBusiness->status,
            [Business::STATUS_DRAFT, Business::STATUS_REJECTED],
            true,
        );
    }

    public function canSubmitRevision(): bool
    {
        return $this->currentBusiness->status === Business::STATUS_ACTIVE
            && $this->pendingRevision === null;
    }

    public function save(): void
    {
        $owner = auth()->user();

        abort_unless($owner && $owner->isOwner(), 403);

        $business = $owner->businesses()
            ->where('public_id', $this->businessPublicId)
            ->firstOrFail();

        abort_unless(
            in_array($business->status, [
                Business::STATUS_DRAFT,
                Business::STATUS_REJECTED,
                Business::STATUS_ACTIVE,
            ], true),
            403,
        );

        $validated = $this->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:2000'],
            ],
            [
                'name.required' => 'Nama usaha wajib diisi.',
                'name.max' => 'Nama usaha maksimal 255 karakter.',
                'address.max' => 'Alamat maksimal 2.000 karakter.',
            ],
        );

        $businessName = trim($validated['name']);
        $businessAddress = trim((string) ($validated['address'] ?? '')) ?: null;

        if ($business->status === Business::STATUS_ACTIVE) {
            $revision = DB::transaction(function () use ($owner, $business, $businessName, $businessAddress): ?BusinessRevision {
                $lockedBusiness = $owner->businesses()
                    ->whereKey($business->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless($lockedBusiness->status === Business::STATUS_ACTIVE, 403);

                if ($lockedBusiness->revisions()->where('status', BusinessRevision::STATUS_PENDING)->exists()) {
                    $this->addError('name', 'Sudah ada revisi identitas yang menunggu peninjauan.');

                    return null;
                }

                if (
                    $businessName === $lockedBusiness->name
                    && $businessAddress === ($lockedBusiness->address ?: null)
                ) {
                    session()->flash('status', 'Tidak ada perubahan identitas untuk diajukan.');

                    return null;
                }

                return $lockedBusiness->revisions()->create([
                    'submitted_by' => $owner->getKey(),
                    'proposed_name' => $businessName,
                    'proposed_address' => $businessAddress,
                    'proposed_latitude' => $lockedBusiness->latitude,
                    'proposed_longitude' => $lockedBusiness->longitude,
                ]);
            });

            if ($revision === null) {
                return;
            }

            $this->resetValidation();
            unset($this->currentBusiness, $this->pendingRevision);
            session()->flash(
                'status',
                'Revisi identitas berhasil diajukan. Informasi publik tetap menggunakan data yang saat ini disetujui sampai Super Admin meninjau revisi.',
            );

            return;
        }

        $slugBase = Str::slug($businessName) ?: 'usaha';
        $slug = $slugBase;
        $suffix = 2;

        while (Business::query()
            ->where('slug', $slug)
            ->where('id', '!=', $business->getKey())
            ->exists()) {
            $slug = $slugBase . '-' . $suffix;
            $suffix++;
        }

        $business->fill([
            'name' => $businessName,
            'slug' => $slug,
            'address' => $businessAddress,
        ])->save();

        $this->resetValidation();
        unset($this->currentBusiness);

        session()->flash('status', 'Perubahan profil dasar tersimpan. Status usaha tidak berubah.');
    }

};
?>

<div class="site-container py-7 sm:py-10 lg:py-12">
    @php
        $business = $this->currentBusiness;
        $statusMeta = match ($business->status) {
            'draft' => ['Draft', 'bg-[var(--surface-soft)] text-[var(--ink-soft)]'],
            'pending_verification' => ['Menunggu verifikasi', 'bg-[var(--warning-bg)] text-[var(--warning-ink)]'],
            'active' => ['Aktif', 'bg-[var(--success-bg)] text-[var(--success-ink)]'],
            'suspended' => ['Ditangguhkan', 'bg-[var(--danger-bg)] text-[var(--danger-ink)]'],
            'rejected' => ['Ditolak', 'bg-[var(--danger-bg)] text-[var(--danger-ink)]'],
            default => [$business->status, 'bg-[var(--surface-soft)] text-[var(--ink-soft)]'],
        };
        $canEdit = $this->canEditIdentity();
        $canSubmit = $canEdit || $this->canSubmitRevision();
        $pendingRevision = $this->pendingRevision;
        $latestRejectedRevision = $this->latestRejectedRevision;
    @endphp

    <a href="{{ route('owner.businesses.index') }}" class="inline-flex min-h-9 items-center gap-2 text-sm font-semibold text-[var(--ink-soft)] underline-offset-4 hover:text-[var(--brand-forest)] hover:underline">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m15 18-6-6 6-6"/></svg>
        Kembali ke daftar usaha
    </a>

    <header class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p class="eyebrow"><span class="eyebrow-dot"></span> Profil usaha</p>
            <h1 class="mt-2 break-words text-3xl font-extrabold leading-tight tracking-[-0.055em] sm:text-4xl">{{ $business->name }}</h1>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-bold {{ $statusMeta[1] }}">{{ $statusMeta[0] }}</span>
                <span class="text-sm text-[var(--muted)]">{{ $business->venues_count }} venue terdaftar</span>
            </div>
        </div>
    </header>

    @if (session('status'))
        <div role="status" class="status-note status-success mt-6">{{ session('status') }}</div>
    @endif

    <div class="mt-7 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_19rem] lg:gap-8">
        <section aria-labelledby="business-profile-heading" class="rounded-2xl bg-white p-5 sm:p-7">
            <div>
                <h2 id="business-profile-heading" class="text-xl font-extrabold tracking-[-0.04em] sm:text-2xl">Informasi dasar</h2>
                <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Nama dan alamat membantu pelanggan mengenali usaha saat profil publik tersedia.</p>
            </div>

            @if (!$canEdit)
                <div class="status-note mt-5">
                    @if ($business->status === 'active')
                        Usaha ini sedang aktif. Perubahan nama atau alamat akan diajukan sebagai revisi dan harus disetujui Super Admin. Data publik yang sudah disetujui tidak akan berubah sebelum peninjauan selesai.
                    @elseif ($business->status === 'pending_verification')
                        Usaha sedang menunggu verifikasi. Informasi identitas dikunci selama proses peninjauan.
                    @elseif ($business->status === 'suspended')
                        Usaha sedang ditangguhkan. Informasi ini hanya dapat ditinjau dari halaman ini.
                    @else
                        Informasi identitas saat ini tidak dapat diedit.
                    @endif
                </div>
            @elseif ($business->status === 'rejected')
                <div class="status-note mt-5">
                    Profil dapat diperbaiki. Menyimpan perubahan tidak mengubah status penolakan atau otomatis mengajukan ulang verifikasi.
                </div>
            @endif

            @if (!$pendingRevision && $latestRejectedRevision)
                <div class="status-note mt-5">
                    <p class="font-bold">Revisi identitas sebelumnya ditolak</p>
                    <p class="mt-2 text-sm leading-6">Usulan nama: <strong>{{ $latestRejectedRevision->proposed_name }}</strong></p>
                    <p class="mt-1 text-sm leading-6">Alasan: {{ $latestRejectedRevision->review_reason ?: 'Alasan penolakan belum tersedia.' }}</p>
                    <p class="mt-2 text-xs leading-5">Data publik tetap menggunakan identitas yang disetujui. Perbaiki usulan lalu ajukan revisi baru.</p>
                </div>
            @endif

            @if ($pendingRevision)
                <div class="status-note mt-5">
                    <p class="font-bold">Revisi identitas menunggu peninjauan</p>
                    <p class="mt-2 text-sm leading-6">Usulan nama: <strong>{{ $pendingRevision->proposed_name }}</strong></p>
                    <p class="mt-1 text-sm leading-6">Usulan alamat: <strong>{{ $pendingRevision->proposed_address ?: 'Belum diisi' }}</strong></p>
                    <p class="mt-2 text-xs leading-5">Data publik tetap memakai nama dan alamat saat ini sampai revisi disetujui atau ditolak.</p>
                </div>
            @endif

            <form wire:submit="save" class="mt-6 space-y-5">
                <div>
                    <label for="business-name" class="ui-label">Nama usaha</label>
                    <input id="business-name" type="text" wire:model="name" required maxlength="255" class="ui-input" @disabled(!$canSubmit) aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                    @error('name') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="business-address" class="ui-label">Alamat usaha</label>
                    <textarea id="business-address" wire:model="address" rows="3" maxlength="2000" class="ui-input resize-y" placeholder="Jalan, area, kota" @disabled(!$canSubmit) aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}"></textarea>
                    @error('address') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                @if ($canSubmit)
                    <div class="flex flex-col-reverse gap-2 border-t border-[var(--border)] pt-5 sm:flex-row sm:justify-end">
                        <a href="{{ route('owner.businesses.index') }}" class="btn btn-secondary w-full sm:w-auto">Kembali</a>
                        <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full sm:w-auto">
                            <span wire:loading.remove wire:target="save">{{ $business->status === \App\Models\Business::STATUS_ACTIVE ? 'Ajukan revisi untuk ditinjau' : 'Simpan perubahan' }}</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                @endif
            </form>
        </section>

        <aside class="rounded-2xl bg-[var(--surface-soft)] p-5 sm:p-6">
            <p class="eyebrow"><span class="eyebrow-dot"></span> Berikutnya</p>
            <h2 class="mt-3 text-lg font-extrabold tracking-[-0.035em]">Siapkan informasi venue</h2>
            <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Sebelum usaha dapat ditampilkan ke publik, data venue, foto, informasi operasional, dan proses verifikasi perlu diselesaikan.</p>
            <div class="mt-5 space-y-3 border-t border-[var(--border)] pt-5">
                <div class="flex items-start gap-3">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-[var(--brand-lime)] text-xs font-extrabold text-[var(--ink)]">1</span>
                    <div><p class="text-sm font-bold">Profil usaha</p><p class="mt-1 text-xs leading-5 text-[var(--muted)]">Nama dan alamat dasar</p></div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-white text-xs font-extrabold text-[var(--ink-soft)]">2</span>
                    <div><p class="text-sm font-bold">Detail venue</p><p class="mt-1 text-xs leading-5 text-[var(--muted)]">Informasi ruang dan kategori</p></div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-white text-xs font-extrabold text-[var(--ink-soft)]">3</span>
                    <div><p class="text-sm font-bold">Tinjau dan verifikasi</p><p class="mt-1 text-xs leading-5 text-[var(--muted)]">Sebelum profil ditampilkan kepada publik</p></div>
                </div>
            </div>
            <p class="mt-5 text-xs leading-5 text-[var(--muted)]">Kelola nama, kategori, dan deskripsi venue dari katalog usaha ini. Pengaturan foto, harga, dan jadwal menyusul pada modul berikutnya.</p>
            @if ($business->status !== \App\Models\Business::STATUS_SUSPENDED)
                <a href="{{ route('owner.businesses.venues.index', ['business' => $business->public_id]) }}" class="btn btn-primary mt-5 w-full">
                    Kelola venue
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M4 12h15m-6-6 6 6-6 6"/></svg>
                </a>
            @endif
        </aside>
    </div>
</div>