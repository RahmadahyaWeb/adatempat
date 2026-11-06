<?php

namespace Database\Factories;

use App\Enums\AddOnPricingType;
use App\Models\Space;
use App\Models\SpaceAddOn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpaceAddOn>
 */
class SpaceAddOnFactory extends Factory
{
    protected $model = SpaceAddOn::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'name' => fake()->randomElement(['Bola', 'Rompi', 'Raket', 'Kok', 'Proyektor', 'Air Mineral']),
            'price' => fake()->numberBetween(1, 10) * 5000,
            'pricing_type' => fake()->randomElement(AddOnPricingType::cases()),
            'stock_quantity' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }
}
