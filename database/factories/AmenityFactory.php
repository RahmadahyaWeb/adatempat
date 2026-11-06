<?php

namespace Database\Factories;

use App\Models\Amenity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Amenity>
 */
class AmenityFactory extends Factory
{
    protected $model = Amenity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'icon' => fake()->randomElement(['wifi', 'car', 'toilet', 'snowflake', 'utensils']),
            'category' => fake()->randomElement(['fasilitas', 'peralatan', 'olahraga']),
        ];
    }
}
