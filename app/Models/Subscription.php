<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    public const STATUS_TRIALING = 'trialing';
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_GRACE_PERIOD = 'grace_period';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_PENDING_REVERIFICATION = 'pending_reverification';
    public const STATUS_CANCELLED = 'cancelled';

    public const BOOKABLE_STATUSES = [
        self::STATUS_TRIALING,
        self::STATUS_ACTIVE,
        self::STATUS_GRACE_PERIOD,
    ];

    protected $fillable = [
        'owner_id',
        'plan_id',
        'scheduled_plan_id',
        'retained_business_id',
        'is_trial',
        'status',
        'starts_at',
        'ends_at',
        'grace_ends_at',
        'suspended_at',
        'reverified_by',
    ];

    protected function casts(): array
    {
        return [
            'is_trial' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function scheduledPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'scheduled_plan_id');
    }

    public function retainedBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'retained_business_id');
    }

    public function reverifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverified_by');
    }

    public function scopeEligibleAt(Builder $query, CarbonInterface $at): Builder
    {
        return $query
            ->where('starts_at', '<=', $at)
            ->where(function (Builder $active): void {
                $active->whereIn('status', [self::STATUS_TRIALING, self::STATUS_ACTIVE])
                    ->where('ends_at', '>', now('UTC'));
            })
            ->orWhere(function (Builder $grace) use ($at): void {
                $grace->where('status', self::STATUS_GRACE_PERIOD)
                    ->where('starts_at', '<=', $at)
                    ->where('grace_ends_at', '>=', $at);
            });
    }

    public function isEligibleAt(CarbonInterface $at): bool
    {
        if ($this->starts_at->greaterThan($at)) {
            return false;
        }

        return match ($this->status) {
            self::STATUS_TRIALING, self::STATUS_ACTIVE => $this->ends_at->greaterThan($at),
            self::STATUS_GRACE_PERIOD => $this->grace_ends_at->greaterThanOrEqualTo($at),
            default => false,
        };
    }
}
