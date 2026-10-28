<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\CategorySeeder;
use Database\Seeders\LocalUatSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Database\Seeders\VenueTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocalUatSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_uat_seeder_builds_clean_bookable_dataset(): void
    {
        Storage::fake('public');

        $this->seed(CategorySeeder::class);
        $this->seed(VenueTemplateSeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);

        $originalEnvironment = $this->app->environment();
        $this->app->instance('env', 'local');

        try {
            $this->seed(LocalUatSeeder::class);
        } finally {
            $this->app->instance('env', $originalEnvironment);
        }

        $this->assertSame(3, User::query()->whereIn('email', [
            'customer@adatempat.test',
            'owner@adatempat.test',
            'owner2@adatempat.test',
        ])->count());

        $this->assertSame(2, Business::query()->whereIn('slug', [
            'ruang-kita-banjarbaru',
            'karsa-space-banjarbaru',
        ])->count());

        $this->assertSame(4, Venue::query()->whereIn('slug', [
            'meratus-meeting-room',
            'arunika-event-hall',
            'studio-cahaya',
            'taman-senja',
        ])->count());

        $this->assertSame(4, Venue::query()->published()->count());
        $this->assertSame(2, Subscription::query()->where('status', Subscription::STATUS_ACTIVE)->count());

        $this->assertDatabaseCount('venue_availability_rules', 28);
        $this->assertDatabaseCount('venue_prices', 6);
        $this->assertDatabaseCount('venue_media', 4);

        $this->assertSame(0, Booking::query()->count());
        $this->assertSame(0, Payment::query()->count());

        Storage::disk('public')->assertExists('uat/venues/meeting.svg');
        Storage::disk('public')->assertExists('uat/venues/event.svg');
        Storage::disk('public')->assertExists('uat/venues/studio.svg');
        Storage::disk('public')->assertExists('uat/venues/outdoor.svg');
    }

    public function test_local_uat_seeder_is_skipped_outside_local_environment(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(VenueTemplateSeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);

        $this->seed(LocalUatSeeder::class);

        $this->assertDatabaseMissing('users', ['email' => 'customer@adatempat.test']);
        $this->assertDatabaseMissing('users', ['email' => 'owner@adatempat.test']);
        $this->assertDatabaseMissing('users', ['email' => 'owner2@adatempat.test']);
    }
}
