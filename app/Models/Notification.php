<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    use HasFactory;

    public const CHANNEL_DATABASE = 'database';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'recipient_user_id',
        'guest_session_id',
        'channel',
        'notification_type',
        'title',
        'body',
        'payload_json',
        'deduplication_key',
        'status',
        'available_at',
        'sent_at',
        'read_at',
        'last_error_code',
        'last_error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'available_at' => 'datetime',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $notification): void {
            if (($notification->recipient_user_id === null) === ($notification->guest_session_id === null)) {
                throw new \LogicException('Exactly one notification recipient identity must be set.');
            }
        });
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function guestSession(): BelongsTo
    {
        return $this->belongsTo(GuestSession::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(NotificationAttempt::class)->orderBy('attempt_number');
    }
}
