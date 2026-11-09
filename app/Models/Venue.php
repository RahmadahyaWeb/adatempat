<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venue extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Default model attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'booking_horizon_days' => 30,
        'allow_reschedule' => true,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Trial->value,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'city',
        'address',
        'phone',
        'description',
        'facilities',
        'booking_horizon_days',
        'allow_reschedule',
        'is_active',
        'subscription_status',
        'subscription_expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'facilities' => 'array',
            'booking_horizon_days' => 'integer',
            'allow_reschedule' => 'boolean',
            'is_active' => 'boolean',
            'subscription_status' => SubscriptionStatus::class,
            'subscription_expires_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the venue maintains an active SaaS subscription.
     */
    public function hasActiveSubscription(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (! $this->subscription_status->canMutate()) {
            return false;
        }

        if ($this->subscription_expires_at !== null && $this->subscription_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Courts configured under this venue.
     *
     * @return HasMany<Court, $this>
     */
    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    /**
     * Operators assigned to this venue.
     *
     * @return HasMany<User, $this>
     */
    public function operators(): HasMany
    {
        return $this->hasMany(User::class, 'venue_id');
    }
}
