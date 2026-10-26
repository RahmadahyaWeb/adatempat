<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\TemplateField;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueFieldValue;
use App\Models\VenueTemplate;
use Database\Seeders\CategorySeeder;
use Database\Seeders\VenueTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VenueTemplateDynamicFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
        $this->seed(VenueTemplateSeeder::class);
    }

    public function test_owner_can_save_capacity_facilities_and_category_template_values(): void
    {
        $owner = $this->createOwner();
        $business = $this->createBusiness($owner);
        $category = Category::query()->where('slug', 'meeting')->firstOrFail();
        $template = VenueTemplate::query()
            ->with('fields')
            ->where('category_id', $category->getKey())
            ->where('is_active', true)
            ->firstOrFail();

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-form', ['business' => $business->public_id])
            ->set('name', 'Ruang Meeting Dinamis')
            ->set('categoryId', (string) $category->getKey())
            ->set('venueTemplateId', (string) $template->getKey())
            ->set('description', 'Ruang meeting dengan informasi kategori.')
            ->set('capacityPersons', '50')
            ->set('facilities', ['Wi-Fi', 'AC'])
            ->set('templateValues', [
                'projector_available' => true,
                'whiteboard_available' => false,
                'meeting_layout' => 'Classroom',
            ])
            ->call('save')
            ->assertHasNoErrors();

        $venue = $business->venues()->where('name', 'Ruang Meeting Dinamis')->firstOrFail();

        $this->assertSame($template->getKey(), $venue->venue_template_id);
        $this->assertSame(50, $venue->capacity_persons);
        $this->assertSame(['Wi-Fi', 'AC'], $venue->facilities_json);
        $this->assertSame(Venue::STATUS_DRAFT, $venue->status);

        $projectorField = $template->fields->firstWhere('field_key', 'projector_available');
        $layoutField = $template->fields->firstWhere('field_key', 'meeting_layout');

        $this->assertNotNull($projectorField);
        $this->assertNotNull($layoutField);

        $this->assertDatabaseHas('venue_field_values', [
            'venue_id' => $venue->getKey(),
            'template_field_id' => $projectorField->getKey(),
            'value_boolean' => 1,
        ]);

        $this->assertDatabaseHas('venue_field_values', [
            'venue_id' => $venue->getKey(),
            'template_field_id' => $layoutField->getKey(),
            'value_text' => 'Classroom',
        ]);
    }

    public function test_owner_cannot_save_an_option_that_is_not_defined_by_the_template(): void
    {
        $owner = $this->createOwner();
        $business = $this->createBusiness($owner);
        $category = Category::query()->where('slug', 'meeting')->firstOrFail();
        $template = VenueTemplate::query()
            ->where('category_id', $category->getKey())
            ->where('is_active', true)
            ->firstOrFail();

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-form', ['business' => $business->public_id])
            ->set('name', 'Ruang Meeting Tidak Valid')
            ->set('categoryId', (string) $category->getKey())
            ->set('venueTemplateId', (string) $template->getKey())
            ->set('templateValues', [
                'projector_available' => true,
                'whiteboard_available' => false,
                'meeting_layout' => '<script>alert(1)</script>',
            ])
            ->call('save')
            ->assertHasErrors(['templateValues.meeting_layout']);

        $this->assertDatabaseMissing('venues', [
            'business_id' => $business->getKey(),
            'name' => 'Ruang Meeting Tidak Valid',
        ]);
    }

    public function test_public_business_profile_displays_capacity_facilities_and_dynamic_template_values(): void
    {
        $owner = $this->createOwner();
        $business = $this->createBusiness($owner, Business::STATUS_ACTIVE);
        $business->forceFill(['published_at' => now()])->save();

        $category = Category::query()->where('slug', 'meeting')->firstOrFail();
        $template = VenueTemplate::query()
            ->with('fields')
            ->where('category_id', $category->getKey())
            ->where('is_active', true)
            ->firstOrFail();

        $venue = $business->venues()->create([
            'category_id' => $category->getKey(),
            'venue_template_id' => $template->getKey(),
            'name' => 'Ruang Publik Dinamis',
            'slug' => 'ruang-publik-dinamis',
            'description' => 'Venue publik yang memiliki detail kategori.',
            'status' => Venue::STATUS_PUBLISHED,
            'published_at' => now(),
            'capacity_persons' => 50,
            'facilities_json' => ['Wi-Fi', 'AC'],
        ]);

        $projectorField = $template->fields->firstWhere('field_key', 'projector_available');
        $layoutField = $template->fields->firstWhere('field_key', 'meeting_layout');

        VenueFieldValue::query()->create([
            'venue_id' => $venue->getKey(),
            'template_field_id' => $projectorField->getKey(),
            'value_boolean' => true,
        ]);
        VenueFieldValue::query()->create([
            'venue_id' => $venue->getKey(),
            'template_field_id' => $layoutField->getKey(),
            'value_text' => 'Classroom',
        ]);

        Livewire::test('pages::business', ['businessSlug' => $business->slug])
            ->assertSee('Ruang Publik Dinamis')
            ->assertSee('Kapasitas: 50 orang')
            ->assertSee('Wi-Fi')
            ->assertSee('AC')
            ->assertSee('Proyektor tersedia')
            ->assertSee('Classroom');
    }

    private function createOwner(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function createBusiness(User $owner, string $status = Business::STATUS_DRAFT): Business
    {
        return $owner->businesses()->create([
            'name' => 'Studio Dinamis Uji',
            'slug' => 'studio-dinamis-' . strtolower($status) . '-' . str()->random(8),
            'address' => 'Jalan Contoh Nomor 10',
            'status' => $status,
            'published_at' => null,
        ]);
    }
}
