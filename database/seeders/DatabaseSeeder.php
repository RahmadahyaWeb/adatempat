<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LegalDocumentSeeder::class,
            CategorySeeder::class,
            VenueTemplateSeeder::class,
            SubscriptionPlanSeeder::class,
            PlatformSettingSeeder::class,
            DemoDataSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
