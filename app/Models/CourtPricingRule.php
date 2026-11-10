<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DayType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $court_id
 * @property DayType $day_type
 * @property string $start_time
 * @property string $end_time
 * @property int $price_per_hour
 * @property string|null $tier_name
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Court $court
 */
class CourtPricingRule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'court_id',
        'day_type',
        'start_time',
        'end_time',
        'price_per_hour',
        'tier_name',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_type' => DayType::class,
            'price_per_hour' => 'integer',
        ];
    }

    /**
     * Court this pricing rule applies to.
     *
     * @return BelongsTo<Court, $this>
     */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
