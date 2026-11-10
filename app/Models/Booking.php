<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CheckInStatus;
use App\Enums\PaymentStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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
