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
        $startsAtUtc = CarbonImmutable::parse($validated['startsAt'], $timezone)->utc();
        $endsAtUtc = CarbonImmutable::parse($validated['endsAt'], $timezone)->utc();

        $promotion = DB::transaction(function () use ($owner, $validated, $startsAtUtc, $endsAtUtc): VenuePromotion {
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

        DB::transaction(function () use ($owner, $promotionId): void {
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

                    return;
                }

                if ($promotion->max_quota !== null && $promotion->used_count >= $promotion->max_quota) {
                    $this->addError('promotionStatus', 'Kuota promosi sudah habis dan tidak dapat diaktifkan kembali.');

                    return;
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
        });

        unset($this->currentVenue);
        session()->flash('status', 'Status promosi diperbarui.');
    }
};
?>