<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CheckInStatus;
use App\Enums\PaymentStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $booking_code
 * @property int $user_id
 * @property int $venue_id
 * @property int $total_amount
 * @property PaymentStatus $payment_status
 * @property CheckInStatus $check_in_status
 * @property string|null $qr_token
 * @property string|null $midtrans_transaction_id
 * @property string|null $qris_url
 * @property CarbonInterface|null $qris_expires_at
 * @property CarbonInterface|null $paid_at
 * @property CarbonInterface|null $checked_in_at
 * @property int|null $checked_in_by
 * @property CarbonInterface|null $rescheduled_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read User $user
 * @property-read Venue $venue
 * @property-read User|null $checkedInBy
 * @property-read Collection<int, BookingItem> $items
 */
class Booking extends Model
{
    use HasFactory;

    /**
     * Default model attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'payment_status' => PaymentStatus::Pending->value,
        'check_in_status' => CheckInStatus::Unclaimed->value,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'booking_code',
        'user_id',
        'venue_id',
        'total_amount',
        'payment_status',
        'check_in_status',
        'qr_token',
        'midtrans_transaction_id',
        'qris_url',
        'qris_expires_at',
        'paid_at',
        'checked_in_at',
        'checked_in_by',
        'rescheduled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
            'payment_status' => PaymentStatus::class,
            'check_in_status' => CheckInStatus::class,
            'qris_expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'rescheduled_at' => 'datetime',
        ];
    }

    /**
     * Player who placed this booking.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Venue hosting this booking.
     *
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * Operator staff who verified attendance.
     *
     * @return BelongsTo<User, $this>
     */
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    /**
     * Locked-price slot line items.
     *
     * @return HasMany<BookingItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /**
     * Gateway payment records.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Determine if the digital ticket details and QR token can be revealed.
     */
    public function canRevealTicket(): bool
    {
        return in_array($this->payment_status, [
            PaymentStatus::Paid,
            PaymentStatus::Rescheduled,
            PaymentStatus::Completed,
        ], true);
    }

    /**
     * Generate unique booking code formatted as ADT-YYYYMMDD-XXXX.
     */
    public static function generateBookingCode(?CarbonInterface $referenceTime = null): string
    {
        $dateSegment = ($referenceTime ?? now())->format('Ymd');
        $randomSegment = strtoupper(Str::random(4));

        return "ADT-{$dateSegment}-{$randomSegment}";
    }

    /**
     * Generate secure 64-character cryptographic QR verification token.
     */
    public static function generateQrToken(): string
    {
        return hash('sha256', Str::random(40).microtime(true).Str::random(20));
    }
}
