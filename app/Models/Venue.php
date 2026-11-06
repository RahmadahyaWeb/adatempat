<?php

namespace App\Models;

use App\Enums\VenueApprovalStatus;
use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string $slug
 * @property string $city
 * @property string $address
 * @property string $timezone
 * @property string|null $cover_photo_path
 * @property string|null $contact_phone
 * @property bool $is_published
 * @property VenueApprovalStatus $approval_status
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $cover_url
 * @property-read int $spaces_count
 */
#[Fillable([
    'business_id',
    'name',
    'slug',
    'city',
    'address',
    'timezone',
    'cover_photo_path',
    'contact_phone',
    'is_published',
    'approval_status',
    'approved_at',
    'approved_by',
    'rejection_reason',
])]
class Venue extends Model
{
    /** @use HasFactory<VenueFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = [
        'cover_url',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if (! $this->cover_photo_path || ! $this->slug) {
                    return null;
                }

                if (str_starts_with($this->cover_photo_path, 'http://') || str_starts_with($this->cover_photo_path, 'https://') || str_starts_with($this->cover_photo_path, '/')) {
                    return $this->cover_photo_path;
                }

                return URL::temporarySignedRoute(
                    'venues.cover',
                    now()->addHours(24),
                    ['venue' => $this->slug]
                );
            },
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'approval_status' => VenueApprovalStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return HasMany<Space, $this>
     */
    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
