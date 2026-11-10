<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'order_id',
        'midtrans_transaction_id',
        'gross_amount',
        'payment_type',
        'status',
        'qr_string',
        'qr_url',
        'expiry_time',
        'settled_at',
        'raw_payload',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_amount' => 'integer',
            'expiry_time' => 'datetime',
            'settled_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    /**
     * Booking associated with this payment attempt.
     *
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
