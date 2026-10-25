<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'actor_role',
        'action',
        'auditable_type',
        'auditable_id',
        'reason',
        'before_state_json',
        'after_state_json',
        'ip_hash',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before_state_json' => 'array',
            'after_state_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
