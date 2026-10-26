<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class LedgerEntry extends Model
{
    use HasFactory;

    public const TYPE_DEBIT = 'debit';
    public const TYPE_CREDIT = 'credit';

    public const ACCOUNT_CODES = [
        'customer_cash',
        'gateway_clearing',
        'escrow_liability',
        'owner_payable',
        'platform_subsidy_expense',
        'gateway_fee_expense',
        'subscription_revenue',
        'ad_revenue',
        'refund_payable',
    ];

    public $timestamps = false;

    protected $fillable = [
        'transaction_group_ulid',
        'account_code',
        'entry_type',
        'amount',
        'reference_type',
        'reference_id',
        'description',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are immutable; post a reversal entry instead.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are immutable and cannot be deleted.'));
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }
}
