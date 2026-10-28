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

<div class="site-container owner-core-page py-7 sm:py-9 lg:py-10">
    @php
        $business = $this->currentBusiness;
        $canCreateVenue = Gate::allows('create', [Venue::class, $business]);
        $venues = $this->venues;
        $statusMeta = [
            'draft' => ['Draft', 'muted'],
            'pending_review' => ['Menunggu peninjauan', 'warning'],
            'published' => ['Dipublikasikan', 'success'],
            'rejected' => ['Perlu perbaikan', 'danger'],
            'archived' => ['Diarsipkan', 'muted'],
        ];
    @endphp

    <header class="owner-core-header">
        <div>
            <a href="{{ route('owner.businesses.edit', ['business' => $business->public_id]) }}" class="owner-core-back">
                <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m12 5-5 5 5 5"/></svg>
                Profil usaha
            </a>
            <p class="owner-core-kicker">Venue · {{ $business->name }}</p>
            <h1>Kelola venue</h1>
            <p>Atur venue, status publikasi, dan promosi dari satu daftar yang mudah dipindai.</p>
        </div>
        @if ($canCreateVenue)
        <a href="{{ route('owner.businesses.venues.create', ['business' => $business->public_id]) }}" class="btn btn-primary owner-core-primary owner-core-header-action">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Tambah venue
        </a>
        @else
        <a href="{{ route('owner.subscription') }}" class="btn btn-secondary owner-core-header-action">Lihat kuota paket</a>
        @endif
    </header>

    @if (session('status'))
        <div role="status" class="status-note status-success mt-5">{{ session('status') }}</div>
    @endif

    @if ($business->status !== 'active')
        <div class="owner-core-alert mt-5">
            @if ($business->status === 'draft')
                <strong>Usaha masih draft.</strong><span>Venue dapat disiapkan, tetapi belum tampil ke publik.</span>
            @elseif ($business->status === 'pending_verification')
                <strong>Usaha sedang diverifikasi.</strong><span>Perubahan venue tidak mengubah status verifikasi usaha.</span>
            @elseif ($business->status === 'rejected')
                <strong>Usaha perlu diperbaiki.</strong><span>Venue tetap dapat disiapkan sebelum profil dipublikasikan.</span>
            @else
                <strong>Status usaha: {{ $business->status }}.</strong>
            @endif
        </div>
    @endif

    <section class="owner-core-metrics is-four" aria-label="Ringkasan venue">
        <div><span>Semua venue</span><strong>{{ $business->venues_count }}</strong></div>
        <div><span>Draft</span><strong>{{ $business->draft_venues_count }}</strong></div>
        <div><span>Menunggu tinjauan</span><strong>{{ $business->pending_venues_count }}</strong></div>
        <div><span>Dipublikasikan</span><strong>{{ $business->published_venues_count }}</strong></div>
    </section>

    <section class="owner-core-section" aria-labelledby="venue-list-heading">
        <div class="owner-core-section-head owner-venue-list-head">
            <div>
                <h2 id="venue-list-heading">Daftar venue</h2>
                <p>Cari, filter, dan buka venue yang ingin dikelola.</p>
            </div>

            <div class="owner-venue-filters">
                <div>
                    <label for="venue-search" class="sr-only">Cari venue</label>
                    <input id="venue-search" type="search" wire:model.live.debounce.300ms="search" class="ui-input" placeholder="Cari venue…">
                </div>
                <div class="ui-select-wrap">
                    <label for="venue-status" class="sr-only">Filter status</label>
                    <select id="venue-status" wire:model.live="statusFilter" class="ui-input ui-select">
                        <option value="all">Semua status</option>
                        <option value="draft">Draft</option>
                        <option value="pending_review">Menunggu peninjauan</option>
                        <option value="published">Dipublikasikan</option>
                        <option value="rejected">Perlu perbaikan</option>
                        <option value="archived">Diarsipkan</option>
                    </select>
                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="ui-select-chevron"><path d="m6 8 4 4 4-4"/></svg>
                </div>
            </div>
        </div>

        @if ($venues->isEmpty())
            <div class="owner-core-empty">
                <span>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M10 21v-3h4v3"/></svg>
                </span>
                <h3>{{ $this->currentBusiness->venues_count === 0 ? 'Belum ada venue.' : 'Tidak ada venue yang cocok.' }}</h3>
                <p>{{ $this->currentBusiness->venues_count === 0 ? 'Tambahkan venue pertama untuk mulai menyiapkan katalog tempat.' : 'Coba kata kunci atau filter status lain.' }}</p>
                @if ($this->currentBusiness->venues_count === 0)
                    @if ($canCreateVenue)
                        <a href="{{ route('owner.businesses.venues.create', ['business' => $business->public_id]) }}" class="btn btn-primary">Tambah venue pertama</a>
                    @else
                        <a href="{{ route('owner.subscription') }}" class="btn btn-primary">Lihat paket langganan</a>
                    @endif
                @endif
            </div>
        @else
            <div class="owner-venue-grid">
                @foreach ($venues as $venue)
                    @php($meta = $statusMeta[$venue->status] ?? [$venue->status, 'muted'])
                    <article wire:key="venue-{{ $venue->id }}" class="owner-venue-card">
                        <div class="owner-venue-card-head">
                            <div>
                                <span class="owner-core-status is-{{ $meta[1] }}">{{ $meta[0] }}</span>
                                <h3>{{ $venue->name }}</h3>
                                <p>{{ $venue->category?->name ?? 'Kategori tidak tersedia' }}</p>
                            </div>
                            <span class="owner-venue-updated">{{ $venue->updated_at?->format('d M Y') }}</span>
                        </div>

                        <p class="owner-venue-description">
                            {{ $venue->description ? \Illuminate\Support\Str::limit($venue->description, 125) : 'Belum ada deskripsi venue.' }}
                        </p>

                        <div class="owner-venue-card-actions">
                            @if ($venue->status !== \App\Models\Venue::STATUS_ARCHIVED)
                                <a href="{{ route('owner.businesses.venues.edit', ['business' => $business->public_id, 'venue' => $venue->public_id]) }}" class="btn btn-primary">
                                    Edit venue
                                </a>
                                <a href="{{ route('owner.businesses.venues.promotions', ['business' => $business->public_id, 'venue' => $venue->public_id]) }}" class="btn btn-secondary">
                                    Promosi
                                </a>
                            @else
                                <span class="owner-venue-archived">Venue diarsipkan</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
