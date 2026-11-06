<?php

namespace Database\Seeders;

use App\Enums\AddOnPricingType;
use App\Enums\CancellationPolicy;
use App\Enums\SpaceCategory;
use App\Enums\VenueApprovalStatus;
use App\Models\Amenity;
use App\Models\User;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * Seed four demo venues. Requires DatabaseSeeder (demo users) to run first.
     */
    public function run(): void
    {
        $owner = User::query()->where('email', 'owner@adatempat.test')->firstOrFail();
        $superadmin = User::query()->where('email', 'superadmin@adatempat.test')->firstOrFail();
        $business = $owner->businesses()->firstOrCreate(
            ['name' => 'Borneo Futsal Arena'],
            ['status' => 'active'],
        );

        foreach ($this->amenityData() as $amenity) {
            Amenity::query()->firstOrCreate(['name' => $amenity['name']], $amenity);
        }

        foreach ($this->venueData() as $venueData) {
            $venue = $business->venues()->updateOrCreate(
                ['slug' => $venueData['slug']],
                [
                    'name' => $venueData['name'],
                    'city' => $venueData['city'],
                    'address' => $venueData['address'],
                    'timezone' => $venueData['timezone'],
                    'contact_phone' => $venueData['contact_phone'],
                    'is_published' => true,
                    'approval_status' => VenueApprovalStatus::Approved,
                    'approved_at' => now(),
                    'approved_by' => $superadmin->id,
                    'rejection_reason' => null,
                ],
            );

            foreach ($venueData['spaces'] as $spaceData) {
                $space = $venue->spaces()->updateOrCreate(
                    ['slug' => $spaceData['slug']],
                    [
                        'name' => $spaceData['name'],
                        'category' => $spaceData['category'],
                        'capacity' => $spaceData['capacity'],
                        'base_price' => $spaceData['base_price'],
                        'instant_book' => true,
                        'cancellation_policy' => $spaceData['cancellation_policy'],
                        'cancellation_cutoff_hours' => $spaceData['cancellation_cutoff_hours'],
                        'refund_percentage' => $spaceData['refund_percentage'],
                        'is_active' => true,
                    ],
                );

                $space->amenities()->sync(
                    Amenity::query()->whereIn('name', $spaceData['amenities'])->pluck('id'),
                );

                $version = $space->scheduleVersions()->updateOrCreate(
                    ['version' => 1],
                    [
                        'effective_from' => now()->toDateString(),
                        'effective_to' => null,
                        'created_by' => $superadmin->id,
                    ],
                );

                foreach (range(0, 6) as $dayOfWeek) {
                    $version->schedules()->updateOrCreate(
                        [
                            'space_id' => $space->id,
                            'day_of_week' => $dayOfWeek,
                            'open_time' => $spaceData['open_time'],
                        ],
                        [
                            'close_time' => $spaceData['close_time'],
                            'slot_duration_minutes' => $spaceData['slot_duration_minutes'],
                            'is_active' => true,
                        ],
                    );
                }

                foreach ($spaceData['add_ons'] as $addOn) {
                    $space->addOns()->updateOrCreate(
                        ['name' => $addOn['name']],
                        [
                            'price' => $addOn['price'],
                            'pricing_type' => $addOn['pricing_type'],
                            'stock_quantity' => $addOn['stock_quantity'],
                            'is_active' => true,
                        ],
                    );
                }
            }
        }
    }

    /**
     * @return array<int, array{name: string, icon: string, category: string}>
     */
    private function amenityData(): array
    {
        return [
            ['name' => 'WiFi', 'icon' => 'wifi', 'category' => 'fasilitas'],
            ['name' => 'Parkir', 'icon' => 'car', 'category' => 'fasilitas'],
            ['name' => 'Toilet', 'icon' => 'door-open', 'category' => 'fasilitas'],
            ['name' => 'Mushola', 'icon' => 'moon', 'category' => 'fasilitas'],
            ['name' => 'Kantin', 'icon' => 'utensils', 'category' => 'fasilitas'],
            ['name' => 'AC', 'icon' => 'snowflake', 'category' => 'fasilitas'],
            ['name' => 'Papan Skor', 'icon' => 'scoreboard', 'category' => 'olahraga'],
            ['name' => 'Proyektor', 'icon' => 'projector', 'category' => 'peralatan'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function venueData(): array
    {
        return [
            [
                'slug' => 'borneo-futsal-arena',
                'name' => 'Borneo Futsal Arena',
                'city' => 'Samarinda',
                'address' => 'Jl. Untung Suropati No. 12, Samarinda, Kalimantan Timur',
                'timezone' => 'Asia/Makassar',
                'contact_phone' => '0812-5000-1001',
                'spaces' => [
                    $this->futsalSpace('borneo-futsal-arena-lapangan-a', 'Lapangan A', 150000),
                    $this->futsalSpace('borneo-futsal-arena-lapangan-b', 'Lapangan B', 175000),
                ],
            ],
            [
                'slug' => 'smash-badminton-club',
                'name' => 'Smash Badminton Club',
                'city' => 'Balikpapan',
                'address' => 'Jl. MT Haryono No. 88, Balikpapan, Kalimantan Timur',
                'timezone' => 'Asia/Makassar',
                'contact_phone' => '0812-5000-1002',
                'spaces' => [
                    $this->badmintonSpace('smash-badminton-court-1', 'Court 1'),
                    $this->badmintonSpace('smash-badminton-court-2', 'Court 2'),
                    $this->badmintonSpace('smash-badminton-court-3', 'Court 3'),
                ],
            ],
            [
                'slug' => 'hoops-basketball-court',
                'name' => 'Hoops Basketball Court',
                'city' => 'Jakarta',
                'address' => 'Jl. Kemang Raya No. 45, Jakarta Selatan',
                'timezone' => 'Asia/Jakarta',
                'contact_phone' => '0812-5000-1003',
                'spaces' => [
                    [
                        'slug' => 'hoops-basketball-main-court',
                        'name' => 'Main Court',
                        'category' => SpaceCategory::Basketball,
                        'capacity' => 12,
                        'base_price' => 250000,
                        'cancellation_policy' => CancellationPolicy::FreeCancellation,
                        'cancellation_cutoff_hours' => 6,
                        'refund_percentage' => 100,
                        'open_time' => '08:00:00',
                        'close_time' => '22:00:00',
                        'slot_duration_minutes' => 60,
                        'amenities' => ['WiFi', 'Parkir', 'Toilet', 'Mushola', 'Kantin', 'Papan Skor'],
                        'add_ons' => [
                            [
                                'name' => 'Bola Basket',
                                'price' => 25000,
                                'pricing_type' => AddOnPricingType::PerBooking,
                                'stock_quantity' => 2,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'nusantara-meeting-space',
                'name' => 'Nusantara Meeting Space',
                'city' => 'Surabaya',
                'address' => 'Jl. Basuki Rahmat No. 101, Surabaya, Jawa Timur',
                'timezone' => 'Asia/Jakarta',
                'contact_phone' => '0812-5000-1004',
                'spaces' => [
                    $this->meetingSpace('nusantara-ruang-melati', 'Ruang Melati', 12, 100000),
                    $this->meetingSpace('nusantara-ruang-anggrek', 'Ruang Anggrek', 24, 175000),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function futsalSpace(string $slug, string $name, int $price): array
    {
        return [
            'slug' => $slug,
            'name' => $name,
            'category' => SpaceCategory::Futsal,
            'capacity' => 10,
            'base_price' => $price,
            'cancellation_policy' => CancellationPolicy::FreeCancellation,
            'cancellation_cutoff_hours' => 6,
            'refund_percentage' => 100,
            'open_time' => '08:00:00',
            'close_time' => '23:00:00',
            'slot_duration_minutes' => 60,
            'amenities' => ['WiFi', 'Parkir', 'Toilet', 'Mushola', 'Kantin', 'Papan Skor'],
            'add_ons' => [
                [
                    'name' => 'Bola Futsal',
                    'price' => 20000,
                    'pricing_type' => AddOnPricingType::PerBooking,
                    'stock_quantity' => 2,
                ],
                [
                    'name' => 'Rompi',
                    'price' => 5000,
                    'pricing_type' => AddOnPricingType::PerItem,
                    'stock_quantity' => 12,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function badmintonSpace(string $slug, string $name): array
    {
        return [
            'slug' => $slug,
            'name' => $name,
            'category' => SpaceCategory::Badminton,
            'capacity' => 4,
            'base_price' => 60000,
            'cancellation_policy' => CancellationPolicy::PartialRefund,
            'cancellation_cutoff_hours' => 6,
            'refund_percentage' => 50,
            'open_time' => '07:00:00',
            'close_time' => '22:00:00',
            'slot_duration_minutes' => 60,
            'amenities' => ['WiFi', 'Parkir', 'Toilet', 'Mushola', 'Kantin'],
            'add_ons' => [
                [
                    'name' => 'Raket',
                    'price' => 10000,
                    'pricing_type' => AddOnPricingType::PerItem,
                    'stock_quantity' => 8,
                ],
                [
                    'name' => 'Kok',
                    'price' => 15000,
                    'pricing_type' => AddOnPricingType::PerItem,
                    'stock_quantity' => 20,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function meetingSpace(string $slug, string $name, int $capacity, int $price): array
    {
        return [
            'slug' => $slug,
            'name' => $name,
            'category' => SpaceCategory::Meeting,
            'capacity' => $capacity,
            'base_price' => $price,
            'cancellation_policy' => CancellationPolicy::PartialRefund,
            'cancellation_cutoff_hours' => 24,
            'refund_percentage' => 50,
            'open_time' => '08:00:00',
            'close_time' => '18:00:00',
            'slot_duration_minutes' => 120,
            'amenities' => ['WiFi', 'Parkir', 'Toilet', 'AC', 'Proyektor'],
            'add_ons' => [
                [
                    'name' => 'Proyektor',
                    'price' => 50000,
                    'pricing_type' => AddOnPricingType::PerBooking,
                    'stock_quantity' => 1,
                ],
                [
                    'name' => 'Air Mineral',
                    'price' => 5000,
                    'pricing_type' => AddOnPricingType::PerItem,
                    'stock_quantity' => 24,
                ],
            ],
        ];
    }
}
