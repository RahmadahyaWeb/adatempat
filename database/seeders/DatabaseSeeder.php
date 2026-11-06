<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'superadmin@adatempat.test'],
            [
                'name' => 'Superadmin AdaTempat',
                'password' => 'password',
                'role' => UserRole::Superadmin,
                'email_verified_at' => now(),
            ],
        );

        $owner = User::query()->firstOrCreate(
            ['email' => 'owner@adatempat.test'],
            [
                'name' => 'Owner Demo',
                'password' => 'password',
                'role' => UserRole::Owner,
                'email_verified_at' => now(),
            ],
        );

        Business::query()->firstOrCreate(
            ['user_id' => $owner->id, 'name' => 'Borneo Futsal Arena'],
            ['status' => 'active'],
        );

        User::query()->firstOrCreate(
            ['email' => 'customer@adatempat.test'],
            [
                'name' => 'Customer Demo',
                'password' => 'password',
                'role' => UserRole::Customer,
                'email_verified_at' => now(),
            ],
        );

        $this->call(CatalogSeeder::class);
    }
}
