<?php

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Venue;
use App\Models\VenuePromotion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Promosi venue')] class extends Component {
    public string $businessPublicId;
    public string $venuePublicId;
    public string $name = '';
    public string $code = '';
    public string $discountType = 'percentage';
    public string $discountValue = '';
    public string $maxDiscountAmount = '';
    public string $maxQuota = '';
    public string $startsAt = '';
    public string $endsAt = '';

    public function mount(string $business, string $venue): void
    {
        $businessRecord = auth()->user()->businesses()->where('public_id', $business)->firstOrFail();
        Gate::authorize('view', $businessRecord);
        abort_unless($businessRecord->status !== Business::STATUS_SUSPENDED, 403);

        $venueRecord = $businessRecord->venues()->where('public_id', $venue)->firstOrFail();
        Gate::authorize('view', $venueRecord);
        abort_unless($venueRecord->status !== Venue::STATUS_ARCHIVED, 403);

        $this->businessPublicId = (string) $businessRecord->public_id;
        $this->venuePublicId = (string) $venueRecord->public_id;
        $timezone = $businessRecord->timezone ?: 'Asia/Jakarta';
        $this->startsAt = now($timezone)->addDay()->startOfHour()->format('Y-m-d\TH:i');
        $this->endsAt = now($timezone)->addDays(8)->startOfHour()->format('Y-m-d\TH:i');
    }

    #[Computed]
    public function currentBusiness(): Business
    {
        return auth()->user()->businesses()->where('public_id', $this->businessPublicId)->firstOrFail();
    }

    #[Computed]
    public function currentVenue(): Venue
    {
        return $this->currentBusiness->venues()
            ->with('promotions')
            ->where('public_id', $this->venuePublicId)
            ->firstOrFail();
    }

    public function save(): void
    {
        $owner = auth()->user();
        abort_unless($owner && $owner->isOwner(), 403);
        Gate::authorize('update', $this->currentVenue);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:140'],
            'code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
            'discountType' => ['required', Rule::in([VenuePromotion::DISCOUNT_PERCENTAGE, VenuePromotion::DISCOUNT_FIXED])],
            'discountValue' => [
                'required',
                'integer',
                'min:1',
                $this->discountType === VenuePromotion::DISCOUNT_PERCENTAGE ? 'max:100' : 'max:1000000000000',
            ],
            'maxDiscountAmount' => [
                Rule::requiredIf($this->discountType === VenuePromotion::DISCOUNT_PERCENTAGE),
                'nullable',
                'integer',
                'min:1',
                'max:1000000000000',
            ],
            'maxQuota' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'startsAt' => ['required', 'date_format:Y-m-d\\TH:i'],
            'endsAt' => ['required', 'date_format:Y-m-d\\TH:i', 'after:startsAt'],
        ], [
            'name.required' => 'Nama promosi wajib diisi.',
            'name.max' => 'Nama promosi maksimal 140 karakter.',
            'code.regex' => 'Kode hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
            'discountType.in' => 'Jenis diskon tidak valid.',
            'discountValue.required' => 'Nilai diskon wajib diisi.',
            'discountValue.integer' => 'Nilai diskon harus berupa bilangan bulat.',
            'discountValue.min' => 'Nilai diskon minimal 1.',
            'discountValue.max' => 'Diskon persentase maksimal 100%.',
            'maxDiscountAmount.required' => 'Batas nominal diskon wajib diisi untuk diskon persentase.',
            'maxDiscountAmount.integer' => 'Batas nominal diskon harus berupa bilangan bulat Rupiah.',
            'maxDiscountAmount.min' => 'Batas nominal diskon minimal Rp1.',
            'maxQuota.integer' => 'Kuota harus berupa bilangan bulat.',
            'maxQuota.min' => 'Kuota minimal 1 penggunaan.',
            'startsAt.required' => 'Waktu mulai wajib diisi.',
            'startsAt.date_format' => 'Format waktu mulai tidak valid.',
            'endsAt.required' => 'Waktu selesai wajib diisi.',
            'endsAt.date_format' => 'Format waktu selesai tidak valid.',
            'endsAt.after' => 'Waktu selesai harus lebih akhir daripada waktu mulai.',
        ]);

        if ($validated['discountType'] === VenuePromotion::DISCOUNT_FIXED && $validated['discountValue'] > 1000000000000) {
            $this->addError('discountValue', 'Nominal diskon melampaui batas sistem.');

            return;
        }

        $timezone = $this->currentBusiness->timezone ?: 'Asia/Jakarta';
        $startsAtLocal = CarbonImmutable::parse($validated['startsAt'], $timezone);
        $endsAtLocal = CarbonImmutable::parse($validated['endsAt'], $timezone);

        if (! $startsAtLocal->isFuture()) {
            $this->addError('startsAt', 'Waktu mulai promosi harus berada di masa depan.');

            return;
        }

        $startsAtUtc = $startsAtLocal->utc();
        $endsAtUtc = $endsAtLocal->utc();

        DB::transaction(function () use ($owner, $validated, $startsAtUtc, $endsAtUtc): VenuePromotion {
            $promotion = $this->currentVenue->promotions()->create([
                'business_id' => null,
                'code' => filled($validated['code']) ? strtoupper(trim($validated['code'])) : null,
                'name' => trim($validated['name']),
                'funded_by' => VenuePromotion::FUNDED_BY_OWNER,
                'discount_type' => $validated['discountType'],
                'discount_value' => (int) $validated['discountValue'],
                'max_discount_amount' => $validated['discountType'] === VenuePromotion::DISCOUNT_PERCENTAGE
                    ? (int) $validated['maxDiscountAmount']
                    : null,
                'is_combinable_with_owner_promo' => false,
                'max_quota' => filled($validated['maxQuota']) ? (int) $validated['maxQuota'] : null,
                'used_count' => 0,
                'starts_at' => $startsAtUtc,
                'ends_at' => $endsAtUtc,
                'status' => VenuePromotion::STATUS_ACTIVE,
                'created_by' => $owner->getKey(),
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $owner->getKey(),
                'actor_role' => $owner->role,
                'action' => 'venue_promotion.created',
                'auditable_type' => VenuePromotion::class,
                'auditable_id' => $promotion->getKey(),
                'reason' => 'Owner membuat promosi venue.',
                'before_state_json' => null,
                'after_state_json' => [
                    'venue_id' => $this->currentVenue->getKey(),
                    'name' => $promotion->name,
                    'discount_type' => $promotion->discount_type,
                    'discount_value' => $promotion->discount_value,
                    'starts_at' => $promotion->starts_at?->toISOString(),
                    'ends_at' => $promotion->ends_at?->toISOString(),
                    'status' => $promotion->status,
                ],
                'ip_hash' => hash('sha256', (string) request()->ip()),
                'created_at' => now(),
            ]);

            return $promotion;
        });

        $this->reset(['name', 'code', 'discountValue', 'maxDiscountAmount', 'maxQuota']);
        $this->discountType = VenuePromotion::DISCOUNT_PERCENTAGE;
        $this->resetValidation();
        unset($this->currentVenue);
        session()->flash('status', 'Promosi berhasil dibuat.');
    }

    public function toggleStatus(int $promotionId): void
    {
        $owner = auth()->user();
        abort_unless($owner && $owner->isOwner(), 403);
        Gate::authorize('update', $this->currentVenue);

        $changed = DB::transaction(function () use ($owner, $promotionId): bool {
            $promotion = $this->currentVenue->promotions()->whereKey($promotionId)->lockForUpdate()->firstOrFail();
            abort_unless($promotion->funded_by === VenuePromotion::FUNDED_BY_OWNER, 403);
            abort_unless($promotion->status !== VenuePromotion::STATUS_SUSPENDED, 403);

            $before = $promotion->status;
            $next = $before === VenuePromotion::STATUS_ACTIVE
                ? VenuePromotion::STATUS_PAUSED
                : VenuePromotion::STATUS_ACTIVE;

            if ($next === VenuePromotion::STATUS_ACTIVE) {
                if ($promotion->ends_at->isPast()) {
                    $this->addError('promotionStatus', 'Promosi yang sudah berakhir tidak dapat diaktifkan kembali.');

                    return false;
                }

                if ($promotion->max_quota !== null && $promotion->used_count >= $promotion->max_quota) {
                    $this->addError('promotionStatus', 'Kuota promosi sudah habis dan tidak dapat diaktifkan kembali.');

                    return false;
                }
            }

            $promotion->update(['status' => $next]);

            AuditLog::query()->create([
                'actor_user_id' => $owner->getKey(),
                'actor_role' => $owner->role,
                'action' => 'venue_promotion.status_changed',
                'auditable_type' => VenuePromotion::class,
                'auditable_id' => $promotion->getKey(),
                'reason' => 'Owner mengubah status promosi.',
                'before_state_json' => ['status' => $before],
                'after_state_json' => ['status' => $next],
                'ip_hash' => hash('sha256', (string) request()->ip()),
                'created_at' => now(),
            ]);

            return true;
        });

        if (! $changed) {
            return;
        }

        unset($this->currentVenue);
        session()->flash('status', 'Status promosi diperbarui.');
    }
};
?>

<div class="site-container py-7 sm:py-10 lg:py-12">
    @php
        $business = $this->currentBusiness;
        $venue = $this->currentVenue;
        $timezone = $business->timezone ?: 'Asia/Jakarta';
    @endphp

    <a href="{{ route('owner.businesses.venues.index', ['business' => $business->public_id]) }}" class="inline-flex min-h-9 items-center gap-2 text-sm font-semibold text-[var(--ink-soft)] underline-offset-4 hover:text-[var(--brand-forest)] hover:underline">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m15 18-6-6 6-6"/></svg>
        Kembali ke daftar venue
    </a>

    <header class="mt-5 max-w-3xl">
        <p class="eyebrow"><span class="eyebrow-dot"></span> Promosi venue</p>
        <h1 class="mt-3 break-words text-3xl font-extrabold leading-tight tracking-[-0.055em] sm:text-4xl">Kelola promosi</h1>
        <p class="mt-2 text-sm font-semibold text-[var(--ink-soft)]">{{ $venue->name }} · {{ $business->name }}</p>
        <p class="mt-3 text-sm leading-6 text-[var(--muted)]">Buat diskon persentase atau nominal tetap untuk venue ini. Jadwal menggunakan zona waktu {{ $timezone }}. Promosi hanya tersimpan di tahap ini; pemilihan diskon saat checkout akan ditangani Booking Engine.</p>
    </header>

    @if (session('status'))
        <div role="status" class="status-note status-success mt-6">{{ session('status') }}</div>
    @endif
    @error('promotionStatus')
        <div role="alert" class="status-note mt-4">{{ $message }}</div>
    @enderror

    <div class="mt-7 grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
        <section class="h-fit rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 sm:p-6">
            <h2 class="text-lg font-extrabold tracking-[-0.035em]">Buat promosi baru</h2>
            <p class="mt-1 text-sm leading-6 text-[var(--muted)]">Diskon didanai oleh Owner dan tidak dapat digabung dengan promosi Owner lain dalam satu booking.</p>

            <form wire:submit="save" class="mt-5 space-y-4">
                <div>
                    <label for="promotion-name" class="ui-label">Nama promosi</label>
                    <input id="promotion-name" type="text" wire:model="name" maxlength="140" required class="ui-input" placeholder="Contoh: Promo pembukaan">
                    @error('name') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="promotion-code" class="ui-label">Kode promosi (opsional)</label>
                    <input id="promotion-code" type="text" wire:model="code" maxlength="50" class="ui-input" placeholder="HEMAT10">
                    @error('code') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="promotion-discount-type" class="ui-label">Jenis diskon</label>
                    <select id="promotion-discount-type" wire:model.live="discountType" class="ui-input">
                        <option value="percentage">Persentase (%)</option>
                        <option value="fixed_amount">Nominal tetap (Rupiah)</option>
                    </select>
                    @error('discountType') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="promotion-discount-value" class="ui-label">{{ $discountType === 'percentage' ? 'Persentase diskon' : 'Nominal diskon (Rp)' }}</label>
                    <input id="promotion-discount-value" type="number" wire:model="discountValue" min="1" max="{{ $discountType === 'percentage' ? 100 : 1000000000000 }}" required class="ui-input" placeholder="{{ $discountType === 'percentage' ? '10' : '50000' }}">
                    @error('discountValue') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                @if ($discountType === 'percentage')
                    <div>
                        <label for="promotion-max-discount" class="ui-label">Batas maksimal diskon (Rp)</label>
                        <input id="promotion-max-discount" type="number" wire:model="maxDiscountAmount" min="1" max="1000000000000" required class="ui-input" placeholder="100000">
                        @error('maxDiscountAmount') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div>
                    <label for="promotion-max-quota" class="ui-label">Batas penggunaan (opsional)</label>
                    <input id="promotion-max-quota" type="number" wire:model="maxQuota" min="1" max="1000000000" class="ui-input" placeholder="Kosongkan untuk tanpa batas kuota">
                    @error('maxQuota') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="promotion-starts-at" class="ui-label">Mulai</label>
                        <input id="promotion-starts-at" type="datetime-local" wire:model="startsAt" required class="ui-input">
                        @error('startsAt') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="promotion-ends-at" class="ui-label">Berakhir</label>
                        <input id="promotion-ends-at" type="datetime-local" wire:model="endsAt" required class="ui-input">
                        @error('endsAt') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="text-xs leading-5 text-[var(--muted)]">Waktu disimpan sebagai UTC. Sistem booking nantinya akan memilih maksimal satu promosi Owner dengan potongan nominal terbesar; jika sama, promosi dengan ID terkecil dipilih.</p>
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full">
                    <span wire:loading.remove wire:target="save">Simpan promosi</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </button>
            </form>
        </section>

        <section class="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 sm:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-extrabold tracking-[-0.035em]">Daftar promosi</h2>
                    <p class="mt-1 text-sm text-[var(--muted)]">{{ $venue->promotions->count() }} promosi tercatat</p>
                </div>
                <span class="text-xs font-semibold text-[var(--muted)]">Zona waktu: {{ $timezone }}</span>
            </div>

            <div class="mt-5 space-y-3">
                @forelse ($venue->promotions as $promotion)
                    @php
                        $statusLabels = [
                            'active' => ['Aktif', 'bg-[var(--success-bg)] text-[var(--success-ink)]'],
                            'paused' => ['Dijeda', 'bg-[var(--surface-soft)] text-[var(--ink-soft)]'],
                            'suspended_by_admin' => ['Ditangguhkan admin', 'bg-[var(--danger-bg)] text-[var(--danger-ink)]'],
                            'expired' => ['Berakhir', 'bg-[var(--surface-soft)] text-[var(--muted)]'],
                        ];
                        [$statusLabel, $statusClass] = $statusLabels[$promotion->status] ?? ['Tidak diketahui', 'bg-[var(--surface-soft)] text-[var(--muted)]'];
                        $startsLocal = $promotion->starts_at->copy()->setTimezone($timezone);
                        $endsLocal = $promotion->ends_at->copy()->setTimezone($timezone);
                    @endphp
                    <article wire:key="venue-promotion-{{ $promotion->id }}" class="rounded-xl border border-[var(--border)] p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <h3 class="break-words text-base font-extrabold text-[var(--ink)]">{{ $promotion->name }}</h3>
                                @if ($promotion->code)
                                    <p class="mt-1 text-xs font-bold tracking-wide text-[var(--brand-forest)]">Kode: {{ $promotion->code }}</p>
                                @endif
                            </div>
                            <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-bold {{ $statusClass }}">{{ $statusLabel }}</span>
                        </div>
                        <p class="mt-3 text-sm font-semibold text-[var(--ink)]">
                            @if ($promotion->discount_type === 'percentage')
                                Diskon {{ $promotion->discount_value }}% · Maks. Rp{{ number_format($promotion->max_discount_amount, 0, ',', '.') }}
                            @else
                                Diskon Rp{{ number_format($promotion->discount_value, 0, ',', '.') }}
                            @endif
                        </p>
                        <p class="mt-1 text-xs leading-5 text-[var(--muted)]">{{ $startsLocal->format('d/m/Y H:i') }} – {{ $endsLocal->format('d/m/Y H:i') }}</p>
                        <p class="mt-1 text-xs leading-5 text-[var(--muted)]">
                            Penggunaan: {{ number_format($promotion->used_count, 0, ',', '.') }}{{ $promotion->max_quota !== null ? ' / '.number_format($promotion->max_quota, 0, ',', '.') : '' }}
                        </p>
                        @if ($promotion->funded_by === 'owner' && $promotion->status !== 'suspended_by_admin' && $promotion->status !== 'expired' && !$promotion->ends_at->isPast())
                            <div class="mt-4 flex justify-end">
                                <button type="button" wire:click="toggleStatus({{ $promotion->id }})" wire:loading.attr="disabled" class="btn btn-secondary w-full sm:w-auto">
                                    {{ $promotion->status === 'active' ? 'Jeda promosi' : 'Aktifkan promosi' }}
                                </button>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed border-[var(--border-strong)] p-6 text-center">
                        <p class="text-sm font-semibold text-[var(--ink)]">Belum ada promosi</p>
                        <p class="mt-1 text-sm text-[var(--muted)]">Promosi yang dibuat akan muncul di sini.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
