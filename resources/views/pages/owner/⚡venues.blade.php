<?php

use App\Models\Business;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kelola venue')] class extends Component {
    public string $businessPublicId;

    public string $search = '';

    public string $statusFilter = 'all';

    public function mount(string $business): void
    {
        $record = auth()->user()
            ->businesses()
            ->where('public_id', $business)
            ->firstOrFail();

        Gate::authorize('viewAny', [Venue::class, $record]);

        abort_unless($record->status !== Business::STATUS_SUSPENDED, 403);

        $this->businessPublicId = (string) $record->public_id;
    }

    #[Computed]
    public function currentBusiness(): Business
    {
        return auth()->user()
            ->businesses()
            ->withCount([
                'venues',
                'venues as draft_venues_count' => fn (Builder $query) => $query->where('status', Venue::STATUS_DRAFT),
                'venues as published_venues_count' => fn (Builder $query) => $query->where('status', Venue::STATUS_PUBLISHED),
                'venues as pending_venues_count' => fn (Builder $query) => $query->where('status', Venue::STATUS_PENDING_REVIEW),
            ])
            ->where('public_id', $this->businessPublicId)
            ->firstOrFail();
    }

    #[Computed]
    public function venues(): Collection
    {
        $allowedStatuses = [
            'all',
            Venue::STATUS_DRAFT,
            Venue::STATUS_PENDING_REVIEW,
            Venue::STATUS_PUBLISHED,
            Venue::STATUS_REJECTED,
            Venue::STATUS_ARCHIVED,
        ];

        $status = in_array($this->statusFilter, $allowedStatuses, true)
            ? $this->statusFilter
            : 'all';

        return $this->currentBusiness
            ->venues()
            ->with('category')
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $keyword = trim($this->search);

                $query->where(function (Builder $nested) use ($keyword): void {
                    $nested
                        ->where('name', 'like', '%' . $keyword . '%')
                        ->orWhere('description', 'like', '%' . $keyword . '%');
                });
            })
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->orderByDesc('updated_at')
            ->get();
    }

    public function updatedStatusFilter(): void
    {
        if (! in_array($this->statusFilter, [
            'all',
            Venue::STATUS_DRAFT,
            Venue::STATUS_PENDING_REVIEW,
            Venue::STATUS_PUBLISHED,
            Venue::STATUS_REJECTED,
            Venue::STATUS_ARCHIVED,
        ], true)) {
            $this->statusFilter = 'all';
        }
    }
};
?>

<div class="site-container py-7 sm:py-10 lg:py-12">
    @php
        $business = $this->currentBusiness;
        $venues = $this->venues;
        $statusMeta = [
            'draft' => ['Draft', 'bg-[var(--surface-soft)] text-[var(--ink-soft)]'],
            'pending_review' => ['Menunggu peninjauan', 'bg-[var(--warning-bg)] text-[var(--warning-ink)]'],
            'published' => ['Dipublikasikan', 'bg-[var(--success-bg)] text-[var(--success-ink)]'],
            'rejected' => ['Perlu perbaikan', 'bg-[var(--danger-bg)] text-[var(--danger-ink)]'],
            'archived' => ['Diarsipkan', 'bg-[var(--surface-soft)] text-[var(--muted)]'],
        ];
    @endphp

    <a href="{{ route('owner.businesses.edit', ['business' => $business->public_id]) }}" class="inline-flex min-h-9 items-center gap-2 text-sm font-semibold text-[var(--ink-soft)] underline-offset-4 hover:text-[var(--brand-forest)] hover:underline">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m15 18-6-6 6-6"/></svg>
        Kembali ke profil usaha
    </a>

    <header class="mt-5 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0 max-w-2xl">
            <p class="eyebrow"><span class="eyebrow-dot"></span> Katalog usaha</p>
            <h1 class="mt-3 break-words text-3xl font-extrabold leading-tight tracking-[-0.055em] sm:text-4xl lg:text-5xl">Kelola venue</h1>
            <p class="mt-2 break-words text-sm font-semibold text-[var(--ink-soft)] sm:text-base">{{ $business->name }}</p>
            <p class="mt-3 text-sm leading-6 text-[var(--muted)]">Atur nama, kategori, dan deskripsi setiap tempat di bawah usaha ini.</p>
        </div>
        <a href="{{ route('owner.businesses.venues.create', ['business' => $business->public_id]) }}" class="btn btn-primary w-full shrink-0 sm:w-auto">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" class="size-4"><path d="M12 5v14M5 12h14"/></svg>
            Tambah venue
        </a>
    </header>

    @if (session('status'))
        <div role="status" class="status-note status-success mt-6">{{ session('status') }}</div>
    @endif

    @if ($business->status !== 'active')
        <div class="status-note mt-6">
            @if ($business->status === 'draft')
                Usaha ini masih berstatus draft. Data venue dapat disiapkan, tetapi profil usaha belum tampil ke publik.
            @elseif ($business->status === 'pending_verification')
                Usaha sedang menunggu verifikasi. Penambahan atau pembaruan informasi venue tidak mengubah status verifikasi usaha.
            @elseif ($business->status === 'rejected')
                Usaha memerlukan perbaikan. Kamu dapat menyiapkan data venue, tetapi profil tidak akan dipublikasikan sampai status usaha memenuhi persyaratan.
            @else
                Status usaha saat ini: {{ $business->status }}.
            @endif
        </div>
    @endif

    <section aria-label="Ringkasan venue" class="mt-7 grid grid-cols-2 gap-x-4 gap-y-5 border-y border-[var(--border)] py-5 sm:grid-cols-4 sm:gap-7 sm:py-6">
        <div>
            <p class="text-xs font-semibold text-[var(--muted)] sm:text-sm">Semua venue</p>
            <p class="mt-1 text-2xl font-extrabold tracking-[-0.05em] tabular-nums sm:text-3xl">{{ $business->venues_count }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold text-[var(--muted)] sm:text-sm">Draft</p>
            <p class="mt-1 text-2xl font-extrabold tracking-[-0.05em] tabular-nums sm:text-3xl">{{ $business->draft_venues_count }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold text-[var(--muted)] sm:text-sm">Menunggu tinjauan</p>
            <p class="mt-1 text-2xl font-extrabold tracking-[-0.05em] tabular-nums sm:text-3xl">{{ $business->pending_venues_count }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold text-[var(--muted)] sm:text-sm">Dipublikasikan</p>
            <p class="mt-1 text-2xl font-extrabold tracking-[-0.05em] tabular-nums sm:text-3xl">{{ $business->published_venues_count }}</p>
        </div>
    </section>

    <section aria-labelledby="venue-list-heading" class="mt-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 id="venue-list-heading" class="text-xl font-extrabold tracking-[-0.04em] sm:text-2xl">Daftar venue</h2>
                <p class="mt-1 text-sm text-[var(--muted)]">Perubahan hanya berlaku untuk venue milik usaha ini.</p>
            </div>
            <div class="grid gap-3 sm:w-[26rem] sm:grid-cols-[minmax(0,1fr)_12rem]">
                <div>
                    <label for="venue-search" class="sr-only">Cari venue</label>
                    <input id="venue-search" type="search" wire:model.live.debounce.300ms="search" class="ui-input" placeholder="Cari nama atau deskripsi…">
                </div>
                <div>
                    <label for="venue-status" class="sr-only">Filter status</label>
                    <select id="venue-status" wire:model.live="statusFilter" class="ui-input">
                        <option value="all">Semua status</option>
                        <option value="draft">Draft</option>
                        <option value="pending_review">Menunggu peninjauan</option>
                        <option value="published">Dipublikasikan</option>
                        <option value="rejected">Perlu perbaikan</option>
                        <option value="archived">Diarsipkan</option>
                    </select>
                </div>
            </div>
        </div>

        @if ($venues->isEmpty())
            <div class="mt-5 rounded-2xl bg-white px-5 py-9 text-center sm:px-8 sm:py-12">
                <span class="mx-auto flex size-12 items-center justify-center rounded-xl bg-[var(--brand-lime)] text-[var(--ink)]">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M10 21v-3h4v3"/></svg>
                </span>
                <h3 class="mt-4 text-lg font-extrabold tracking-[-0.035em] sm:text-xl">
                    {{ $this->currentBusiness->venues_count === 0 ? 'Belum ada venue untuk usaha ini.' : 'Tidak ada venue yang cocok.' }}
                </h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[var(--muted)]">
                    {{ $this->currentBusiness->venues_count === 0 ? 'Tambahkan venue untuk mulai menyiapkan katalog tempat.' : 'Coba kata kunci atau status lain, atau buat venue baru.' }}
                </p>
                @if ($this->currentBusiness->venues_count === 0)
                    <a href="{{ route('owner.businesses.venues.create', ['business' => $business->public_id]) }}" class="btn btn-primary mt-5">Tambah venue pertama</a>
                @endif
            </div>
        @else
            <div class="mt-5 divide-y divide-[var(--border)] rounded-2xl bg-white px-4 sm:px-6">
                @foreach ($venues as $venue)
                    @php
                        $meta = $statusMeta[$venue->status] ?? [$venue->status, 'bg-[var(--surface-soft)] text-[var(--ink-soft)]'];
                    @endphp
                    <article wire:key="venue-{{ $venue->id }}" class="flex flex-col gap-4 py-5 sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:py-6">
                        <div class="flex min-w-0 items-start gap-3 sm:gap-4">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[var(--surface-lime)] text-[var(--brand-forest)]">
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M10 21v-3h4v3"/></svg>
                            </span>
                            <div class="min-w-0">
                                <h3 class="break-words text-base font-extrabold tracking-[-0.03em] sm:text-lg">{{ $venue->name }}</h3>
                                <p class="mt-1 text-sm text-[var(--ink-soft)]">{{ $venue->category?->name ?? 'Kategori tidak tersedia' }}</p>
                                @if ($venue->description)
                                    <p class="mt-2 max-w-2xl text-sm leading-6 text-[var(--muted)]">{{ \Illuminate\Support\Str::limit($venue->description, 150) }}</p>
                                @else
                                    <p class="mt-2 text-sm text-[var(--muted)]">Belum ada deskripsi.</p>
                                @endif
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-bold {{ $meta[1] }}">{{ $meta[0] }}</span>
                                    <span class="text-xs text-[var(--muted)]">Diperbarui {{ $venue->updated_at?->format('d/m/Y') }}</span>
                                </div>
                            </div>
                        </div>
                        @if ($venue->status !== \App\Models\Venue::STATUS_ARCHIVED)
                            <a href="{{ route('owner.businesses.venues.edit', ['business' => $business->public_id, 'venue' => $venue->public_id]) }}" class="btn btn-secondary w-full shrink-0 sm:w-auto">
                                Edit venue
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m9 18 6-6-6-6"/></svg>
                            </a>
                            <a href="{{ route('owner.businesses.venues.promotions', ['business' => $business->public_id, 'venue' => $venue->public_id]) }}" class="btn btn-secondary w-full shrink-0 sm:w-auto">
                                Kelola promosi
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m9 18 6-6-6-6"/></svg>
                            </a>
                        @else
                            <span class="text-xs font-semibold text-[var(--muted)]">Venue diarsipkan</span>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>