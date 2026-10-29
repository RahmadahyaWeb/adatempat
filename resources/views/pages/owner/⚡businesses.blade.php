<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kelola usaha')] class extends Component {
    public bool $showCreateForm = false;

    public string $name = '';

    public string $address = '';

    #[Computed]
    public function businesses(): Collection
    {
        Gate::authorize('viewAny', Business::class);

        return auth()->user()
            ->businesses()
            ->withCount('venues')
            ->orderByDesc('updated_at')
            ->get();
    }

    public function startCreate(): void
    {
        Gate::authorize('create', Business::class);
        $this->resetValidation();
        $this->showCreateForm = true;
    }

    public function cancelCreate(): void
    {
        $this->reset(['showCreateForm', 'name', 'address']);
        $this->resetValidation();
    }

    public function createBusiness(): void
    {
        $user = auth()->user();

        Gate::authorize('create', Business::class);

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
        $business = DB::transaction(function () use ($user, $businessName, $validated): Business {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            Gate::authorize('create', Business::class);

            $slugBase = Str::slug($businessName) ?: 'usaha';
            $slug = $slugBase;
            $suffix = 2;
            while (Business::query()->where('slug', $slug)->exists()) {
                $slug = $slugBase . '-' . $suffix++;
            }

            return $user->businesses()->create([
                'name' => $businessName,
                'slug' => $slug,
                'address' => trim((string) ($validated['address'] ?? '')) ?: null,
                'status' => Business::STATUS_DRAFT,
            ]);
        }, attempts: 3);

        session()->flash('status', 'Usaha berhasil dibuat sebagai draft. Lengkapi informasi dasarnya sebelum melanjutkan.');

        $this->redirectRoute(
            'owner.businesses.edit',
            ['business' => $business->public_id],
            navigate: false,
        );
    }
};
?>

<div class="site-container owner-core-page py-7 sm:py-9 lg:py-10">
    @php
        $businesses = $this->businesses;
        $draftCount = $businesses->where('status', 'draft')->count();
        $activeCount = $businesses->where('status', 'active')->count();
        $venueCount = $businesses->sum('venues_count');
    @endphp
    @php $canCreateBusiness = Gate::allows('create', Business::class); @endphp

    <header class="owner-core-header">
        <div>
            <p class="owner-core-kicker">Portofolio usaha</p>
            <h1>Kelola usaha</h1>
            <p>Atur profil usaha dan venue yang kamu miliki. Konten publik hanya tampil setelah memenuhi persyaratan publikasi.</p>
        </div>
        @if ($canCreateBusiness)
        <button type="button" wire:click="startCreate" class="btn btn-primary owner-core-primary">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" class="size-4"><path d="M12 5v14M5 12h14"/></svg>
            Tambah usaha
        </button>
        @else
        <a href="{{ route('owner.subscription') }}" class="btn btn-primary owner-core-primary">Lihat paket & kuota</a>
        @endif
    </header>

    @if (session('status'))
        <div role="status" class="status-note status-success mt-5">{{ session('status') }}</div>
    @endif

    <section class="owner-core-metrics" aria-label="Ringkasan usaha">
        <div><span>Total usaha</span><strong>{{ $businesses->count() }}</strong></div>
        <div><span>Usaha aktif</span><strong>{{ $activeCount }}</strong></div>
        <div>
            <span>Total venue</span>
            <strong>{{ $venueCount }}</strong>
            @if ($draftCount > 0)<small>{{ $draftCount }} usaha masih draft</small>@endif
        </div>
    </section>

    @if ($showCreateForm)
        <section class="owner-core-create-panel" aria-labelledby="create-business-heading">
            <div class="owner-core-panel-head">
                <div>
                    <p class="owner-core-kicker">Usaha baru</p>
                    <h2 id="create-business-heading">Mulai dari informasi dasar</h2>
                    <p>Data ini disimpan sebagai draft dan dapat dilengkapi sebelum verifikasi.</p>
                </div>
                <button type="button" wire:click="cancelCreate" aria-label="Tutup formulir" class="venue-v2-modal-close">
                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M5 5l10 10M15 5 5 15"/></svg>
                </button>
            </div>

            <form wire:submit="createBusiness" class="owner-core-form-grid">
                <div>
                    <label for="business-name" class="ui-label">Nama usaha</label>
                    <input id="business-name" type="text" wire:model="name" required maxlength="255" class="ui-input" placeholder="Nama usaha atau pengelola venue">
                    @error('name') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="business-address" class="ui-label">Alamat usaha</label>
                    <input id="business-address" type="text" wire:model="address" maxlength="2000" class="ui-input" placeholder="Jalan, kelurahan, kota">
                    @error('address') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div class="owner-core-form-actions">
                    <button type="button" wire:click="cancelCreate" class="btn btn-secondary">Batal</button>
                    <button type="submit" wire:loading.attr="disabled" class="btn btn-primary">
                        <span wire:loading.remove wire:target="createBusiness">Simpan draft</span>
                        <span wire:loading wire:target="createBusiness">Menyimpan...</span>
                    </button>
                </div>
            </form>
        </section>
    @endif

    <section class="owner-core-section" aria-labelledby="business-list-heading">
        <div class="owner-core-section-head">
            <div>
                <h2 id="business-list-heading">Daftar usaha</h2>
                <p>Kelola setiap usaha dan venue di dalamnya.</p>
            </div>
            @if ($businesses->isNotEmpty())<span>{{ $businesses->count() }} usaha</span>@endif
        </div>

        @if ($businesses->isEmpty())
            <div class="owner-core-empty">
                <span>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M10 21v-3h4v3"/></svg>
                </span>
                <h3>Belum ada usaha.</h3>
                <p>Tambahkan usaha untuk mulai menyiapkan profil dan venue.</p>
                @if ($canCreateBusiness)
                    <button type="button" wire:click="startCreate" class="btn btn-primary">Tambah usaha pertama</button>
                @else
                    <a href="{{ route('owner.subscription') }}" class="btn btn-primary">Pilih paket langganan</a>
                @endif
            </div>
        @else
            <div class="owner-business-list">
                @foreach ($businesses as $business)
                    @php
                        $statusMeta = match ($business->status) {
                            'draft' => ['Draft', 'muted'],
                            'pending_verification' => ['Menunggu verifikasi', 'warning'],
                            'active' => ['Aktif', 'success'],
                            'suspended' => ['Ditangguhkan', 'danger'],
                            'rejected' => ['Perlu perbaikan', 'danger'],
                            default => [$business->status, 'muted'],
                        };
                    @endphp
                    <article wire:key="business-{{ $business->id }}" class="owner-business-row">
                        <div class="owner-business-row-icon">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M10 21v-3h4v3"/></svg>
                        </div>
                        <div class="owner-business-row-main">
                            <div class="owner-business-row-title">
                                <div>
                                    <h3>{{ $business->name }}</h3>
                                    <p>{{ $business->address ?: 'Alamat belum ditambahkan' }}</p>
                                </div>
                            </div>
                            <div class="owner-business-row-meta">
                                <span>{{ $business->venues_count }} venue</span>
                                <span>Diperbarui {{ $business->updated_at?->format('d M Y') }}</span>
                            </div>
                        </div>
                        <div class="owner-business-row-status">
                            <span class="owner-core-status is-{{ $statusMeta[1] }}">{{ $statusMeta[0] }}</span>
                        </div>
                        <div class="owner-business-row-actions">
                            @if ($business->status !== \App\Models\Business::STATUS_SUSPENDED)
                                <a href="{{ route('owner.businesses.venues.index', ['business' => $business->public_id]) }}" class="btn btn-primary">Kelola venue</a>
                            @endif
                            <a href="{{ route('owner.businesses.edit', ['business' => $business->public_id]) }}" class="btn btn-secondary">Profil usaha</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
