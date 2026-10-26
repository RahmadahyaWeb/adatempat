<?php

use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\User;
use App\Services\Booking\DisputeResolutionService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Sengketa booking')] class extends Component {
    public string $search = '';

    public string $statusFilter = '';

    public string $selectedDisputeId = '';

    public string $decision = 'resolved_to_owner';

    public string $refundAmount = '0';

    public string $payoutAmount = '0';

    public string $resolutionNotes = '';

    #[Computed]
    public function disputes()
    {
        $this->requireSuperAdmin();

        return Dispute::query()
            ->with([
                'openedBy:id,name,email,role',
                'auditLogs',
                'messages.sender:id,name',
                'evidence',
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

    public function startResolution(int $disputeId): void
    {
        $this->requireSuperAdmin();
        $dispute = Dispute::query()->with('booking')->whereKey($disputeId)->firstOrFail();

        abort_unless(in_array($dispute->status, Dispute::OPEN_STATUSES, true), 403);

        $this->selectedDisputeId = (string) $dispute->getKey();
        $this->decision = Dispute::STATUS_RESOLVED_TO_OWNER;
        $this->refundAmount = (string) ((int) $dispute->booking->snapshot_actual_paid_amount);
        $this->payoutAmount = (string) ((int) $dispute->booking->snapshot_net_owner_entitlement);
        $this->resolutionNotes = '';
        $this->resetErrorBag();
    }

    public function cancelResolution(): void
    {
        $this->requireSuperAdmin();
        $this->selectedDisputeId = '';
        $this->resolutionNotes = '';
        $this->resetErrorBag();
    }

    public function resolveDispute(DisputeResolutionService $resolution): void
    {
        $admin = $this->requireSuperAdmin();

        $data = $this->validate([
            'selectedDisputeId' => ['required', 'integer', 'exists:disputes,id'],
            'decision' => ['required', 'in:resolved_to_customer,resolved_to_owner,resolved_split'],
            'refundAmount' => ['required', 'integer', 'min:0'],
            'payoutAmount' => ['required', 'integer', 'min:0'],
            'resolutionNotes' => ['required', 'string', 'min:20', 'max:5000'],
        ], [
            'decision.required' => 'Pilih keputusan akhir.',
            'refundAmount.integer' => 'Nominal refund harus berupa bilangan rupiah.',
            'payoutAmount.integer' => 'Nominal payout harus berupa bilangan rupiah.',
            'resolutionNotes.required' => 'Alasan keputusan wajib diisi.',
            'resolutionNotes.min' => 'Alasan keputusan minimal 20 karakter.',
            'resolutionNotes.max' => 'Alasan keputusan maksimal 5.000 karakter.',
        ]);

        $dispute = Dispute::query()->findOrFail((int) $data['selectedDisputeId']);
        $result = $resolution->resolve(
            $admin,
            $dispute,
            $data['decision'],
            (int) $data['refundAmount'],
            (int) $data['payoutAmount'],
            trim($data['resolutionNotes']),
        );

        $this->selectedDisputeId = '';
        $this->resolutionNotes = '';
        unset($this->disputes);
        session()->flash('status', 'Sengketa ' . $result['dispute']->reference_code . ' diselesaikan. Status refund: ' . ($result['refund']?->status ?? 'tidak diperlukan') . '; payout: ' . $result['payout']->status . '.');
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

    @if (session()->has('status'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @error('resolution') <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $message }}</div> @enderror

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
                            @foreach ($dispute->auditLogs->where('action', 'booking.dispute_owner_response_submitted') as $responseLog)
                                <div class="mt-3 rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm">
                                    <p class="font-semibold text-sky-950">Tanggapan Owner · {{ $responseLog->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>
                                    <p class="mt-1 whitespace-pre-line leading-6 text-sky-950">{{ $responseLog->reason }}</p>
                                </div>
                            @endforeach
                            @if ($dispute->messages->isNotEmpty())
                                <div class="mt-3 space-y-2">
                                    <p class="text-sm font-bold">Tanggapan tambahan</p>
                                    @foreach ($dispute->messages as $entry)
                                        <div class="rounded-lg border border-[var(--border)] p-3 text-sm">
                                            <p class="font-semibold">{{ $entry->sender?->name }} · {{ $entry->created_at?->format('d/m/Y H:i') }} UTC</p>
                                            <p class="mt-1 whitespace-pre-line">{{ $entry->message }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if ($dispute->evidence->isNotEmpty())
                                <div class="mt-3 space-y-2">
                                    <p class="text-sm font-bold">Bukti privat (memerlukan MFA Super Admin)</p>
                                    @foreach ($dispute->evidence as $entry)
                                        <a href="{{ route('disputes.evidence.download', $entry) }}" class="block text-sm font-semibold underline">{{ $entry->original_name }}</a>
                                    @endforeach
                                </div>
                            @endif
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
                                <button type="button" wire:click="startReview({{ $dispute->id }})" class="btn btn-secondary mt-3 w-full sm:w-auto">Mulai peninjauan</button>
                            @endif
                            @if (in_array($dispute->status, ['open', 'under_review'], true))
                                <button type="button" wire:click="startResolution({{ $dispute->id }})" class="btn btn-primary mt-2 w-full sm:w-auto">Putuskan sengketa</button>
                            @endif
                            @if ($selectedDisputeId === (string) $dispute->id)
                                <form wire:submit="resolveDispute" class="mt-4 rounded-xl bg-[var(--surface-soft)] p-4 text-left">
                                    <h3 class="font-bold">Keputusan akhir</h3>
                                    <label class="ui-label mt-3" for="resolution-decision-{{ $dispute->id }}">Hasil resolusi</label>
                                    <select id="resolution-decision-{{ $dispute->id }}" wire:model="decision" class="ui-input w-full" required>
                                        <option value="resolved_to_owner">Penyelesaian penuh untuk Owner</option>
                                        <option value="resolved_to_customer">Refund penuh untuk pelanggan</option>
                                        <option value="resolved_split">Pembagian parsial</option>
                                    </select>
                                    @error('decision') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                                    @if ($decision === 'resolved_split')
                                        <label class="ui-label mt-3" for="resolution-refund-{{ $dispute->id }}">Refund pelanggan (Rp)</label>
                                        <input id="resolution-refund-{{ $dispute->id }}" type="number" min="0" step="1" wire:model="refundAmount" class="ui-input w-full" required>
                                        @error('refundAmount') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                                        <label class="ui-label mt-3" for="resolution-payout-{{ $dispute->id }}">Payout Owner (Rp)</label>
                                        <input id="resolution-payout-{{ $dispute->id }}" type="number" min="0" step="1" wire:model="payoutAmount" class="ui-input w-full" required>
                                        @error('payoutAmount') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                                    @endif
                                    <label class="ui-label mt-3" for="resolution-notes-{{ $dispute->id }}">Dasar keputusan dan bukti</label>
                                    <textarea id="resolution-notes-{{ $dispute->id }}" wire:model="resolutionNotes" class="ui-input w-full" rows="4" minlength="20" maxlength="5000" required></textarea>
                                    @error('resolutionNotes') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                                    @error('payment') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                                    @error('payout') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                                    @error('dispute') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Simpan keputusan</button>
                                        <button type="button" wire:click="cancelResolution" class="btn btn-secondary">Batal</button>
                                    </div>
                                    <p class="mt-3 text-xs leading-5 text-[var(--ink-soft)]">Keputusan final mengubah status booking, mencatat award refund/payout, dan memulai refund jika ada. Pastikan nominal parsial sesuai bukti; biaya gateway tidak boleh mengurangi refund pelanggan.</p>
                                </form>
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
