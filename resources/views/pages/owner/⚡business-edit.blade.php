<?php

use App\Models\Business;
use App\Models\AuditLog;
use App\Models\BusinessRevision;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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
        $this->latitude = (string) ($record->latitude ?? '');
        $this->longitude = (string) ($record->longitude ?? '');
    }

    #[Computed]
    public function currentBusiness(): Business
    {
        return auth()->user()
            ->businesses()
            ->withCount([
                'venues',
                'venues as draft_venues_count' => fn ($query) => $query->where('status', Venue::STATUS_DRAFT),
            ])
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
        $business = $this->currentBusiness;

        return in_array($business->status, [Business::STATUS_DRAFT, Business::STATUS_REJECTED], true)
            || ($business->status === Business::STATUS_ACTIVE && $business->published_at === null);
    }

    public function canSubmitRevision(): bool
    {
        return $this->currentBusiness->status === Business::STATUS_ACTIVE
            && $this->currentBusiness->published_at !== null
            && $this->pendingRevision === null;
    }

    public function canSubmitVerification(): bool
    {
        return in_array($this->currentBusiness->status, [Business::STATUS_DRAFT, Business::STATUS_REJECTED], true);
    }

    public function submitForVerification(): void
    {
        $owner = auth()->user();
        abort_unless($owner && $owner->isOwner(), 403);

        $business = $owner->businesses()
            ->where('public_id', $this->businessPublicId)
            ->firstOrFail();

        Gate::authorize('update', $business);
        abort_unless(in_array($business->status, [Business::STATUS_DRAFT, Business::STATUS_REJECTED], true), 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:2000'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ], [
            'name.required' => 'Nama usaha wajib diisi sebelum mengajukan verifikasi.',
            'address.required' => 'Alamat lengkap wajib diisi sebelum mengajukan verifikasi.',
            'city.required' => 'Kota atau kabupaten wajib diisi.',
            'province.required' => 'Provinsi wajib diisi.',
            'latitude.required' => 'Koordinat lintang wajib diisi.',
            'latitude.between' => 'Koordinat lintang harus berada di antara -90 dan 90.',
            'longitude.required' => 'Koordinat bujur wajib diisi.',
            'longitude.between' => 'Koordinat bujur harus berada di antara -180 dan 180.',
        ]);

        $submitted = DB::transaction(function () use ($owner, $validated): bool {
            $lockedBusiness = $owner->businesses()
                ->where('public_id', $this->businessPublicId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(in_array($lockedBusiness->status, [Business::STATUS_DRAFT, Business::STATUS_REJECTED], true), 403);

            if (! $lockedBusiness->venues()->where('status', Venue::STATUS_DRAFT)->exists()) {
                return false;
            }

            $beforeState = [
                'status' => $lockedBusiness->status,
                'name' => $lockedBusiness->name,
                'address' => $lockedBusiness->address,
                'city' => $lockedBusiness->city,
                'province' => $lockedBusiness->province,
                'latitude' => $lockedBusiness->latitude,
                'longitude' => $lockedBusiness->longitude,
            ];

            $slugBase = Str::slug(trim($validated['name'])) ?: 'usaha';
            $slug = $slugBase;
            $suffix = 2;

            while (Business::query()
                ->where('slug', $slug)
                ->where('id', '!=', $lockedBusiness->getKey())
                ->exists()) {
                $slug = $slugBase . '-' . $suffix;
                $suffix++;
            }

            $lockedBusiness->fill([
                'status' => Business::STATUS_PENDING_VERIFICATION,
                'slug' => $slug,
                'name' => trim($validated['name']),
                'address' => trim($validated['address']),
                'city' => trim($validated['city']),
                'province' => trim($validated['province']),
                'latitude' => (float) $validated['latitude'],
                'longitude' => (float) $validated['longitude'],
                'rejection_reason' => null,
                'verification_submitted_at' => now(),
                'verification_reviewed_at' => null,
                'verification_reviewed_by' => null,
                'verified_at' => null,
                'verified_by' => null,
                'published_at' => null,
            ])->save();

            AuditLog::create([
                'actor_user_id' => $owner->getKey(),
                'actor_role' => $owner->role,
                'action' => 'business_verification.submitted',
                'auditable_type' => Business::class,
                'auditable_id' => $lockedBusiness->getKey(),
                'reason' => null,
                'before_state_json' => $beforeState,
                'after_state_json' => [
                    'status' => $lockedBusiness->status,
                    'name' => $lockedBusiness->name,
                    'address' => $lockedBusiness->address,
                    'city' => $lockedBusiness->city,
                    'province' => $lockedBusiness->province,
                    'latitude' => $lockedBusiness->latitude,
                    'longitude' => $lockedBusiness->longitude,
                    'verification_submitted_at' => $lockedBusiness->verification_submitted_at?->toISOString(),
                ],
                'ip_hash' => hash_hmac('sha256', (string) request()->ip(), (string) config('app.key')),
                'created_at' => now(),
            ]);

            return true;
        });

        if (! $submitted) {
            $this->addError('verification', 'Tambahkan setidaknya satu venue berstatus Draft sebelum mengajukan verifikasi.');
            return;
        }

        $this->resetValidation();
        unset($this->currentBusiness, $this->pendingRevision, $this->latestRevision);
        session()->flash('status', 'Usaha berhasil diajukan untuk verifikasi. Profil belum akan tampil ke publik sampai persyaratan publikasi terpenuhi.');
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
                'city.max' => 'Kota/kabupaten maksimal 100 karakter.',
                'province.max' => 'Provinsi maksimal 100 karakter.',
                'latitude.between' => 'Koordinat lintang harus berada di antara -90 dan 90.',
                'longitude.between' => 'Koordinat bujur harus berada di antara -180 dan 180.',
            ],
        );

        $businessName = trim($validated['name']);
        $businessAddress = trim((string) ($validated['address'] ?? '')) ?: null;
        $businessCity = trim((string) ($validated['city'] ?? '')) ?: null;
        $businessProvince = trim((string) ($validated['province'] ?? '')) ?: null;
        $businessLatitude = trim((string) ($validated['latitude'] ?? '')) !== '' ? (float) $validated['latitude'] : null;
        $businessLongitude = trim((string) ($validated['longitude'] ?? '')) !== '' ? (float) $validated['longitude'] : null;

        if ($business->status === Business::STATUS_ACTIVE && $business->published_at !== null) {
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
                    && $businessLatitude === ($lockedBusiness->latitude !== null ? (float) $lockedBusiness->latitude : null)
                    && $businessLongitude === ($lockedBusiness->longitude !== null ? (float) $lockedBusiness->longitude : null)
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
            'active' => $business->published_at
                ? ['Aktif', 'bg-[var(--success-bg)] text-[var(--success-ink)]']
                : ['Terverifikasi · Belum tayang', 'bg-[var(--warning-bg)] text-[var(--warning-ink)]'],
            'suspended' => ['Ditangguhkan', 'bg-[var(--danger-bg)] text-[var(--danger-ink)]'],
            'rejected' => ['Ditolak', 'bg-[var(--danger-bg)] text-[var(--danger-ink)]'],
            default => [$business->status, 'bg-[var(--surface-soft)] text-[var(--ink-soft)]'],
        };
        $canEdit = $this->canEditIdentity();
        $canSubmit = $canEdit || $this->canSubmitRevision();
        $pendingRevision = $this->pendingRevision;
        $latestRevision = $this->latestRevision;
        $hasDraftVenue = $business->draft_venues_count > 0;
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
                    @if ($business->status === 'active' && $business->published_at)
                        Usaha ini sudah terverifikasi dan ditampilkan ke publik. Perubahan identitas utama akan diajukan sebagai revisi dan harus disetujui Super Admin.
                    @elseif ($business->status === 'active')
                        Usaha ini sudah terverifikasi. Profil belum ditampilkan ke publik karena pengelolaan status langganan belum tersedia pada versi ini. Perubahan identitas utama tetap harus ditinjau Super Admin.
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
                    <p class="font-bold">Usaha perlu diperbaiki</p>
                    @if ($business->rejection_reason)
                        <p class="mt-2 text-sm leading-6">Alasan: {{ $business->rejection_reason }}</p>
                    @endif
                    <p class="mt-2 text-xs leading-5">Perbaiki informasi lalu ajukan ulang verifikasi. Menyimpan perubahan saja tidak mengirim pengajuan baru.</p>
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
                    <input id="business-name" type="text" wire:model.blur="name" required maxlength="255" class="ui-input" @disabled(!$canSubmit) aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                    @error('name') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="business-address" class="ui-label">Alamat lengkap</label>
                    <textarea id="business-address" wire:model.blur="address" rows="3" maxlength="2000" class="ui-input resize-y" placeholder="Nama jalan, nomor, kelurahan, dan area" @disabled(!$canSubmit) aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}"></textarea>
                    @error('address') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="business-city" class="ui-label">Kota / kabupaten</label>
                        <input id="business-city" type="text" wire:model.blur="city" maxlength="100" class="ui-input" placeholder="Contoh: Banjarbaru" @disabled(!$canSubmit) aria-invalid="{{ $errors->has('city') ? 'true' : 'false' }}">
                        @error('city') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="business-province" class="ui-label">Provinsi</label>
                        <input id="business-province" type="text" wire:model.blur="province" maxlength="100" class="ui-input" placeholder="Contoh: Kalimantan Selatan" @disabled(!$canSubmit) aria-invalid="{{ $errors->has('province') ? 'true' : 'false' }}">
                        @error('province') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <fieldset class="rounded-xl border border-[var(--border)] p-4 sm:p-5">
                    <legend class="px-1 text-sm font-extrabold text-[var(--ink)]">Koordinat lokasi</legend>
                    <p class="mb-4 text-sm leading-6 text-[var(--ink-soft)]">Masukkan koordinat desimal yang ditunjukkan pada peta. Contoh format koordinat di Kalimantan Selatan: -3.4429, 114.8355.</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="business-latitude" class="ui-label">Lintang (latitude)</label>
                            <input id="business-latitude" type="number" step="any" min="-90" max="90" wire:model.blur="latitude" class="ui-input" placeholder="-3.4429" @disabled(!$canSubmit) aria-invalid="{{ $errors->has('latitude') ? 'true' : 'false' }}">
                            @error('latitude') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="business-longitude" class="ui-label">Bujur (longitude)</label>
                            <input id="business-longitude" type="number" step="any" min="-180" max="180" wire:model.blur="longitude" class="ui-input" placeholder="114.8355" @disabled(!$canSubmit) aria-invalid="{{ $errors->has('longitude') ? 'true' : 'false' }}">
                            @error('longitude') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <a href="https://www.openstreetmap.org/search?query={{ urlencode(trim($address . ', ' . $city . ', ' . $province)) }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex min-h-9 items-center gap-2 text-sm font-bold text-[var(--brand-forest)] underline-offset-4 hover:underline">
                        Buka peta untuk mencari koordinat
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M14 4h6v6M20 4l-9 9M18 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6"/></svg>
                    </a>
                </fieldset>

                @error('verification')
                    <div role="alert" class="status-note mt-4">{{ $message }}</div>
                @enderror

                @if ($canEdit)
                    <section aria-labelledby="verification-readiness-heading" class="rounded-xl bg-[var(--surface-soft)] p-4 sm:p-5">
                        <h3 id="verification-readiness-heading" class="text-sm font-extrabold text-[var(--ink)]">Persiapan pengajuan verifikasi</h3>
                        <p class="mt-1 text-sm leading-6 text-[var(--ink-soft)]">Lengkapi data dasar dan buat minimal satu venue Draft sebelum mengajukan usaha untuk ditinjau.</p>
                        <ul class="mt-4 space-y-2 text-sm">
                            <li class="flex items-start gap-2"><span aria-hidden="true" class="mt-px {{ trim($name) !== '' && trim($address) !== '' ? 'text-[var(--success-ink)]' : 'text-[var(--muted)]' }}">{{ trim($name) !== '' && trim($address) !== '' ? '✓' : '○' }}</span><span>Nama usaha dan alamat lengkap</span></li>
                            <li class="flex items-start gap-2"><span aria-hidden="true" class="mt-px {{ trim($city) !== '' && trim($province) !== '' ? 'text-[var(--success-ink)]' : 'text-[var(--muted)]' }}">{{ trim($city) !== '' && trim($province) !== '' ? '✓' : '○' }}</span><span>Kota / kabupaten dan provinsi</span></li>
                            <li class="flex items-start gap-2"><span aria-hidden="true" class="mt-px {{ is_numeric($latitude) && is_numeric($longitude) && (float) $latitude >= -90 && (float) $latitude <= 90 && (float) $longitude >= -180 && (float) $longitude <= 180 ? 'text-[var(--success-ink)]' : 'text-[var(--muted)]' }}">{{ is_numeric($latitude) && is_numeric($longitude) && (float) $latitude >= -90 && (float) $latitude <= 90 && (float) $longitude >= -180 && (float) $longitude <= 180 ? '✓' : '○' }}</span><span>Koordinat lokasi valid</span></li>
                            <li class="flex items-start gap-2"><span aria-hidden="true" class="mt-px {{ $hasDraftVenue ? 'text-[var(--success-ink)]' : 'text-[var(--muted)]' }}">{{ $hasDraftVenue ? '✓' : '○' }}</span><span>Minimal satu venue berstatus Draft</span></li>
                        </ul>
                        @if (!$hasDraftVenue)
                            <a href="{{ route('owner.businesses.venues.create', ['business' => $business->public_id]) }}" class="btn btn-secondary mt-4 w-full sm:w-auto">Tambah venue Draft</a>
                        @endif
                    </section>
                @endif

                @if ($canSubmit)
                    <div class="flex flex-col-reverse gap-2 border-t border-[var(--border)] pt-5 sm:flex-row sm:flex-wrap sm:justify-end">
                        <a href="{{ route('owner.businesses.index') }}" class="btn btn-secondary w-full sm:w-auto">Kembali</a>
                        <button type="submit" wire:loading.attr="disabled" class="btn btn-secondary w-full sm:w-auto">
                            <span wire:loading.remove wire:target="save">{{ $business->status === \App\Models\Business::STATUS_ACTIVE ? 'Ajukan revisi untuk ditinjau' : 'Simpan draft' }}</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                        @if ($this->canSubmitVerification())
                            <button type="button" wire:click="submitForVerification" wire:loading.attr="disabled" class="btn btn-primary w-full sm:w-auto">
                                <span wire:loading.remove wire:target="submitForVerification">Ajukan verifikasi</span>
                                <span wire:loading wire:target="submitForVerification">Mengirim pengajuan...</span>
                            </button>
                        @endif
                    </div>
                @endif

            </form>
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
                    <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Usaha telah terverifikasi, tetapi belum tampil ke publik karena persyaratan langganan belum dikelola oleh versi ini. Jangan membagikan profil sebagai tautan publik sebelum status publikasi aktif.</p>
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