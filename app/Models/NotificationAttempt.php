<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationAttempt extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'notification_id',
        'attempt_number',
        'provider',
        'status',
        'provider_message_id',
        'request_metadata_json',
        'response_metadata_json',
        'error_code',
        'error_message',
        'started_at',
        'finished_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'request_metadata_json' => 'array',
            'response_metadata_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
