<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhookEvent extends Model
{
    use HasFactory;

    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'gateway_provider',
        'gateway_event_id',
        'payment_id',
        'signature_valid',
        'payload_json',
        'processing_status',
        'duplicate_delivery_count',
        'last_duplicate_received_at',
        'processing_notes',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload_json' => 'array',
            'duplicate_delivery_count' => 'integer',
            'last_duplicate_received_at' => 'datetime',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
