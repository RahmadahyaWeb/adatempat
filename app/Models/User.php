<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property int|null $venue_id
 * @property string|null $password
 * @property CarbonInterface|null $email_verified_at
 * @property string|null $remember_token
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Venue|null $venue
 * @property-read Collection<int, Booking> $bookings
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'venue_id',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role' => UserRole::class,
            'password' => 'hashed',
        ];
    }

    public function isPlayer(): bool
    {
        return $this->role === UserRole::Player;
    }

    public function isVenueOperator(): bool
    {
        return $this->role === UserRole::VenueOperator;
    }

    public function isSuperadmin(): bool
    {
        return $this->role === UserRole::Superadmin;
    }

    /**
     * Assigned venue for venue operators.
     *
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * Bookings placed by this player.
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
