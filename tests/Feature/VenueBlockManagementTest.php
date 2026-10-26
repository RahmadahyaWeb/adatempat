<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBlock;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\VenueTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VenueBlockManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
        $this->seed(VenueTemplateSeeder::class);
    }

    public function test_owner_can_create_a_manual_block_and_times_are_stored_in_utc(): void
    {
        $owner = $this->createOwner();
        [$business, $venue] = $this->createVenue($owner, 'Asia/Makassar');

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-form', [
                'business' => $business->public_id,
                'venue' => $venue->public_id,
            ])
            ->set('blockStartsAt', '2026-10-28T09:00')
            ->set('blockEndsAt', '2026-10-28T11:30')
            ->set('blockReason', 'Perawatan ruang')
            ->call('createVenueBlock')
            ->assertHasNoErrors()
            ->assertSee('Blokir jadwal berhasil disimpan.');

        $block = $venue->blocks()->firstOrFail();

        $this->assertSame('Perawatan ruang', $block->reason);
        $this->assertSame($owner->getKey(), $block->created_by);
        $this->assertSame(
            '2026-10-28 01:00:00',
            CarbonImmutable::parse($block->starts_at)->utc()->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            '2026-10-28 03:30:00',
            CarbonImmutable::parse($block->ends_at)->utc()->format('Y-m-d H:i:s'),
        );
    }

    public function test_owner_cannot_create_a_block_with_end_before_start(): void
    {
        $owner = $this->createOwner();
        [$business, $venue] = $this->createVenue($owner);

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-form', [
                'business' => $business->public_id,
                'venue' => $venue->public_id,
            ])
            ->set('blockStartsAt', '2026-10-28T12:00')
            ->set('blockEndsAt', '2026-10-28T11:00')
            ->set('blockReason', 'Waktu tidak valid')
            ->call('createVenueBlock')
            ->assertHasErrors(['blockEndsAt']);

        $this->assertDatabaseCount('venue_blocks', 0);
    }

    public function test_owner_can_delete_a_block_belonging_to_their_venue(): void
    {
        $owner = $this->createOwner();
        [$business, $venue] = $this->createVenue($owner);
        $block = $venue->blocks()->create([
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'reason' => 'Perawatan ruang',
            'created_by' => $owner->getKey(),
        ]);

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-form', [
                'business' => $business->public_id,
                'venue' => $venue->public_id,
            ])
            ->call('deleteVenueBlock', $block->getKey())
            ->assertHasNoErrors()
            ->assertSee('Blokir jadwal berhasil dihapus.');

        $this->assertDatabaseMissing('venue_blocks', ['id' => $block->getKey()]);
    }

    public function test_owner_cannot_delete_a_block_from_another_venue(): void
    {
        $owner = $this->createOwner();
        [$business, $venue] = $this->createVenue($owner);
        $otherVenue = $business->venues()->create([
            'category_id' => $venue->category_id,
            'name' => 'Venue Kedua',
            'slug' => 'venue-kedua',
            'status' => Venue::STATUS_DRAFT,
        ]);
        $foreignBlock = $otherVenue->blocks()->create([
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'reason' => 'Blokir venue kedua',
            'created_by' => $owner->getKey(),
        ]);

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-form', [
                'business' => $business->public_id,
                'venue' => $venue->public_id,
            ])
            ->call('deleteVenueBlock', $foreignBlock->getKey())
            ->assertStatus(404);

        $this->assertDatabaseHas('venue_blocks', [
            'id' => $foreignBlock->getKey(),
            'venue_id' => $otherVenue->getKey(),
        ]);
    }

    private function createOwner(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function createVenue(User $owner, string $timezone = 'Asia/Jakarta'): array
    {
        $business = $owner->businesses()->create([
            'name' => 'Usaha Venue Blokir',
            'slug' => 'usaha-venue-blokir-' . strtolower((string) \Illuminate\Support\Str::ulid()),
            'address' => 'Jalan Contoh Nomor 10',
            'timezone' => $timezone,
            'status' => Business::STATUS_DRAFT,
            'published_at' => null,
        ]);
        $category = Category::query()->where('slug', 'acara')->firstOrFail();
        $venue = $business->venues()->create([
            'category_id' => $category->getKey(),
            'name' => 'Venue Blokir Uji',
            'slug' => 'venue-blokir-uji',
            'status' => Venue::STATUS_DRAFT,
        ]);

        return [$business, $venue];
    }
}
