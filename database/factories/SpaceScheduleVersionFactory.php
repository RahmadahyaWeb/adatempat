<?php

namespace Database\Factories;

use App\Models\Space;
use App\Models\SpaceScheduleVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpaceScheduleVersion>
 */
class SpaceScheduleVersionFactory extends Factory
{
    protected $model = SpaceScheduleVersion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'version' => 1,
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'created_by' => User::factory(),
        ];
    }
}
