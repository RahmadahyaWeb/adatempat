<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property int $space_id
 * @property int|null $schedule_version_id
 * @property Carbon $slot_date
 * @property string $start_time
 * @property string $end_time
 * @property string $price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'booking_id',
    'space_id',
    'schedule_version_id',
    'slot_date',
    'start_time',
    'end_time',
    'price',
])]
class BookingSlot extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot_date' => 'date',
            'price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * @return BelongsTo<SpaceScheduleVersion, $this>
     */
    public function scheduleVersion(): BelongsTo
    {
        return $this->belongsTo(SpaceScheduleVersion::class, 'schedule_version_id');
    }
}
