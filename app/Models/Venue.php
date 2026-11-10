<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $city
 * @property string $address
 * @property string|null $phone
 * @property string|null $description
 * @property array<string>|null $facilities
 * @property int $booking_horizon_days
 * @property bool $allow_reschedule
 * @property bool $is_active
 * @property SubscriptionStatus $subscription_status
 * @property CarbonInterface|null $subscription_expires_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property CarbonInterface|null $deleted_at
 * @property-read Collection<int, Court> $courts
 * @property-read Collection<int, User> $operators
 * @property-read Collection<int, Booking> $bookings
 */
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
     * Scope query to venues that are publicly discoverable on the marketplace.
     * Excludes inactive, suspended, or expired SaaS subscription venues.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePubliclyDiscoverable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereIn('subscription_status', [
                SubscriptionStatus::Trial->value,
                SubscriptionStatus::Active->value,
            ])
            ->where(function (Builder $subQuery) {
                $subQuery->whereNull('subscription_expires_at')
                    ->orWhere('subscription_expires_at', '>', now());
            });
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

    /**
     * Bookings hosted by this venue.
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Supported unique sports across active courts.
     *
     * @return list<string>
     */
    public function activeSports(): array
    {
        $activeCourts = $this->relationLoaded('courts')
            ? $this->courts->where('is_active', true)
            : $this->courts()->where('is_active', true)->get();

        return $activeCourts->pluck('sport_type')
            ->map(fn ($sport): string => is_object($sport) ? $sport->value : (string) $sport)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Lowest hourly court rental rate across active courts.
     */
    public function startingPrice(): int
    {
        $activeCourts = $this->relationLoaded('courts')
            ? $this->courts->where('is_active', true)
            : $this->courts()->where('is_active', true)->get();

        return (int) ($activeCourts->min('default_hourly_rate') ?? 0);
    }
}
