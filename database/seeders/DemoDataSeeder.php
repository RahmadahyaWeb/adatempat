<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('DemoDataSeeder dilewati: data demo hanya boleh dibuat di environment local.');

            return;
        }

        $this->call(CategorySeeder::class);

        $customer = $this->seedUser(
            name: 'Pengunjung Demo',
            email: 'demo-customer@adatempat.test',
            password: 'AdatempatCustomer123!',
            role: User::ROLE_CUSTOMER,
        );

        $owner = $this->seedUser(
            name: 'Pemilik Demo',
            email: 'demo-owner@adatempat.test',
            password: 'AdatempatOwner123!',
            role: User::ROLE_OWNER,
        );

        $this->seedCustomerProfile($customer);
        $this->seedOwnerProfile($owner);

        $business = $this->seedBusiness($owner);

        $this->seedVenue(
            business: $business,
            slug: 'ruang-meeting-demo',
            name: 'Ruang Meeting Demo',
            categorySlug: 'meeting',
            description: 'Ruang meeting demo untuk rapat tim, diskusi, dan pertemuan kecil.',
        );

        $this->seedVenue(
            business: $business,
            slug: 'studio-foto-demo',
            name: 'Studio Foto Demo',
            categorySlug: 'foto',
            description: 'Studio foto demo untuk kebutuhan potret, produk, dan konten kreatif.',
        );

        $this->command?->info('Data demo Adatempat berhasil disiapkan untuk environment local.');
        $this->command?->line('Pengunjung: demo-customer@adatempat.test / AdatempatCustomer123!');
        $this->command?->line('Owner: demo-owner@adatempat.test / AdatempatOwner123!');
        $this->command?->line('Profil usaha publik: /' . $business->slug);
    }

    private function seedUser(string $name, string $email, string $password, string $role): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user !== null && $user->role !== $role) {
            throw new RuntimeException(
                "Email akun demo {$email} sudah dipakai oleh role berbeda. Seeder dihentikan."
            );
        }

        $user ??= new User();

        $user->forceFill([
            'public_id' => $user->public_id ?: (string) Str::ulid(),
            'name' => $name,
            'email' => $email,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'password' => $password,
            'role' => $role,
            'status' => 'active',
            'deactivated_at' => null,
        ])->save();

        return $user;
    }

    private function seedCustomerProfile(User $customer): void
    {
        $existing = DB::table('customer_profiles')
            ->where('user_id', $customer->getKey())
            ->first();

        DB::table('customer_profiles')->updateOrInsert(
            ['user_id' => $customer->getKey()],
            [
                'preferred_city' => 'Banjarbaru',
                'preferred_latitude' => null,
                'preferred_longitude' => null,
                'created_at' => $existing?->created_at ?? now(),
                'updated_at' => now(),
            ],
        );
    }

    private function seedOwnerProfile(User $owner): void
    {
        $existing = DB::table('owner_profiles')
            ->where('user_id', $owner->getKey())
            ->first();

        DB::table('owner_profiles')->updateOrInsert(
            ['user_id' => $owner->getKey()],
            [
                'legal_entity_name' => 'Adatempat Demo Studio',
                'identity_number_masked' => null,
                'verification_status' => 'verified',
                'rejection_reason' => null,
                'verified_by' => null,
                'verified_at' => $existing?->verified_at ?? now(),
                'created_at' => $existing?->created_at ?? now(),
                'updated_at' => now(),
            ],
        );
    }

    private function seedBusiness(User $owner): Business
    {
        $slug = 'studio-demo-adatempat';
        $business = Business::query()->where('slug', $slug)->first();

        if ($business !== null && (int) $business->owner_id !== (int) $owner->getKey()) {
            throw new RuntimeException(
                'Slug studio-demo-adatempat sudah digunakan usaha milik akun lain. Seeder tidak mengubah kepemilikannya.'
            );
        }

        $business ??= new Business();

        $business->forceFill([
            'owner_id' => $owner->getKey(),
            'name' => 'Studio Demo Adatempat',
            'slug' => $slug,
            'address' => 'Banjarbaru, Kalimantan Selatan',
            'latitude' => null,
            'longitude' => null,
            'status' => Business::STATUS_ACTIVE,
            'published_at' => $business->published_at ?? now(),
        ])->save();

        return $business;
    }

    private function seedVenue(
        Business $business,
        string $slug,
        string $name,
        string $categorySlug,
        string $description,
    ): Venue {
        $category = Category::query()
            ->where('slug', $categorySlug)
            ->where('status', Category::STATUS_ACTIVE)
            ->firstOrFail();

        $venue = $business->venues()->where('slug', $slug)->first();
        $venue ??= $business->venues()->make();

        $venue->forceFill([
            'business_id' => $business->getKey(),
            'category_id' => $category->getKey(),
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'status' => Venue::STATUS_PUBLISHED,
            'published_at' => $venue->published_at ?? now(),
        ])->save();

        return $venue;
    }
}
