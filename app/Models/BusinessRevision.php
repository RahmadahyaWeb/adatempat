<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BusinessRevision extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected static function booted(): void
    {
        static::creating(function (BusinessRevision $revision): void {
            if (! $revision->public_id) {
                $revision->public_id = (string) Str::ulid();
            }
        });
    }

    protected $fillable = [
        'business_id',
        'submitted_by',
        'proposed_name',
        'proposed_address',
        'proposed_city',
        'proposed_province',
        'proposed_latitude',
        'proposed_longitude',
        'proposed_legal_identity',
        'status',
        'review_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'proposed_latitude' => 'decimal:7',
            'proposed_longitude' => 'decimal:7',
            'proposed_legal_identity' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
