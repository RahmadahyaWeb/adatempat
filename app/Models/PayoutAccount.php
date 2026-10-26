<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayoutAccount extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'owner_id',
        'bank_code',
        'bank_name',
        'account_number_encrypted',
        'account_number_last4',
        'account_holder_name',
        'verification_status',
        'verified_by',
        'verified_at',
    ];

    protected $hidden = ['account_number_encrypted'];

    protected function casts(): array
    {
        return [
            'account_number_encrypted' => 'encrypted',
            'verified_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }
}
