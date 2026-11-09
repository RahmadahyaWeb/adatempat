<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SlotStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtSlot extends Model
{
    use HasFactory;

    /**
     * Default model attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => SlotStatus::Available->value,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'court_id',
        'date',
        'start_time',
        'end_time',
        'price',
        'status',
        'locked_until',
        'block_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'status' => SlotStatus::class,
            'price' => 'integer',
            'locked_until' => 'datetime',
        ];
    }

    /**
     * Court hosting this time slot.
     *
     * @return BelongsTo<Court, $this>
     */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /**
     * Scope to available slots only.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', SlotStatus::Available);
    }

    /**
     * Scope to strictly future slots relative to a specific time.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeFuture(Builder $query, ?CarbonInterface $from = null): Builder
    {
        $now = $from ?? now();
        $currentDate = $now->toDateString();
        $currentTime = $now->toTimeString();

        return $query->where(function (Builder $subQuery) use ($currentDate, $currentTime) {
            $subQuery->where('date', '>', $currentDate)
                ->orWhere(function (Builder $todayQuery) use ($currentDate, $currentTime) {
                    $todayQuery->where('date', '=', $currentDate)
                        ->where('start_time', '>', $currentTime);
                });
        });
    }

    /**
     * Resolve effective slot status, accounting for expired checkout holds dynamically.
     */
    public function resolveEffectiveStatus(?CarbonInterface $referenceTime = null): SlotStatus
    {
        if ($this->status === SlotStatus::Held) {
            $now = $referenceTime ?? now();
            if ($this->locked_until !== null && $this->locked_until->gt($now)) {
                return SlotStatus::Held;
            }

            return SlotStatus::Available;
        }

        return $this->status;
    }

    /**
     * Determine whether this slot is currently bookable by a player.
     */
    public function isBookable(?CarbonInterface $referenceTime = null): bool
    {
        return $this->resolveEffectiveStatus($referenceTime)->isBookable();
    }
}
