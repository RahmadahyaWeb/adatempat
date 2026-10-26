<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAYMENT_VERIFYING = 'payment_verifying';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_AWAITING_COMPLETION = 'awaiting_completion';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED_BY_CUSTOMER = 'cancelled_by_customer';
    public const STATUS_CANCELLED_BY_OWNER = 'cancelled_by_owner';
    public const STATUS_CANCELLED_BY_ADMIN = 'cancelled_by_admin';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_RECONCILIATION_REQUIRED = 'reconciliation_required';
    public const STATUS_DISPUTED = 'disputed';

    public const SLOT_BLOCKING_STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PAYMENT_VERIFYING,
        self::STATUS_CONFIRMED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_AWAITING_COMPLETION,
        self::STATUS_COMPLETED,
        self::STATUS_RECONCILIATION_REQUIRED,
        self::STATUS_DISPUTED,
    ];

    protected $fillable = [
        'reference_code',
        'venue_id',
        'business_id',
        'customer_id',
        'pricing_unit',
        'duration_units',
        'booking_start',
        'booking_end',
        'payment_required',
        'status',
        'owner_promotion_id',
        'platform_promotion_id',
        'snapshot_base_amount',
        'snapshot_owner_discount_amount',
        'snapshot_platform_subsidy_amount',
        'snapshot_actual_paid_amount',
        'snapshot_gateway_fee_amount',
        'snapshot_net_owner_entitlement',
        'snapshot_refund_policy_json',
        'terms_acceptance_id',
        'cancellation_reason',
        'cancelled_by',
        'confirmed_at',
        'cancelled_at',
        'completion_window_ends_at',
        'completed_at',
        'slot_released_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_units' => 'integer',
            'booking_start' => 'datetime',
            'booking_end' => 'datetime',
            'payment_required' => 'boolean',
            'snapshot_base_amount' => 'integer',
            'snapshot_owner_discount_amount' => 'integer',
            'snapshot_platform_subsidy_amount' => 'integer',
            'snapshot_actual_paid_amount' => 'integer',
            'snapshot_gateway_fee_amount' => 'integer',
            'snapshot_net_owner_entitlement' => 'integer',
            'snapshot_refund_policy_json' => 'array',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completion_window_ends_at' => 'datetime',
            'completed_at' => 'datetime',
            'slot_released_at' => 'datetime',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function termsAcceptance(): BelongsTo
    {
        return $this->belongsTo(TermsAcceptance::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(BookingHold::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payable_id')->where('payable_type', Payment::TYPE_BOOKING);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function dispute(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Dispute::class);
    }

    public function payout(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Payout::class);
    }
}
