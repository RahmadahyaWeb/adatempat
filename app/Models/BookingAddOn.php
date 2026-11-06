<?php

namespace App\Models;

use Database\Factories\BookingAddOnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property int $space_add_on_id
 * @property string $name
 * @property string $unit_price
 * @property int $quantity
 * @property string $total_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'booking_id',
    'space_add_on_id',
    'name',
    'unit_price',
    'quantity',
    'total_price',
])]
class BookingAddOn extends Model
{
    /** @use HasFactory<BookingAddOnFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'total_price' => 'decimal:2',
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
     * @return BelongsTo<SpaceAddOn, $this>
     */
    public function spaceAddOn(): BelongsTo
    {
        return $this->belongsTo(SpaceAddOn::class);
    }
}
