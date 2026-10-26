<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    use HasFactory;

    public const STATUS_HELD = 'held';
    public const STATUS_ELIGIBLE = 'eligible';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'reference_code',
        'owner_id',
        'booking_id',
        'payout_account_id',
        'gross_amount',
        'gateway_fee_deducted',
        'net_amount',
        'status',
        'transfer_reference',
        'processed_by',
        'eligible_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'integer',
            'gateway_fee_deducted' => 'integer',
            'net_amount' => 'integer',
            'eligible_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(PayoutAccount::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
