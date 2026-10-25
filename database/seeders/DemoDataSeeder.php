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

        $secondOwner = $this->seedUser(
            name: 'Pemilik Demo Dua',
            email: 'demo-owner-2@adatempat.test',
            password: 'AdatempatOwner2Demo123!',
            role: User::ROLE_OWNER,
        );

        $this->seedCustomerProfile($customer);
        $this->seedOwnerProfile($owner, 'Adatempat Demo Studio');
        $this->seedOwnerProfile($secondOwner, 'Studio Demo Pemilik Dua');

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

        $secondBusiness = $this->seedBusiness(
            owner: $secondOwner,
            slug: 'studio-demo-owner-dua',
            name: 'Studio Demo Pemilik Dua',
            address: 'Banjarbaru, Kalimantan Selatan',
        );

        $this->seedVenue(
            business: $secondBusiness,
            slug: 'ruang-meeting-owner-dua',
            name: 'Ruang Meeting Pemilik Dua',
            categorySlug: 'meeting',
            description: 'Venue demo milik owner kedua untuk pengujian isolasi kepemilikan.',
        );

        $this->command?->info('Data demo Adatempat berhasil disiapkan untuk environment local.');
        $this->command?->line('Pengunjung: demo-customer@adatempat.test / AdatempatCustomer123!');
        $this->command?->line('Owner pertama: demo-owner@adatempat.test / AdatempatOwner123!');
        $this->command?->line('Owner kedua: demo-owner-2@adatempat.test / AdatempatOwner2Demo123!');
        $this->command?->line('Profil usaha publik owner pertama: /' . $business->slug);
        $this->command?->line('Profil usaha publik owner kedua: /' . $secondBusiness->slug);
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

    private function seedOwnerProfile(User $owner, string $legalEntityName): void
    {
        $existing = DB::table('owner_profiles')
            ->where('user_id', $owner->getKey())
            ->first();

        DB::table('owner_profiles')->updateOrInsert(
            ['user_id' => $owner->getKey()],
            [
                'legal_entity_name' => $legalEntityName,
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

    private function seedBusiness(
        User $owner,
        string $slug = 'studio-demo-adatempat',
        string $name = 'Studio Demo Adatempat',
        string $address = 'Banjarbaru, Kalimantan Selatan',
    ): Business
    {
        $business = Business::query()->where('slug', $slug)->first();

        if ($business !== null && (int) $business->owner_id !== (int) $owner->getKey()) {
            throw new RuntimeException(
                "Slug {$slug} sudah digunakan usaha milik akun lain. Seeder tidak mengubah kepemilikannya."
            );
        }

        $business ??= new Business();

        $business->forceFill([
            'owner_id' => $owner->getKey(),
            'name' => $name,
            'slug' => $slug,
            'address' => $address,
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
