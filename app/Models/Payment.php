<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    public const TYPE_BOOKING = 'booking';
    public const TYPE_SUBSCRIPTION = 'subscription';
    public const TYPE_AD_BOOKING = 'ad_booking';

    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFYING = 'verifying';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_RECONCILIATION_REQUIRED = 'reconciliation_required';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    protected $fillable = [
        'payable_type',
        'payable_id',
        'payer_user_id',
        'gateway_provider',
        'gateway_transaction_id',
        'idempotency_key',
        'qris_payload',
        'amount',
        'gateway_fee_amount',
        'status',
        'paid_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'payable_id' => 'integer',
            'amount' => 'integer',
            'gateway_fee_amount' => 'integer',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'payable_id')
            ->where('payable_type', self::TYPE_BOOKING);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'payable_id')
            ->where('payable_type', self::TYPE_SUBSCRIPTION);
    }

    public function adBooking(): BelongsTo
    {
        return $this->belongsTo(AdBooking::class, 'payable_id')
            ->where('payable_type', self::TYPE_AD_BOOKING);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(PaymentWebhookEvent::class);
    }
}
