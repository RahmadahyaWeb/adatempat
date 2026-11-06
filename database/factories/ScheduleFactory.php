<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\Space;
use App\Models\SpaceScheduleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'schedule_version_id' => SpaceScheduleVersion::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'open_time' => '08:00:00',
            'close_time' => '22:00:00',
            'slot_duration_minutes' => 60,
            'is_active' => true,
        ];
    }
}
