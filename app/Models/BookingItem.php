<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $booking_id
 * @property int $court_slot_id
 * @property int $price_locked
 * @property int $price
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Booking $booking
 * @property-read CourtSlot $courtSlot
 */
class BookingItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'court_slot_id',
        'price_locked',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_locked' => 'integer',
        ];
    }

    /**
     * Parent booking.
     *
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Scheduled court slot claimed by this item.
     *
     * @return BelongsTo<CourtSlot, $this>
     */
    public function courtSlot(): BelongsTo
    {
        return $this->belongsTo(CourtSlot::class);
    }

    /**
     * Convenient alias attribute returning price_locked.
     *
     * @return Attribute<int, never>
     */
    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn (): int => (int) $this->price_locked,
        );
    }
}
