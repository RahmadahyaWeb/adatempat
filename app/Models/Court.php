<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SportType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $venue_id
 * @property string $name
 * @property SportType $sport_type
 * @property string $opening_time
 * @property string $closing_time
 * @property int $default_hourly_rate
 * @property bool $is_active
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property CarbonInterface|null $deleted_at
 * @property-read Venue $venue
 * @property-read Collection<int, CourtPricingRule> $pricingRules
 * @property-read Collection<int, CourtSlot> $slots
 */
class Court extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Default model attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'opening_time' => '06:00:00',
        'closing_time' => '23:00:00',
        'is_active' => true,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'venue_id',
        'name',
        'sport_type',
        'opening_time',
        'closing_time',
        'default_hourly_rate',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sport_type' => SportType::class,
            'default_hourly_rate' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Venue hosting this court.
     *
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * Tiered pricing rules configured for this court.
     *
     * @return HasMany<CourtPricingRule, $this>
     */
    public function pricingRules(): HasMany
    {
        return $this->hasMany(CourtPricingRule::class);
    }

    /**
     * Scheduled discrete 60-minute slots generated for this court.
     *
     * @return HasMany<CourtSlot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(CourtSlot::class);
    }
}
