<?php

namespace App\Models;

use App\Enums\CancellationPolicy;
use App\Enums\SpaceCategory;
use Database\Factories\SpaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $venue_id
 * @property string $name
 * @property string $slug
 * @property SpaceCategory $category
 * @property int|null $capacity
 * @property string $base_price
 * @property bool $instant_book
 * @property CancellationPolicy $cancellation_policy
 * @property int $cancellation_cutoff_hours
 * @property string $refund_percentage
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'slug',
    'category',
    'capacity',
    'base_price',
    'instant_book',
    'cancellation_policy',
    'cancellation_cutoff_hours',
    'refund_percentage',
    'is_active',
])]
class Space extends Model
{
    /** @use HasFactory<SpaceFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => SpaceCategory::class,
            'capacity' => 'integer',
            'base_price' => 'decimal:2',
            'instant_book' => 'boolean',
            'cancellation_policy' => CancellationPolicy::class,
            'cancellation_cutoff_hours' => 'integer',
            'refund_percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return BelongsToMany<Amenity, $this>
     */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'space_amenities')->withTimestamps();
    }

    /**
     * @return HasMany<SpaceAddOn, $this>
     */
    public function addOns(): HasMany
    {
        return $this->hasMany(SpaceAddOn::class);
    }

    /**
     * @return HasMany<SpaceScheduleVersion, $this>
     */
    public function scheduleVersions(): HasMany
    {
        return $this->hasMany(SpaceScheduleVersion::class);
    }

    /**
     * @return HasMany<Schedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
