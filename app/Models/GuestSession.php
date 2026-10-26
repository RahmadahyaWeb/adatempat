<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_token_hash',
        'nickname',
        'converted_to_user_id',
        'ip_hash',
        'expires_at',
        'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    public function convertedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_to_user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
