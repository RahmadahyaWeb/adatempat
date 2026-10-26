<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'monthly_price',
        'duration_days',
        'max_businesses',
        'max_venues_per_business',
        'has_combined_reporting',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'integer',
            'duration_days' => 'integer',
            'max_businesses' => 'integer',
            'max_venues_per_business' => 'integer',
            'has_combined_reporting' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
