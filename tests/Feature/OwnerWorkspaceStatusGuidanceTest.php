<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OwnerWorkspaceStatusGuidanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_business_prioritizes_completing_its_profile(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $business = $this->createBusiness($owner, Business::STATUS_DRAFT);

        Livewire::actingAs($owner)
            ->test('pages::owner.businesses')
            ->assertSee('Konten publik hanya tampil setelah memenuhi persyaratan publikasi.')
            ->assertSee('Kelola venue');
    }

    public function test_active_business_prioritizes_venue_management_and_explains_identity_revision(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $business = $this->createBusiness($owner, Business::STATUS_ACTIVE);
        $business->forceFill(['published_at' => now()])->save();

        Livewire::actingAs($owner)
            ->test('pages::owner.businesses')
            ->assertSee('Kelola venue')
            ->assertSee('Kelola data');

        Livewire::actingAs($owner)
            ->test('pages::owner.business-edit', ['business' => $business->public_id])
            ->assertSee('Perubahan nama atau alamat akan diajukan sebagai revisi');
    }

    public function test_venue_empty_search_has_clear_filters_action_and_can_recover_the_list(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $business = $this->createBusiness($owner, Business::STATUS_ACTIVE);
        $category = Category::query()->create([
            'name' => 'Meeting',
            'slug' => 'meeting',
            'status' => Category::STATUS_ACTIVE,
        ]);

        $business->venues()->create([
            'category_id' => $category->getKey(),
            'name' => 'Ruang Meeting Uji',
            'slug' => 'ruang-meeting-uji',
            'description' => 'Venue untuk pengujian filter.',
            'status' => Venue::STATUS_DRAFT,
        ]);

        $component = Livewire::actingAs($owner)
            ->test('pages::owner.venues', ['business' => $business->public_id])
            ->set('search', 'nama-yang-tidak-ada')
            ->set('statusFilter', Venue::STATUS_DRAFT)
            ->assertSee('Tidak ada venue yang cocok.')
            ->assertDontSee('Tambah venue pertama');

        $component
            ->set('search', '')
            ->set('statusFilter', 'all')
            ->assertSee('Ruang Meeting Uji')
            ->assertDontSee('Tidak ada venue yang cocok.');
    }

    public function test_draft_venue_explains_public_visibility_and_uses_action_specific_copy(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $business = $this->createBusiness($owner, Business::STATUS_ACTIVE);
        $category = Category::query()->create([
            'name' => 'Meeting',
            'slug' => 'meeting',
            'status' => Category::STATUS_ACTIVE,
        ]);

        $business->venues()->create([
            'category_id' => $category->getKey(),
            'name' => 'Venue Draft Uji',
            'slug' => 'venue-draft-uji',
            'description' => 'Venue draft untuk pengujian.',
            'status' => Venue::STATUS_DRAFT,
        ]);

        Livewire::actingAs($owner)
            ->test('pages::owner.venues', ['business' => $business->public_id])
            ->assertSee('Edit venue');
    }

    private function createBusiness(User $owner, string $status): Business
    {
        return $owner->businesses()->create([
            'name' => 'Studio Uji Owner',
            'slug' => 'studio-uji-owner-' . strtolower($status),
            'address' => 'Banjarbaru, Kalimantan Selatan',
            'status' => $status,
            'published_at' => $status === Business::STATUS_ACTIVE ? now() : null,
        ]);
    }
}
