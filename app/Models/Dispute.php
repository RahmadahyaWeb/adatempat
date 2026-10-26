<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispute extends Model
{
    use HasFactory;

    public const STATUS_AWAITING_RESPONSE = 'awaiting_response';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_REJECTED = 'rejected';

    public const REASON_SERVICE_NOT_PROVIDED = 'service_not_provided';
    public const REASON_VENUE_CONDITION = 'venue_condition';
    public const REASON_BOOKING_MISMATCH = 'booking_mismatch';
    public const REASON_PAYMENT_OR_REFUND = 'payment_or_refund';
    public const REASON_OTHER = 'other';

    public const RESOLUTION_FULL_OWNER_PAYOUT = 'full_owner_payout';
    public const RESOLUTION_FULL_CUSTOMER_REFUND = 'full_customer_refund';
    public const RESOLUTION_PARTIAL_SPLIT = 'partial_split';

    protected $fillable = [
        'reference_code', 'booking_id', 'opened_by', 'against_user_id', 'reason_type',
        'description', 'status', 'response_deadline', 'resolution_type',
        'customer_refund_amount', 'owner_payout_amount', 'resolution_notes',
        'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'response_deadline' => 'datetime',
            'customer_refund_amount' => 'integer',
            'owner_payout_amount' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function opener(): BelongsTo { return $this->belongsTo(User::class, 'opened_by'); }
    public function respondent(): BelongsTo { return $this->belongsTo(User::class, 'against_user_id'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
    public function messages(): HasMany { return $this->hasMany(DisputeMessage::class)->orderBy('created_at'); }
    public function evidence(): HasMany { return $this->hasMany(DisputeEvidence::class)->orderBy('created_at'); }
}
