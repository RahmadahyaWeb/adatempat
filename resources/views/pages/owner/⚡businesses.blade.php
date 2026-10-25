<?php

use App\Models\Business;
use Illuminate\Database\Eloquent\Collection;
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
        return auth()->user()
            ->businesses()
            ->withCount('venues')
            ->orderByDesc('updated_at')
            ->get();
    }

    public function startCreate(): void
    {
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

        abort_unless($user && $user->isOwner(), 403);

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
        $slugBase = Str::slug($businessName) ?: 'usaha';
        $slug = $slugBase;
        $suffix = 2;

        while (Business::query()->where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . $suffix;
            $suffix++;
        }

        $business = $user->businesses()->create([
            'name' => $businessName,
            'slug' => $slug,
            'address' => trim((string) ($validated['address'] ?? '')) ?: null,
            'status' => Business::STATUS_DRAFT,
        ]);

        session()->flash('status', 'Usaha berhasil dibuat sebagai draft. Lengkapi informasi dasarnya sebelum melanjutkan.');

        $this->redirectRoute(
            'owner.businesses.edit',
            ['business' => $business->getKey()],
            navigate: false,
        );
    }
};
?>

<div class="site-container py-7 sm:py-10 lg:py-12">
    @php
        $businesses = $this->businesses;
        $draftCount = $businesses->where('status', 'draft')->count();
        $activeCount = $businesses->where('status', 'active')->count();
        $venueCount = $businesses->sum('venues_count');
    @endphp

    <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="max-w-2xl">
            <a href="{{ route('home') }}" class="mb-4 inline-flex min-h-9 items-center gap-2 text-sm font-semibold text-[var(--ink-soft)] underline-offset-4 hover:text-[var(--brand-forest)] hover:underline">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m15 18-6-6 6-6"/></svg>
                Kembali ke Adatempat
            </a>
            <p class="eyebrow"><span class="eyebrow-dot"></span> Ruang pemilik usaha</p>
            <h1 class="mt-3 text-3xl font-extrabold leading-tight tracking-[-0.055em] sm:text-4xl lg:text-5xl">Kelola usaha</h1>
            <p class="mt-3 max-w-xl text-sm leading-7 text-[var(--ink-soft)] sm:text-base">
                Atur profil usaha yang kamu miliki. Usaha dan venue hanya tampil di katalog publik setelah memenuhi persyaratan publikasi.
            </p>
        </div>
        <button type="button" wire:click="startCreate" class="btn btn-primary w-full shrink-0 sm:w-auto">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" class="size-4"><path d="M12 5v14M5 12h14"/></svg>
            Tambah usaha
        </button>
    </header>

    @if (session('status'))
        <div role="status" class="status-note status-success mt-6">{{ session('status') }}</div>
    @endif

    <section aria-label="Ringkasan usaha" class="mt-7 grid grid-cols-2 gap-4 border-y border-[var(--border)] py-5 sm:grid-cols-3 sm:gap-7 sm:py-6">
        <div>
            <p class="text-xs font-semibold text-[var(--muted)] sm:text-sm">Total usaha</p>
            <p class="mt-1 text-2xl font-extrabold tracking-[-0.05em] sm:text-3xl">{{ $businesses->count() }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold text-[var(--muted)] sm:text-sm">Usaha aktif</p>
            <p class="mt-1 text-2xl font-extrabold tracking-[-0.05em] sm:text-3xl">{{ $activeCount }}</p>
        </div>
        <div class="col-span-2 sm:col-span-1">
            <p class="text-xs font-semibold text-[var(--muted)] sm:text-sm">Total venue</p>
            <p class="mt-1 text-2xl font-extrabold tracking-[-0.05em] sm:text-3xl">{{ $venueCount }}</p>
            @if ($draftCount > 0)
                <p class="mt-1 text-xs text-[var(--muted)]">{{ $draftCount }} usaha masih berstatus draft</p>
            @endif
        </div>
    </section>

    @if ($showCreateForm)
        <section aria-labelledby="create-business-heading" class="mt-7 rounded-2xl bg-[var(--surface-soft)] p-5 sm:p-7">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="eyebrow"><span class="eyebrow-dot"></span> Usaha baru</p>
                    <h2 id="create-business-heading" class="mt-2 text-xl font-extrabold tracking-[-0.04em] sm:text-2xl">Mulai dengan informasi dasar</h2>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-[var(--ink-soft)]">Nama dan alamat akan disimpan sebagai draft. Kamu bisa meninjaunya sebelum melanjutkan ke tahap berikutnya.</p>
                </div>
                <button type="button" wire:click="cancelCreate" aria-label="Tutup formulir" class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white text-[var(--ink-soft)] hover:text-[var(--ink)]">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="size-5"><path d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <form wire:submit="createBusiness" class="mt-6 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="business-name" class="ui-label">Nama usaha <span class="text-[var(--danger-ink)]">*</span></label>
                    <input id="business-name" type="text" wire:model="name" required maxlength="255" class="ui-input" placeholder="Nama usaha atau pengelola venue" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                    @error('name') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="business-address" class="ui-label">Alamat usaha</label>
                    <input id="business-address" type="text" wire:model="address" maxlength="2000" class="ui-input" placeholder="Jalan, kelurahan, kota">
                    @error('address') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col-reverse gap-2 sm:col-span-2 sm:flex-row sm:justify-end">
                    <button type="button" wire:click="cancelCreate" class="btn btn-secondary w-full sm:w-auto">Batal</button>
                    <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full sm:w-auto">
                        <span wire:loading.remove wire:target="createBusiness">Simpan sebagai draft</span>
                        <span wire:loading wire:target="createBusiness">Menyimpan...</span>
                    </button>
                </div>
            </form>
        </section>
    @endif

    <section aria-labelledby="business-list-heading" class="mt-8">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h2 id="business-list-heading" class="text-xl font-extrabold tracking-[-0.04em] sm:text-2xl">Daftar usaha</h2>
                <p class="mt-1 text-sm text-[var(--muted)]">Hanya data usaha milik akunmu yang ditampilkan.</p>
            </div>
            @if ($businesses->isNotEmpty())
                <span class="text-xs font-semibold text-[var(--muted)]">{{ $businesses->count() }} usaha</span>
            @endif
        </div>

        @if ($businesses->isEmpty())
            <div class="mt-5 rounded-2xl bg-white px-5 py-9 text-center sm:px-8 sm:py-12">
                <span class="mx-auto flex size-12 items-center justify-center rounded-xl bg-[var(--brand-lime)] text-[var(--ink)]">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M10 21v-3h4v3"/></svg>
                </span>
                <h3 class="mt-4 text-lg font-extrabold tracking-[-0.03em] sm:text-xl">Belum ada usaha yang terdaftar.</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[var(--muted)]">Tambahkan usaha untuk mulai menyimpan profil bisnis dan menyiapkan venue yang ingin ditampilkan di Adatempat.</p>
                <button type="button" wire:click="startCreate" class="btn btn-primary mt-5">Tambah usaha pertama</button>
            </div>
        @else
            <div class="mt-5 divide-y divide-[var(--border)] rounded-2xl bg-white px-4 sm:px-6">
                @foreach ($businesses as $business)
                    @php
                        $statusMeta = match ($business->status) {
                            'draft' => ['Draft', 'bg-[var(--surface-soft)] text-[var(--ink-soft)]'],
                            'pending_verification' => ['Menunggu verifikasi', 'bg-[var(--warning-bg)] text-[var(--warning-ink)]'],
                            'active' => ['Aktif', 'bg-[var(--success-bg)] text-[var(--success-ink)]'],
                            'suspended' => ['Ditangguhkan', 'bg-[var(--danger-bg)] text-[var(--danger-ink)]'],
                            'rejected' => ['Perlu perbaikan', 'bg-[var(--danger-bg)] text-[var(--danger-ink)]'],
                            default => [$business->status, 'bg-[var(--surface-soft)] text-[var(--ink-soft)]'],
                        };
                    @endphp
                    <article wire:key="business-{{ $business->id }}" class="flex flex-col gap-4 py-5 sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:py-6">
                        <div class="flex min-w-0 items-start gap-3 sm:gap-4">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[var(--surface-lime)] text-[var(--brand-forest)]">
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M10 21v-3h4v3"/></svg>
                            </span>
                            <div class="min-w-0">
                                <h3 class="break-words text-base font-extrabold tracking-[-0.03em] sm:text-lg">{{ $business->name }}</h3>
                                <p class="mt-1 break-words text-sm leading-5 text-[var(--muted)]">{{ $business->address ?: 'Alamat belum ditambahkan' }}</p>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-bold {{ $statusMeta[1] }}">{{ $statusMeta[0] }}</span>
                                    <span class="text-xs text-[var(--muted)]">{{ $business->venues_count }} venue</span>
                                    <span class="text-xs text-[var(--muted)]">Diperbarui {{ $business->updated_at?->format('d/m/Y') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex w-full shrink-0 flex-col gap-2 sm:w-auto sm:flex-row">
                            @if ($business->status !== \App\Models\Business::STATUS_SUSPENDED)
                                <a href="{{ route('owner.businesses.venues.index', ['business' => $business->id]) }}" class="btn btn-primary w-full sm:w-auto">
                                    Kelola venue
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m9 18 6-6-6-6"/></svg>
                                </a>
                            @endif
                            <a href="{{ route('owner.businesses.edit', ['business' => $business->id]) }}" class="btn btn-secondary w-full sm:w-auto">
                                Kelola data
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>