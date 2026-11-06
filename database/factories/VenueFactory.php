<?php

namespace Database\Factories;

use App\Enums\VenueApprovalStatus;
use App\Models\Business;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->randomElement(['Jakarta', 'Bandung', 'Surabaya', 'Balikpapan', 'Samarinda', 'Makassar']);
        $name = fake()->company();
        $makassar = in_array($city, ['Balikpapan', 'Samarinda', 'Makassar'], true);

        return [
            'business_id' => Business::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'city' => $city,
            'address' => fake()->address(),
            'timezone' => $makassar ? 'Asia/Makassar' : 'Asia/Jakarta',
            'contact_phone' => fake()->phoneNumber(),
            'is_published' => false,
            'approval_status' => VenueApprovalStatus::Pending,
            'approved_at' => null,
            'approved_by' => null,
            'rejection_reason' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'approval_status' => VenueApprovalStatus::Approved,
            'is_published' => true,
            'approved_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Informasi tidak lengkap'): static
    {
        return $this->state(fn (): array => [
            'approval_status' => VenueApprovalStatus::Rejected,
            'is_published' => false,
            'rejection_reason' => $reason,
        ]);
    }
}
