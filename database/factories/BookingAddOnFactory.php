<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingAddOn;
use App\Models\SpaceAddOn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingAddOn>
 */
class BookingAddOnFactory extends Factory
{
    protected $model = BookingAddOn::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'space_add_on_id' => SpaceAddOn::factory(),
            'name' => 'Layanan Tambahan',
            'unit_price' => 25000,
            'quantity' => 1,
            'total_price' => 25000,
        ];
    }
}
