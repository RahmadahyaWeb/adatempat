<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenuePrice extends Model
{
    use HasFactory;

    public const UNIT_HOURLY = 'hourly';
    public const UNIT_SESSION = 'session';
    public const UNIT_DAILY = 'daily';

    protected $fillable = [
        'venue_id',
        'pricing_unit',
        'session_name',
        'session_start_time',
        'session_end_time',
        'step_interval_minutes',
        'min_duration_units',
        'max_duration_units',
        'price_amount',
        'weekend_price_amount',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'step_interval_minutes' => 'integer',
            'min_duration_units' => 'integer',
            'max_duration_units' => 'integer',
            'price_amount' => 'integer',
            'weekend_price_amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
