<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenuePromotion extends Model
{
    use HasFactory;

    public const FUNDED_BY_OWNER = 'owner';
    public const FUNDED_BY_PLATFORM = 'platform';
    public const DISCOUNT_PERCENTAGE = 'percentage';
    public const DISCOUNT_FIXED = 'fixed_amount';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_SUSPENDED = 'suspended_by_admin';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'venue_id',
        'business_id',
        'code',
        'name',
        'funded_by',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'is_combinable_with_owner_promo',
        'max_quota',
        'used_count',
        'starts_at',
        'ends_at',
        'status',
        'suspension_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'integer',
            'max_discount_amount' => 'integer',
            'is_combinable_with_owner_promo' => 'boolean',
            'max_quota' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeUsableAt(Builder $query, \DateTimeInterface $at): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where('funded_by', self::FUNDED_BY_OWNER)
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>', $at)
            ->where(function (Builder $nested): void {
                $nested->whereNull('max_quota')->orWhereColumn('used_count', '<', 'max_quota');
            });
    }
}
