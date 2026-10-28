<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Category;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TemplateField;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueAvailabilityRule;
use App\Models\VenueFieldValue;
use App\Models\VenueMedia;
use App\Models\VenuePrice;
use App\Models\VenueTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LocalUatSeeder extends Seeder
{
    private const PASSWORD = 'Adatempat123!';

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('LocalUatSeeder dilewati: hanya untuk environment local.');

            return;
        }

        $customer = $this->seedUser(
            name: 'Pelanggan UAT',
            email: 'customer@adatempat.test',
            role: User::ROLE_CUSTOMER,
        );

        $ownerOne = $this->seedUser(
            name: 'Owner Ruang Kita',
            email: 'owner@adatempat.test',
            role: User::ROLE_OWNER,
        );

        $ownerTwo = $this->seedUser(
            name: 'Owner Karsa Space',
            email: 'owner2@adatempat.test',
            role: User::ROLE_OWNER,
        );

        $this->seedCustomerProfile($customer);
        $this->seedOwnerProfile($ownerOne, 'Ruang Kita Banjarbaru');
        $this->seedOwnerProfile($ownerTwo, 'Karsa Space Banjarbaru');

        $this->seedActiveSubscription($ownerOne);
        $this->seedActiveSubscription($ownerTwo);

        $ruangKita = $this->seedBusiness(
            owner: $ownerOne,
            name: 'Ruang Kita Banjarbaru',
            slug: 'ruang-kita-banjarbaru',
            address: 'Jl. Panglima Batur, Banjarbaru Utara, Kota Banjarbaru',
            city: 'Banjarbaru',
            province: 'Kalimantan Selatan',
            latitude: -3.4422,
            longitude: 114.8327,
        );

        $karsaSpace = $this->seedBusiness(
            owner: $ownerTwo,
            name: 'Karsa Space Banjarbaru',
            slug: 'karsa-space-banjarbaru',
            address: 'Jl. A. Yani Km 34,5, Kota Banjarbaru',
            city: 'Banjarbaru',
            province: 'Kalimantan Selatan',
            latitude: -3.4495,
            longitude: 114.8391,
        );

        $meeting = $this->seedVenue(
            business: $ruangKita,
            categorySlug: 'meeting',
            name: 'Meratus Meeting Room',
            slug: 'meratus-meeting-room',
            description: 'Ruang meeting tenang untuk rapat tim, presentasi, workshop kecil, dan sesi diskusi dengan fasilitas kerja lengkap.',
            capacity: 24,
            facilities: ['Wi-Fi', 'AC', 'Parkir', 'Toilet', 'Proyektor', 'Papan tulis'],
            refundPolicy: $this->defaultRefundPolicy(),
        );

        $this->seedAvailability($meeting, [
            0 => [true, '09:00', '18:00'],
            1 => [true, '08:00', '22:00'],
            2 => [true, '08:00', '22:00'],
            3 => [true, '08:00', '22:00'],
            4 => [true, '08:00', '22:00'],
            5 => [true, '08:00', '22:00'],
            6 => [true, '08:00', '22:00'],
        ], 15);

        $meeting->prices()->create([
            'pricing_unit' => VenuePrice::UNIT_HOURLY,
            'step_interval_minutes' => 60,
            'min_duration_units' => 1,
            'max_duration_units' => 8,
            'price_amount' => 150000,
            'weekend_price_amount' => 175000,
            'is_active' => true,
        ]);

        $this->seedTemplateValues($meeting, [
            'projector_available' => true,
            'whiteboard_available' => true,
            'meeting_layout' => 'Boardroom',
        ]);

        $eventHall = $this->seedVenue(
            business: $ruangKita,
            categorySlug: 'acara',
            name: 'Arunika Event Hall',
            slug: 'arunika-event-hall',
            description: 'Aula fleksibel untuk gathering, ulang tahun, seminar, lamaran, dan acara komunitas dengan area parkir yang memadai.',
            capacity: 180,
            facilities: ['AC', 'Parkir', 'Toilet', 'Mushola', 'Ruang ganti', 'Sound system'],
            refundPolicy: $this->defaultRefundPolicy(),
        );

        $this->seedAvailability($eventHall, array_fill(0, 7, [true, '08:00', '23:00']), 30);

        $eventHall->prices()->create([
            'pricing_unit' => VenuePrice::UNIT_HOURLY,
            'step_interval_minutes' => 60,
            'min_duration_units' => 4,
            'max_duration_units' => 12,
            'price_amount' => 500000,
            'weekend_price_amount' => 600000,
            'is_active' => true,
        ]);

        $this->seedTemplateValues($eventHall, [
            'stage_type' => 'Fleksibel',
            'sound_system' => true,
            'parking_spaces' => 45,
        ]);

        $photoStudio = $this->seedVenue(
            business: $karsaSpace,
            categorySlug: 'foto',
            name: 'Studio Cahaya',
            slug: 'studio-cahaya',
            description: 'Studio foto indoor untuk portrait, produk, keluarga, dan konten dengan pilihan backdrop serta pencahayaan yang fleksibel.',
            capacity: 12,
            facilities: ['Wi-Fi', 'AC', 'Parkir', 'Toilet', 'Ruang ganti'],
            refundPolicy: $this->defaultRefundPolicy(),
        );

        $this->seedAvailability($photoStudio, [
            0 => [true, '09:00', '20:00'],
            1 => [false, '09:00', '20:00'],
            2 => [true, '09:00', '20:00'],
            3 => [true, '09:00', '20:00'],
            4 => [true, '09:00', '20:00'],
            5 => [true, '09:00', '20:00'],
            6 => [true, '09:00', '20:00'],
        ], 15);

        $photoStudio->prices()->createMany([
            [
                'pricing_unit' => VenuePrice::UNIT_SESSION,
                'session_name' => 'Sesi Pagi',
                'session_start_time' => '09:00',
                'session_end_time' => '12:00',
                'step_interval_minutes' => 60,
                'min_duration_units' => 1,
                'max_duration_units' => 1,
                'price_amount' => 450000,
                'weekend_price_amount' => 500000,
                'is_active' => true,
            ],
            [
                'pricing_unit' => VenuePrice::UNIT_SESSION,
                'session_name' => 'Sesi Siang',
                'session_start_time' => '12:00',
                'session_end_time' => '15:00',
                'step_interval_minutes' => 60,
                'min_duration_units' => 1,
                'max_duration_units' => 1,
                'price_amount' => 450000,
                'weekend_price_amount' => 500000,
                'is_active' => true,
            ],
            [
                'pricing_unit' => VenuePrice::UNIT_SESSION,
                'session_name' => 'Sesi Sore',
                'session_start_time' => '15:00',
                'session_end_time' => '18:00',
                'step_interval_minutes' => 60,
                'min_duration_units' => 1,
                'max_duration_units' => 1,
                'price_amount' => 500000,
                'weekend_price_amount' => 550000,
                'is_active' => true,
            ],
        ]);

        $this->seedTemplateValues($photoStudio, [
            'natural_light' => true,
            'backdrop_colors' => ['Putih', 'Hitam', 'Abu-abu'],
            'power_outlets' => 8,
        ]);

        $outdoor = $this->seedVenue(
            business: $karsaSpace,
            categorySlug: 'outdoor',
            name: 'Taman Senja',
            slug: 'taman-senja',
            description: 'Area outdoor hijau untuk intimate event, gathering, mini wedding, dan aktivitas komunitas dengan area beratap cadangan.',
            capacity: 120,
            facilities: ['Parkir', 'Toilet', 'Mushola', 'Area beratap'],
            refundPolicy: $this->defaultRefundPolicy(),
        );

        $this->seedAvailability($outdoor, array_fill(0, 7, [true, '07:00', '22:00']), 30);

        $outdoor->prices()->create([
            'pricing_unit' => VenuePrice::UNIT_HOURLY,
            'step_interval_minutes' => 60,
            'min_duration_units' => 3,
            'max_duration_units' => 12,
            'price_amount' => 350000,
            'weekend_price_amount' => 425000,
            'is_active' => true,
        ]);

        $this->seedTemplateValues($outdoor, [
            'surface_type' => 'Rumput',
            'covered_area' => true,
            'noise_restrictions' => 'Penggunaan sound system maksimal pukul 21.00.',
        ]);

        $this->seedMedia($meeting, 'meeting', '#335c42', '#d8ff63');
        $this->seedMedia($eventHall, 'event', '#254b34', '#f4e5bc');
        $this->seedMedia($photoStudio, 'studio', '#4a4a43', '#f1d7b6');
        $this->seedMedia($outdoor, 'outdoor', '#365b39', '#d7e7c7');

        $this->command?->newLine();
        $this->command?->info('Local UAT Adatempat siap digunakan.');
        $this->command?->line('Customer : customer@adatempat.test / ' . self::PASSWORD);
        $this->command?->line('Owner 1  : owner@adatempat.test / ' . self::PASSWORD);
        $this->command?->line('Owner 2  : owner2@adatempat.test / ' . self::PASSWORD);
        $this->command?->line('Public   : /ruang-kita-banjarbaru');
        $this->command?->line('Public   : /karsa-space-banjarbaru');
        $this->command?->line('Data transaksi sengaja kosong agar booking dapat diuji dari kondisi fresh.');
    }

    private function seedUser(string $name, string $email, string $role): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->forceFill([
            'public_id' => $user->public_id ?: (string) Str::ulid(),
            'name' => $name,
            'email_verified_at' => $user->email_verified_at ?? now('UTC'),
            'password' => self::PASSWORD,
            'role' => $role,
            'status' => 'active',
            'deactivated_at' => null,
        ])->save();

        return $user;
    }

    private function seedCustomerProfile(User $customer): void
    {
        DB::table('customer_profiles')->updateOrInsert(
            ['user_id' => $customer->getKey()],
            [
                'preferred_city' => 'Banjarbaru',
                'preferred_latitude' => -3.4422,
                'preferred_longitude' => 114.8327,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function seedOwnerProfile(User $owner, string $legalEntityName): void
    {
        DB::table('owner_profiles')->updateOrInsert(
            ['user_id' => $owner->getKey()],
            [
                'legal_entity_name' => $legalEntityName,
                'identity_number_masked' => '63**********01',
                'verification_status' => 'verified',
                'rejection_reason' => null,
                'verified_by' => null,
                'verified_at' => now('UTC'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function seedActiveSubscription(User $owner): void
    {
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        $now = now('UTC');

        Subscription::query()->updateOrCreate(
            ['owner_id' => $owner->getKey()],
            [
                'plan_id' => $plan->getKey(),
                'scheduled_plan_id' => null,
                'retained_business_id' => null,
                'is_trial' => false,
                'status' => Subscription::STATUS_ACTIVE,
                'starts_at' => $now->copy()->subDays(2),
                'ends_at' => $now->copy()->addDays(28),
                'grace_ends_at' => $now->copy()->addDays(29),
                'suspended_at' => null,
                'reverified_by' => null,
            ],
        );
    }

    private function seedBusiness(
        User $owner,
        string $name,
        string $slug,
        string $address,
        string $city,
        string $province,
        float $latitude,
        float $longitude,
    ): Business {
        return Business::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'owner_id' => $owner->getKey(),
                'name' => $name,
                'address' => $address,
                'city' => $city,
                'province' => $province,
                'timezone' => 'Asia/Makassar',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'status' => Business::STATUS_ACTIVE,
                'rejection_reason' => null,
                'verification_submitted_at' => now('UTC')->subDays(5),
                'verification_reviewed_at' => now('UTC')->subDays(4),
                'verification_reviewed_by' => null,
                'verified_at' => now('UTC')->subDays(4),
                'verified_by' => null,
                'published_at' => now('UTC')->subDays(4),
            ],
        );
    }

    private function seedVenue(
        Business $business,
        string $categorySlug,
        string $name,
        string $slug,
        string $description,
        int $capacity,
        array $facilities,
        array $refundPolicy,
    ): Venue {
        $category = Category::query()
            ->where('slug', $categorySlug)
            ->where('status', Category::STATUS_ACTIVE)
            ->firstOrFail();

        $template = VenueTemplate::query()
            ->where('category_id', $category->getKey())
            ->where('is_active', true)
            ->orderByDesc('version')
            ->firstOrFail();

        return Venue::query()->updateOrCreate(
            [
                'business_id' => $business->getKey(),
                'slug' => $slug,
            ],
            [
                'category_id' => $category->getKey(),
                'venue_template_id' => $template->getKey(),
                'name' => $name,
                'description' => $description,
                'capacity_persons' => $capacity,
                'facilities_json' => $facilities,
                'refund_policy_json' => $refundPolicy,
                'status' => Venue::STATUS_PUBLISHED,
                'published_at' => now('UTC')->subDays(3),
            ],
        );
    }

    private function seedAvailability(Venue $venue, array $rules, int $bufferMinutes): void
    {
        foreach ($rules as $day => [$isOpen, $openTime, $closeTime]) {
            VenueAvailabilityRule::query()->updateOrCreate(
                [
                    'venue_id' => $venue->getKey(),
                    'day_of_week' => $day,
                ],
                [
                    'is_open' => $isOpen,
                    'open_time' => $openTime,
                    'close_time' => $closeTime,
                    'buffer_minutes' => $bufferMinutes,
                ],
            );
        }
    }

    private function seedTemplateValues(Venue $venue, array $values): void
    {
        $fields = TemplateField::query()
            ->where('venue_template_id', $venue->venue_template_id)
            ->whereIn('field_key', array_keys($values))
            ->get()
            ->keyBy('field_key');

        foreach ($values as $key => $value) {
            $field = $fields->get($key);

            if (! $field) {
                continue;
            }

            $payload = [
                'value_text' => null,
                'value_number' => null,
                'value_boolean' => null,
                'value_json' => null,
            ];

            match ($field->input_type) {
                TemplateField::TYPE_BOOLEAN => $payload['value_boolean'] = (bool) $value,
                TemplateField::TYPE_NUMBER => $payload['value_number'] = (float) $value,
                TemplateField::TYPE_MULTISELECT => $payload['value_json'] = array_values((array) $value),
                default => $payload['value_text'] = (string) $value,
            };

            VenueFieldValue::query()->updateOrCreate(
                [
                    'venue_id' => $venue->getKey(),
                    'template_field_id' => $field->getKey(),
                ],
                $payload,
            );
        }
    }

    private function seedMedia(Venue $venue, string $key, string $background, string $accent): void
    {
        $path = "uat/venues/{$key}.svg";
        $svg = $this->venueSvg($venue->name, $background, $accent);

        Storage::disk('public')->put($path, $svg);

        VenueMedia::query()->updateOrCreate(
            [
                'venue_id' => $venue->getKey(),
                'file_path' => $path,
            ],
            [
                'disk' => 'public',
                'mime_type' => 'image/svg+xml',
                'file_size_bytes' => strlen($svg),
                'alt_text' => 'Ilustrasi placeholder ' . $venue->name,
                'is_cover' => true,
                'sort_order' => 10,
            ],
        );
    }

    private function defaultRefundPolicy(): array
    {
        return [
            'source' => 'uat_seed',
            'version' => 1,
            'customer_cancellation' => [
                'minimum_minutes_before_start' => 60,
                'refund_percentage' => 100,
            ],
            'owner_cancellation' => [
                'refund_percentage' => 100,
            ],
        ];
    }

    private function venueSvg(string $name, string $background, string $accent): string
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_XML1);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="1000" viewBox="0 0 1600 1000">
  <rect width="1600" height="1000" fill="{$background}"/>
  <rect x="110" y="120" width="1380" height="760" rx="36" fill="none" stroke="{$accent}" stroke-width="4" opacity=".72"/>
  <circle cx="1320" cy="260" r="150" fill="{$accent}" opacity=".18"/>
  <path d="M230 710h1140M300 710V390h420v320M850 710V310h450v400" fill="none" stroke="{$accent}" stroke-width="18" stroke-linecap="round" opacity=".8"/>
  <text x="160" y="210" fill="{$accent}" font-size="52" font-family="Arial, Helvetica, sans-serif" font-weight="700">adatempat</text>
  <text x="160" y="815" fill="white" font-size="76" font-family="Arial, Helvetica, sans-serif" font-weight="700">{$safeName}</text>
  <text x="160" y="870" fill="white" opacity=".68" font-size="28" font-family="Arial, Helvetica, sans-serif">Local UAT preview</text>
</svg>
SVG;
    }
}
