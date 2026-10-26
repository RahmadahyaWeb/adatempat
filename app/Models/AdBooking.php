<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdBooking extends Model
{
    use HasFactory;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'reference_code',
        'owner_id',
        'business_id',
        'banner_image_path',
        'headline',
        'cta_url',
        'price_amount',
        'starts_at',
        'ends_at',
        'status',
        'confirmed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payable_id')->where('payable_type', Payment::TYPE_AD_BOOKING);
    }
}
