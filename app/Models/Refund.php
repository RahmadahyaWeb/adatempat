<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory;

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

    public const OPEN_STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_PROCESSING,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'reference_code',
        'booking_id',
        'ad_booking_id',
        'payment_id',
        'recipient_user_id',
        'amount',
        'reason_type',
        'reason_notes',
        'status',
        'idempotency_key',
        'gateway_refund_id',
        'manual_transfer_reference',
        'processed_by',
        'succeeded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'succeeded_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function adBooking(): BelongsTo
    {
        return $this->belongsTo(AdBooking::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
