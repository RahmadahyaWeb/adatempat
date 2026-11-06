<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * @property int $id
 * @property int $venue_id
 * @property int $space_id
 * @property int $user_id
 * @property string $code
 * @property Carbon $booking_date
 * @property string $start_time
 * @property string $end_time
 * @property string $customer_name
 * @property string $customer_phone
 * @property string $customer_email
 * @property string|null $notes
 * @property string $subtotal_amount
 * @property string $addons_amount
 * @property string $total_amount
 * @property BookingStatus $status
 * @property PaymentMethod $payment_method
 * @property string $qr_checkin_token
 * @property Carbon|null $expires_at
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property array<string, mixed>|null $cancellation_policy_snapshot
 * @property string $refund_amount
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'venue_id',
    'space_id',
    'user_id',
    'code',
    'booking_date',
    'start_time',
    'end_time',
    'customer_name',
    'customer_phone',
    'customer_email',
    'notes',
    'subtotal_amount',
    'addons_amount',
    'total_amount',
    'status',
    'payment_method',
    'qr_checkin_token',
    'expires_at',
    'checked_in_at',
    'cancelled_at',
    'cancelled_by',
    'cancellation_policy_snapshot',
    'refund_amount',
    'created_by',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'subtotal_amount' => 'decimal:2',
            'addons_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'status' => BookingStatus::class,
            'payment_method' => PaymentMethod::class,
            'expires_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancellation_policy_snapshot' => 'array',
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
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<BookingSlot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(BookingSlot::class);
    }

    /**
     * @return HasMany<BookingAddOn, $this>
     */
    public function addOns(): HasMany
    {
        return $this->hasMany(BookingAddOn::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<NotificationLog, $this>
     */
    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    /**
     * Generate a temporary signed URL for public ticket access without forced re-auth.
     */
    public function getSignedTicketUrl(int $daysValid = 30): string
    {
        return URL::temporarySignedRoute(
            'tickets.show',
            now()->addDays($daysValid),
            ['code' => $this->code]
        );
    }
}
