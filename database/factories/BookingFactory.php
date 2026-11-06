<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Space;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'space_id' => function (array $attributes) {
                return Space::factory()->create([
                    'venue_id' => $attributes['venue_id'],
                ])->id;
            },
            'user_id' => User::factory(),
            'code' => 'ADT-BK-'.now()->format('Ymd').'-'.strtoupper(Str::random(5)),
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '19:00',
            'end_time' => '21:00',
            'customer_name' => fake()->name(),
            'customer_phone' => '0812'.fake()->numerify('########'),
            'customer_email' => fake()->safeEmail(),
            'notes' => null,
            'subtotal_amount' => 200000,
            'addons_amount' => 0,
            'total_amount' => 200000,
            'status' => BookingStatus::PendingPayment,
            'payment_method' => PaymentMethod::Qris,
            'qr_checkin_token' => Str::random(64),
            'expires_at' => now()->addMinutes(10),
            'cancellation_policy_snapshot' => [
                'policy' => 'free_cancellation',
                'cutoff_hours' => 6,
                'refund_percentage' => 100,
            ],
            'refund_amount' => 0,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Confirmed,
            'expires_at' => null,
        ]);
    }

    public function checkedIn(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::CheckedIn,
            'checked_in_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
