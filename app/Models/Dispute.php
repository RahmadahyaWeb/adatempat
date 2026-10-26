<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispute extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_RESOLVED_TO_CUSTOMER = 'resolved_to_customer';
    public const STATUS_RESOLVED_TO_OWNER = 'resolved_to_owner';
    public const STATUS_RESOLVED_SPLIT = 'resolved_split';

    public const OPEN_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_UNDER_REVIEW,
    ];

    protected $fillable = [
        'reference_code',
        'booking_id',
        'opened_by_user_id',
        'opened_by_role',
        'category',
        'description',
        'owner_response_deadline_at',
        'owner_responded_at',
        'status',
        'resolution_notes',
        'refund_amount_awarded',
        'payout_amount_awarded',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'owner_response_deadline_at' => 'datetime',
            'owner_responded_at' => 'datetime',
            'refund_amount_awarded' => 'integer',
            'payout_amount_awarded' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DisputeMessage::class)->orderBy('created_at');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(DisputeEvidence::class)->orderBy('created_at');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'auditable_id')
            ->where('auditable_type', self::class)
            ->orderBy('created_at');
    }
}
