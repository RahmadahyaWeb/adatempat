<?php

use App\Models\Business;
use App\Models\BusinessRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profil usaha')] class extends Component {
    public string $businessPublicId;

    public string $name = '';

    public string $address = '';

    public string $city = '';

    public string $province = '';

    public string $latitude = '';

    public string $longitude = '';

    public string $timezone = 'Asia/Jakarta';

    public function mount(string $business): void
    {
        $record = auth()->user()
            ->businesses()
            ->where('public_id', $business)
            ->firstOrFail();

        Gate::authorize('view', $record);

        $this->businessPublicId = (string) $record->public_id;
        $this->name = $record->name;
        $this->address = (string) ($record->address ?? '');
        $this->city = (string) ($record->city ?? '');
        $this->province = (string) ($record->province ?? '');
        $this->latitude = $record->latitude !== null ? (string) $record->latitude : '';
        $this->longitude = $record->longitude !== null ? (string) $record->longitude : '';
        $this->timezone = (string) ($record->timezone ?: 'Asia/Jakarta');
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
    public function latestRevision(): ?BusinessRevision
    {
        return $this->currentBusiness
            ->revisions()
            ->latest('created_at')
            ->latest('id')
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

    public function submitForVerification(): void
    {
        $owner = auth()->user();
        abort_unless($owner && $owner->isOwner(), 403);

        $business = $owner->businesses()
            ->where('public_id', $this->businessPublicId)
            ->firstOrFail();

        Gate::authorize('update', $business);

        abort_unless(
            in_array($business->status, [Business::STATUS_DRAFT, Business::STATUS_REJECTED], true),
            403,
        );

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:2000'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        if (! $business->venues()->where('status', \App\Models\Venue::STATUS_DRAFT)->exists()) {
            $this->addError('verification', 'Tambahkan minimal satu venue draft sebelum mengajukan verifikasi.');

            return;
        }

        DB::transaction(function () use ($owner, $business, $validated): void {
            $locked = $owner->businesses()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                in_array($locked->status, [Business::STATUS_DRAFT, Business::STATUS_REJECTED], true),
                403,
            );

            $before = [
                'status' => $locked->status,
                'name' => $locked->name,
                'address' => $locked->address,
                'city' => $locked->city,
                'province' => $locked->province,
                'latitude' => $locked->latitude,
                'longitude' => $locked->longitude,
            ];

            $locked->update([
                'name' => trim($validated['name']),
                'address' => trim($validated['address']),
                'city' => trim($validated['city']),
                'province' => trim($validated['province']),
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'status' => Business::STATUS_PENDING_VERIFICATION,
                'rejection_reason' => null,
                'verification_submitted_at' => now('UTC'),
                'verification_reviewed_at' => null,
                'verification_reviewed_by' => null,
                'verified_at' => null,
                'verified_by' => null,
                'published_at' => null,
            ]);

            \App\Models\AuditLog::query()->create([
                'actor_user_id' => $owner->getKey(),
                'actor_role' => $owner->role,
                'action' => 'business_verification.submitted',
                'auditable_type' => Business::class,
                'auditable_id' => $locked->getKey(),
                'reason' => 'Owner submitted business identity for Super Admin verification.',
                'before_state_json' => $before,
                'after_state_json' => [
                    'status' => $locked->status,
                    'name' => $locked->name,
                    'address' => $locked->address,
                    'city' => $locked->city,
                    'province' => $locked->province,
                    'latitude' => $locked->latitude,
                    'longitude' => $locked->longitude,
                    'verification_submitted_at' => $locked->verification_submitted_at?->toISOString(),
                ],
                'ip_hash' => request()->ip() ? hash('sha256', (string) request()->ip()) : null,
                'created_at' => now('UTC'),
            ]);
        }, attempts: 3);

        $this->resetValidation();
        unset($this->currentBusiness);
        session()->flash('status', 'Usaha berhasil diajukan untuk verifikasi.');
    }

    public function saveTimezone(): void
    {
        $owner = auth()->user();

        abort_unless($owner && $owner->isOwner(), 403);

        $business = $owner->businesses()
            ->where('public_id', $this->businessPublicId)
            ->firstOrFail();

        Gate::authorize('update', $business);

        abort_unless(
            in_array($business->status, [
                Business::STATUS_DRAFT,
                Business::STATUS_REJECTED,
                Business::STATUS_ACTIVE,
            ], true),
            403,
        );

        $validated = $this->validate([
            'timezone' => ['required', 'string', Rule::in([
                'Asia/Jakarta',
                'Asia/Makassar',
                'Asia/Jayapura',
            ])],
        ], [
            'timezone.required' => 'Pilih zona waktu usaha.',
            'timezone.in' => 'Zona waktu yang dipilih tidak didukung.',
        ]);

        $business->update(['timezone' => $validated['timezone']]);

        $this->resetValidation('timezone');
        unset($this->currentBusiness);
        session()->flash('status', 'Zona waktu usaha berhasil diperbarui. Jam operasional venue akan menggunakan zona waktu ini.');
    }

    public function save(): void
    {
        $owner = auth()->user();

        abort_unless($owner && $owner->isOwner(), 403);

        $business = $owner->businesses()
            ->where('public_id', $this->businessPublicId)
            ->firstOrFail();

        Gate::authorize('update', $business);

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
                'city' => ['nullable', 'string', 'max:100'],
                'province' => ['nullable', 'string', 'max:100'],
                'latitude' => ['nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            ],
            [
                'name.required' => 'Nama usaha wajib diisi.',
                'name.max' => 'Nama usaha maksimal 255 karakter.',
                'address.max' => 'Alamat maksimal 2.000 karakter.',
            ],
        );

        $businessName = trim($validated['name']);
        $businessAddress = trim((string) ($validated['address'] ?? '')) ?: null;
        $businessCity = trim((string) ($validated['city'] ?? '')) ?: null;
        $businessProvince = trim((string) ($validated['province'] ?? '')) ?: null;
        $businessLatitude = $validated['latitude'] ?? null;
        $businessLongitude = $validated['longitude'] ?? null;

        if ($business->status === Business::STATUS_ACTIVE) {
            $revision = DB::transaction(function () use ($owner, $business, $businessName, $businessAddress, $businessCity, $businessProvince, $businessLatitude, $businessLongitude): ?BusinessRevision {
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
                    && $businessCity === ($lockedBusiness->city ?: null)
                    && $businessProvince === ($lockedBusiness->province ?: null)
                    && (string) $businessLatitude === (string) $lockedBusiness->latitude
                    && (string) $businessLongitude === (string) $lockedBusiness->longitude
                ) {
                    session()->flash('status', 'Tidak ada perubahan identitas untuk diajukan.');

                    return null;
                }

                return $lockedBusiness->revisions()->create([
                    'submitted_by' => $owner->getKey(),
                    'proposed_name' => $businessName,
                    'proposed_address' => $businessAddress,
                    'proposed_city' => $businessCity,
                    'proposed_province' => $businessProvince,
                    'proposed_latitude' => $businessLatitude,
                    'proposed_longitude' => $businessLongitude,
                ]);
            });

            if ($revision === null) {
                return;
            }

            $this->resetValidation();
            unset($this->currentBusiness, $this->pendingRevision, $this->latestRevision);
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
            'city' => $businessCity,
            'province' => $businessProvince,
            'latitude' => $businessLatitude,
            'longitude' => $businessLongitude,
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
        $latestRevision = $this->latestRevision;
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

            @if (!$pendingRevision && $latestRevision?->status === \App\Models\BusinessRevision::STATUS_REJECTED)
                <div class="status-note mt-5">
                    <p class="font-bold">Revisi identitas terakhir ditolak</p>
                    <p class="mt-2 text-sm leading-6">Usulan nama: <strong>{{ $latestRevision->proposed_name }}</strong></p>
                    <p class="mt-1 text-sm leading-6">Alasan: {{ $latestRevision->review_reason ?: 'Alasan penolakan belum tersedia.' }}</p>
                    <p class="mt-2 text-xs leading-5">Perbaiki informasi yang diminta lalu ajukan revisi baru. Nama dan alamat publik tetap menggunakan data yang disetujui.</p>
                </div>
            @elseif (!$pendingRevision && $latestRevision?->status === \App\Models\BusinessRevision::STATUS_APPROVED)
                <div role="status" class="status-note status-success mt-5">
                    <p class="font-bold">Revisi identitas terakhir disetujui</p>
                    <p class="mt-2 text-sm leading-6">Nama yang disetujui: <strong>{{ $latestRevision->proposed_name }}</strong></p>
                    <p class="mt-1 text-sm leading-6">Alamat yang disetujui: <strong>{{ $latestRevision->proposed_address ?: 'Belum diisi' }}</strong></p>
                    @if ($latestRevision->reviewed_at)
                        <p class="mt-2 text-xs leading-5">Disetujui pada {{ $latestRevision->reviewed_at->format('d/m/Y H:i') }}.</p>
                    @endif
                    <p class="mt-2 text-xs leading-5">Data usaha kini menggunakan identitas yang disetujui. Jika profil sudah dipublikasikan, informasi ini menjadi identitas yang tampil ke publik. Tautan profil tetap menggunakan slug sebelumnya.</p>
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
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="business-city" class="ui-label">Kota</label>
                        <input id="business-city" type="text" wire:model="city" maxlength="100" class="ui-input" @disabled(!$canSubmit)>
                        @error('city') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="business-province" class="ui-label">Provinsi</label>
                        <input id="business-province" type="text" wire:model="province" maxlength="100" class="ui-input" @disabled(!$canSubmit)>
                        @error('province') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="business-latitude" class="ui-label">Latitude</label>
                        <input id="business-latitude" type="number" step="0.0000001" wire:model="latitude" class="ui-input" @disabled(!$canSubmit)>
                        @error('latitude') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="business-longitude" class="ui-label">Longitude</label>
                        <input id="business-longitude" type="number" step="0.0000001" wire:model="longitude" class="ui-input" @disabled(!$canSubmit)>
                        @error('longitude') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                @error('verification') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                @if ($canSubmit)
                    <div class="flex flex-col-reverse gap-2 border-t border-[var(--border)] pt-5 sm:flex-row sm:justify-end">
                        <a href="{{ route('owner.businesses.index') }}" class="btn btn-secondary w-full sm:w-auto">Kembali</a>
                        <button type="submit" wire:loading.attr="disabled" class="btn btn-secondary w-full sm:w-auto">
                            <span wire:loading.remove wire:target="save">{{ $business->status === \App\Models\Business::STATUS_ACTIVE ? 'Ajukan revisi untuk ditinjau' : 'Simpan perubahan' }}</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                        @if (in_array($business->status, [\App\Models\Business::STATUS_DRAFT, \App\Models\Business::STATUS_REJECTED], true))
                            <button type="button" wire:click="submitForVerification" wire:loading.attr="disabled" class="btn btn-primary w-full sm:w-auto">
                                Ajukan verifikasi
                            </button>
                        @endif
                    </div>
                @endif
            </form>
            <section aria-labelledby="business-timezone-heading" class="mt-7 border-t border-[var(--border)] pt-6">
                <div>
                    <h3 id="business-timezone-heading" class="text-base font-extrabold text-[var(--ink)]">Zona waktu usaha</h3>
                    <p class="mt-1 text-sm leading-6 text-[var(--ink-soft)]">Jam operasional dan sesi venue menggunakan zona waktu ini. Pilih zona waktu lokal yang sesuai dengan lokasi usaha.</p>
                </div>
                @if (in_array($business->status, ['draft', 'rejected', 'active'], true))
                    <form wire:submit="saveTimezone" class="mt-4 space-y-3">
                        <div>
                            <label for="business-timezone" class="ui-label">Zona waktu</label>
                            <select id="business-timezone" wire:model="timezone" class="ui-input" required>
                                <option value="Asia/Jakarta">WIB — Asia/Jakarta</option>
                                <option value="Asia/Makassar">WITA — Asia/Makassar</option>
                                <option value="Asia/Jayapura">WIT — Asia/Jayapura</option>
                            </select>
                            @error('timezone') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" wire:loading.attr="disabled" class="btn btn-secondary w-full sm:w-auto">
                            <span wire:loading.remove wire:target="saveTimezone">Simpan zona waktu</span>
                            <span wire:loading wire:target="saveTimezone">Menyimpan...</span>
                        </button>
                    </form>
                @else
                    <p class="mt-3 text-sm text-[var(--muted)]">Zona waktu tidak dapat diubah selama proses verifikasi atau penangguhan.</p>
                @endif
            </section>

        </section>

        <aside class="rounded-2xl bg-[var(--surface-soft)] p-5 sm:p-6">
            @if ($business->status === \App\Models\Business::STATUS_ACTIVE)
                @if ($business->published_at)
                    <p class="eyebrow"><span class="eyebrow-dot"></span> Profil publik</p>
                    <h2 class="mt-3 text-lg font-extrabold tracking-[-0.035em]">Profil usaha sudah aktif</h2>
                    <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Nama dan alamat di profil publik mengikuti identitas yang disetujui. Perubahan identitas berikutnya perlu ditinjau Super Admin sebelum tampil ke publik.</p>
                @else
                    <p class="eyebrow"><span class="eyebrow-dot"></span> Status usaha</p>
                    <h2 class="mt-3 text-lg font-extrabold tracking-[-0.035em]">Identitas usaha berstatus aktif</h2>
                    <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Tanggal publikasi belum tercatat. Pastikan status publikasi sudah benar sebelum membagikan tautan profil.</p>
                @endif

                <div class="mt-5 space-y-3 border-t border-[var(--border)] pt-5">
                    <div class="flex items-start gap-3">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-[var(--success-bg)] text-sm font-extrabold text-[var(--success-ink)]">✓</span>
                        <div>
                            <p class="text-sm font-bold">Identitas usaha</p>
                            <p class="mt-1 text-xs leading-5 text-[var(--muted)]">Nama dan alamat yang disetujui</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-white text-xs font-extrabold text-[var(--ink-soft)]">2</span>
                        <div>
                            <p class="text-sm font-bold">Kelola venue</p>
                            <p class="mt-1 text-xs leading-5 text-[var(--muted)]">Perbarui nama, kategori, dan deskripsi venue</p>
                        </div>
                    </div>
                </div>
            @elseif ($business->status === \App\Models\Business::STATUS_PENDING_VERIFICATION)
                <p class="eyebrow"><span class="eyebrow-dot"></span> Status usaha</p>
                <h2 class="mt-3 text-lg font-extrabold tracking-[-0.035em]">Menunggu verifikasi</h2>
                <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Identitas usaha sedang menunggu keputusan. Perubahan identitas dikunci selama status ini berlangsung.</p>
                <p class="mt-5 border-t border-[var(--border)] pt-5 text-xs leading-5 text-[var(--muted)]">Halaman ini belum menyediakan alur pengajuan ulang verifikasi. Status akan tetap mengikuti data yang tercatat di sistem.</p>
            @elseif ($business->status === \App\Models\Business::STATUS_REJECTED)
                <p class="eyebrow"><span class="eyebrow-dot"></span> Perlu tindakan</p>
                <h2 class="mt-3 text-lg font-extrabold tracking-[-0.035em]">Perbaiki profil usaha</h2>
                <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Kamu dapat memperbaiki nama dan alamat lalu menyimpan perubahan. Penyimpanan tidak otomatis mengajukan ulang verifikasi.</p>
                <p class="mt-5 border-t border-[var(--border)] pt-5 text-xs leading-5 text-[var(--muted)]">Alur pengajuan ulang verifikasi belum tersedia pada versi ini.</p>
            @elseif ($business->status === \App\Models\Business::STATUS_DRAFT)
                <p class="eyebrow"><span class="eyebrow-dot"></span> Langkah berikutnya</p>
                <h2 class="mt-3 text-lg font-extrabold tracking-[-0.035em]">Lengkapi profil usaha</h2>
                <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Usaha berstatus draft belum tampil di katalog publik. Lengkapi identitas dan siapkan detail venue sebelum proses publikasi tersedia.</p>
                <div class="mt-5 space-y-3 border-t border-[var(--border)] pt-5">
                    <div class="flex items-start gap-3">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-[var(--brand-lime)] text-xs font-extrabold text-[var(--ink)]">1</span>
                        <div><p class="text-sm font-bold">Profil usaha</p><p class="mt-1 text-xs leading-5 text-[var(--muted)]">Nama dan alamat dasar</p></div>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-white text-xs font-extrabold text-[var(--ink-soft)]">2</span>
                        <div><p class="text-sm font-bold">Detail venue</p><p class="mt-1 text-xs leading-5 text-[var(--muted)]">Nama, kategori, dan deskripsi ruang</p></div>
                    </div>
                </div>
                <p class="mt-5 text-xs leading-5 text-[var(--muted)]">Pengaturan foto, harga, jam operasional, dan pengajuan verifikasi belum tersedia pada versi ini.</p>
            @else
                <p class="eyebrow"><span class="eyebrow-dot"></span> Status usaha</p>
                <h2 class="mt-3 text-lg font-extrabold tracking-[-0.035em]">Usaha ditangguhkan</h2>
                <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Pengelolaan usaha dibatasi selama status penangguhan. Hubungi pengelola platform melalui kanal dukungan yang tersedia jika perlu meninjau status ini.</p>
            @endif

            @if ($business->status !== \App\Models\Business::STATUS_SUSPENDED)
                <a href="{{ route('owner.businesses.venues.index', ['business' => $business->public_id]) }}" class="btn btn-primary mt-5 w-full">
                    Kelola venue
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M4 12h15m-6-6 6 6-6 6"/></svg>
                </a>
                @if ($business->status === \App\Models\Business::STATUS_ACTIVE && $business->published_at)
                    <a href="{{ route('business.public', ['businessSlug' => $business->slug]) }}" class="btn btn-secondary mt-2 w-full">Lihat profil publik</a>
                @endif
            @endif
        </aside>
    </div>
</div>