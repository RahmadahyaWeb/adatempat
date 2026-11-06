<?php

namespace Database\Factories;

use App\Enums\CancellationPolicy;
use App\Enums\SpaceCategory;
use App\Models\Space;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Space>
 */
class SpaceFactory extends Factory
{
    protected $model = Space::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Lapangan', 'Court', 'Studio', 'Ruang']).' '.fake()->numberBetween(1, 20);

        return [
            'venue_id' => Venue::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'category' => fake()->randomElement(SpaceCategory::cases()),
            'capacity' => fake()->numberBetween(2, 30),
            'base_price' => fake()->numberBetween(5, 50) * 10000,
            'instant_book' => true,
            'cancellation_policy' => CancellationPolicy::FreeCancellation,
            'cancellation_cutoff_hours' => 6,
            'refund_percentage' => 100,
            'is_active' => true,
        ];
    }
}
