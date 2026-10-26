<?php

use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Sengketa booking')] class extends Component {
    public string $search = '';

    public string $statusFilter = '';

    #[Computed]
    public function disputes()
    {
        $this->requireSuperAdmin();

        return Dispute::query()
            ->with([
                'openedBy:id,name,email,role',
                'booking:id,reference_code,customer_id,venue_id,booking_start,booking_end,status,snapshot_actual_paid_amount,snapshot_net_owner_entitlement',
                'booking.customer:id,name,email',
                'booking.venue:id,name,business_id',
                'booking.venue.business:id,name,owner_id',
                'booking.venue.business.owner:id,name,email',
            ])
            ->when(trim($this->search) !== '', function ($query): void {
                $term = trim($this->search);

                $query->where(function ($nested) use ($term): void {
                    $nested->where('reference_code', 'like', '%' . $term . '%')
                        ->orWhere('category', 'like', '%' . $term . '%')
                        ->orWhereHas('booking', fn ($booking) => $booking->where('reference_code', 'like', '%' . $term . '%'))
                        ->orWhereHas('openedBy', fn ($user) => $user->where('name', 'like', '%' . $term . '%')->orWhere('email', 'like', '%' . $term . '%'));
                });
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'under_review' THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->limit(100)
            ->get();
    }

    private function requireSuperAdmin(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User && $user->isSuperAdmin() && $user->status === 'active', 403);

        return $user;
    }

    public function startReview(int $disputeId): void
    {
        $admin = $this->requireSuperAdmin();

        DB::transaction(function () use ($admin, $disputeId): void {
            $dispute = Dispute::query()->whereKey($disputeId)->lockForUpdate()->firstOrFail();

            if ($dispute->status !== Dispute::STATUS_OPEN) {
                return;
            }

            $dispute->update(['status' => Dispute::STATUS_UNDER_REVIEW]);

            AuditLog::query()->create([
                'actor_user_id' => $admin->getKey(),
                'actor_role' => $admin->role,
                'action' => 'booking.dispute_review_started',
                'auditable_type' => Dispute::class,
                'auditable_id' => $dispute->getKey(),
                'reason' => 'Super Admin started reviewing the dispute.',
                'before_state_json' => ['status' => Dispute::STATUS_OPEN],
                'after_state_json' => ['status' => Dispute::STATUS_UNDER_REVIEW],
                'ip_hash' => request()->ip() ? hash('sha256', (string) request()->ip()) : null,
                'created_at' => now('UTC'),
            ]);
        }, attempts: 3);

        unset($this->disputes);
    }
};
?>

<div class="site-container space-y-6 py-6 sm:py-10">
    <header>
        <p class="eyebrow"><span class="eyebrow-dot"></span> Operasional / Penanganan sengketa</p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-[-0.05em] sm:text-4xl">Sengketa booking</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-[var(--ink-soft)]">Sengketa menghentikan auto-completion dan menahan payout. Tinjau laporan serta riwayat transaksi sebelum mengambil keputusan akhir.</p>
    </header>

    <section class="ui-panel p-4 sm:p-6">
        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_220px]">
            <div>
                <label class="ui-label" for="dispute-search">Cari sengketa</label>
                <input id="dispute-search" type="search" wire:model.live.debounce.300ms="search" class="ui-input w-full" placeholder="Kode sengketa, booking, kategori, pelapor">
            </div>
            <div>
                <label class="ui-label" for="dispute-status">Status</label>
                <select id="dispute-status" wire:model.live="statusFilter" class="ui-input w-full">
                    <option value="">Semua status</option>
                    <option value="open">Baru</option>
                    <option value="under_review">Sedang ditinjau</option>
                    <option value="resolved_to_customer">Selesai untuk pelanggan</option>
                    <option value="resolved_to_owner">Selesai untuk Owner</option>
                    <option value="resolved_split">Selesai sebagian</option>
                </select>
            </div>
        </div>

        <div class="mt-5 space-y-3">
            @forelse ($this->disputes as $dispute)
                <article wire:key="dispute-{{ $dispute->id }}" class="rounded-xl border border-[var(--border)] p-4 sm:p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-bold">{{ $dispute->reference_code }}</p>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $dispute->status === 'open' ? 'bg-amber-100 text-amber-900' : (str_starts_with($dispute->status, 'resolved_') ? 'bg-emerald-100 text-emerald-800' : 'bg-sky-100 text-sky-800') }}">{{ str($dispute->status)->replace('_', ' ')->title() }}</span>
                            </div>
                            <p class="mt-2 text-sm font-semibold">{{ $dispute->category }}</p>
                            <p class="mt-1 whitespace-pre-line text-sm leading-6 text-[var(--ink-soft)]">{{ $dispute->description }}</p>
                            <div class="mt-3 rounded-lg bg-[var(--surface-soft)] p-3 text-sm">
                                <p><span class="font-semibold">Booking:</span> {{ $dispute->booking?->reference_code }} · {{ $dispute->booking?->venue?->name }}</p>
                                <p class="mt-1"><span class="font-semibold">Usaha:</span> {{ $dispute->booking?->venue?->business?->name }}</p>
                                <p class="mt-1"><span class="font-semibold">Pelanggan:</span> {{ $dispute->booking?->customer?->name }} · {{ $dispute->booking?->customer?->email }}</p>
                                <p class="mt-1"><span class="font-semibold">Owner:</span> {{ $dispute->booking?->venue?->business?->owner?->name }} · {{ $dispute->booking?->venue?->business?->owner?->email }}</p>
                                <p class="mt-1"><span class="font-semibold">Dilaporkan oleh:</span> {{ $dispute->openedBy?->name }} ({{ $dispute->opened_by_role }})</p>
                                <p class="mt-1"><span class="font-semibold">Batas tanggapan Owner:</span> {{ $dispute->owner_response_deadline_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>
                            </div>
                        </div>
                        <div class="shrink-0 lg:text-right">
                            <p class="text-xs text-[var(--ink-soft)]">Pembayaran pelanggan</p>
                            <p class="mt-1 text-lg font-extrabold">Rp{{ number_format($dispute->booking?->snapshot_actual_paid_amount ?? 0, 0, ',', '.') }}</p>
                            <p class="mt-2 text-xs text-[var(--ink-soft)]">Hak normal Owner</p>
                            <p class="font-bold">Rp{{ number_format($dispute->booking?->snapshot_net_owner_entitlement ?? 0, 0, ',', '.') }}</p>
                            @if ($dispute->status === 'open')
                                <button type="button" wire:click="startReview({{ $dispute->id }})" class="btn btn-primary mt-3 w-full sm:w-auto">Mulai peninjauan</button>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-[var(--border-strong)] p-8 text-center text-sm text-[var(--ink-soft)]">Tidak ada sengketa yang sesuai dengan filter.</div>
            @endforelse
        </div>
        <p class="mt-4 text-xs leading-5 text-[var(--ink-soft)]">Daftar dibatasi 100 sengketa. Halaman ini mendukung triase awal; keputusan finansial final akan tersedia melalui alur resolusi terpisah.</p>
    </section>
</div>
