<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueMedia;
use Database\Seeders\CategorySeeder;
use Database\Seeders\VenueTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class VenueMediaManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
        $this->seed(VenueTemplateSeeder::class);
        Storage::fake('public');
    }

    public function test_owner_can_upload_an_allowed_image_and_first_image_becomes_cover(): void
    {
        $owner = $this->createOwner();
        [$business, $venue] = $this->createVenue($owner);

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-form', [
                'business' => $business->public_id,
                'venue' => $venue->public_id,
            ])
            ->set('mediaUpload', UploadedFile::fake()->image('venue.jpg')->size(300))
            ->set('mediaAltText', 'Tampak depan venue')
            ->call('uploadMedia')
            ->assertHasNoErrors()
            ->assertSee('Foto venue berhasil diunggah.');

        $media = $venue->media()->firstOrFail();

        $this->assertSame('public', $media->disk);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame('Tampak depan venue', $media->alt_text);
        $this->assertTrue($media->is_cover);
        $this->assertGreaterThan(0, $media->file_size_bytes);
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_owner_upload_rejects_svg_and_images_larger_than_five_megabytes(): void
    {
        $owner = $this->createOwner();
        [$business, $venue] = $this->createVenue($owner);

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-form', [
                'business' => $business->public_id,
                'venue' => $venue->public_id,
            ])
            ->set('mediaUpload', UploadedFile::fake()->create('unsafe.svg', 20, 'image/svg+xml'))
            ->set('mediaAltText', 'SVG tidak boleh')
            ->call('uploadMedia')
            ->assertHasErrors(['mediaUpload']);

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-form', [
                'business' => $business->public_id,
                'venue' => $venue->public_id,
            ])
            ->set('mediaUpload', UploadedFile::fake()->image('too-large.jpg')->size(5121))
            ->set('mediaAltText', 'File terlalu besar')
            ->call('uploadMedia')
            ->assertHasErrors(['mediaUpload']);

        $this->assertDatabaseCount('venue_media', 0);
    }

    public function test_cover_is_unique_and_deleting_cover_promotes_the_next_photo(): void
    {
        $owner = $this->createOwner();
        [$business, $venue] = $this->createVenue($owner);
        $component = Livewire::actingAs($owner)->test('pages::owner.venue-form', [
            'business' => $business->public_id,
            'venue' => $venue->public_id,
        ]);

        $component
            ->set('mediaUpload', UploadedFile::fake()->image('first.jpg')->size(200))
            ->set('mediaAltText', 'Foto pertama')
            ->call('uploadMedia')
            ->assertHasNoErrors();

        $first = $venue->media()->firstOrFail();

        $component
            ->set('mediaUpload', UploadedFile::fake()->image('second.png')->size(200))
            ->set('mediaAltText', 'Foto kedua')
            ->set('mediaIsCover', true)
            ->call('uploadMedia')
            ->assertHasNoErrors();

        $second = $venue->media()->where('alt_text', 'Foto kedua')->firstOrFail();

        $this->assertFalse($first->fresh()->is_cover);
        $this->assertTrue($second->is_cover);

        $component->call('deleteMedia', $second->getKey())->assertHasNoErrors();

        $this->assertDatabaseMissing('venue_media', ['id' => $second->getKey()]);
        $this->assertTrue($first->fresh()->is_cover);
        Storage::disk('public')->assertMissing($second->file_path);
    }

    public function test_public_business_profile_displays_the_cover_photo_alt_text(): void
    {
        $owner = $this->createOwner();
        [$business, $venue] = $this->createVenue($owner, Business::STATUS_ACTIVE, Venue::STATUS_PUBLISHED);
        $path = UploadedFile::fake()->image('cover.jpg')->storePublicly('venues/' . $venue->public_id, 'public');

        VenueMedia::query()->create([
            'venue_id' => $venue->getKey(),
            'disk' => 'public',
            'file_path' => $path,
            'mime_type' => 'image/jpeg',
            'file_size_bytes' => 300,
            'alt_text' => 'Foto sampul ruang acara',
            'is_cover' => true,
            'sort_order' => 10,
        ]);

        Livewire::test('pages::business', ['businessSlug' => $business->slug])
            ->assertSee('Foto sampul ruang acara')
            ->assertDontSee('Foto venue belum tersedia');
    }

    private function createOwner(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function createVenue(
        User $owner,
        string $businessStatus = Business::STATUS_DRAFT,
        string $venueStatus = Venue::STATUS_DRAFT,
    ): array {
        $category = Category::query()->where('slug', 'acara')->firstOrFail();
        $business = $owner->businesses()->create([
            'name' => 'Studio Foto Uji',
            'slug' => 'studio-foto-' . strtolower($businessStatus) . '-' . strtolower($venueStatus),
            'address' => 'Jalan Contoh Nomor 10',
            'status' => $businessStatus,
            'published_at' => $businessStatus === Business::STATUS_ACTIVE ? now() : null,
        ]);

        $venue = $business->venues()->create([
            'category_id' => $category->getKey(),
            'name' => 'Venue Foto Uji',
            'slug' => 'venue-foto-uji',
            'description' => 'Venue untuk menguji pengelolaan media.',
            'status' => $venueStatus,
            'published_at' => $venueStatus === Venue::STATUS_PUBLISHED ? now() : null,
        ]);

        return [$business, $venue];
    }
}
