<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    public const TYPE_BOOKING = 'booking';
    public const TYPE_SUBSCRIPTION = 'subscription';
    public const TYPE_SUBSCRIPTION_UPGRADE = 'subscription_upgrade';
    public const TYPE_AD_BOOKING = 'ad_booking';

    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PAID = 'paid';
    public const STATUS_VOID = 'void';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'invoice_number',
        'invoice_type',
        'reference_id',
        'recipient_user_id',
        'issuer_name',
        'subtotal_amount',
        'discount_amount',
        'total_amount',
        'status',
        'line_items_json',
        'issued_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'reference_id' => 'integer',
            'subtotal_amount' => 'integer',
            'discount_amount' => 'integer',
            'total_amount' => 'integer',
            'line_items_json' => 'array',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
