<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\OwnerCustomField;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueFieldValue;
use App\Models\VenueTemplate;
use Database\Seeders\CategorySeeder;
use Database\Seeders\VenueTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OwnerCustomVenueFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
        $this->seed(VenueTemplateSeeder::class);
    }

    public function test_owner_can_create_a_custom_field_and_save_its_typed_value(): void
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
            ->set('name', 'Ruang Meeting Custom')
            ->set('categoryId', (string) $category->getKey())
            ->set('venueTemplateId', (string) $template->getKey())
            ->set('templateValues', [
                'projector_available' => true,
                'whiteboard_available' => false,
                'meeting_layout' => 'Classroom',
            ])
            ->set('customFields', [[
                'id' => null,
                'field_key' => '',
                'label' => 'Jam Operasional',
                'input_type' => OwnerCustomField::TYPE_TEXT,
                'options_text' => '',
                'sort_order' => 10,
            ]])
            ->set('customFieldValues', ['Sampai 22.00'])
            ->call('save')
            ->assertHasNoErrors();

        $venue = $business->venues()->where('name', 'Ruang Meeting Custom')->firstOrFail();
        $customField = $venue->ownerCustomFields()->where('field_key', 'jam_operasional')->firstOrFail();

        $this->assertSame('Jam Operasional', $customField->label);
        $this->assertSame(OwnerCustomField::TYPE_TEXT, $customField->input_type);
        $this->assertDatabaseHas('venue_field_values', [
            'venue_id' => $venue->getKey(),
            'template_field_id' => null,
            'owner_custom_field_id' => $customField->getKey(),
            'value_text' => 'Sampai 22.00',
        ]);
    }

    public function test_owner_cannot_save_a_select_value_outside_the_custom_field_options(): void
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
            ->set('name', 'Ruang Meeting Opsi Tidak Valid')
            ->set('categoryId', (string) $category->getKey())
            ->set('venueTemplateId', (string) $template->getKey())
            ->set('templateValues', [
                'projector_available' => true,
                'whiteboard_available' => false,
                'meeting_layout' => 'Classroom',
            ])
            ->set('customFields', [[
                'id' => null,
                'field_key' => '',
                'label' => 'Waktu Kunjungan',
                'input_type' => OwnerCustomField::TYPE_SELECT,
                'options_text' => "Pagi\nMalam",
                'sort_order' => 10,
            ]])
            ->set('customFieldValues', ['Sore'])
            ->call('save')
            ->assertHasErrors(['customFieldValues.0']);

        $this->assertDatabaseMissing('venues', [
            'business_id' => $business->getKey(),
            'name' => 'Ruang Meeting Opsi Tidak Valid',
        ]);
    }

    public function test_public_profile_shows_active_custom_fields_and_hides_fields_hidden_by_admin(): void
    {
        $owner = $this->createOwner();
        $business = $this->createBusiness($owner, Business::STATUS_ACTIVE);
        $business->forceFill(['published_at' => now()])->save();
        $category = Category::query()->where('slug', 'meeting')->firstOrFail();

        $venue = $business->venues()->create([
            'category_id' => $category->getKey(),
            'name' => 'Ruang Custom Publik',
            'slug' => 'ruang-custom-publik',
            'description' => 'Venue dengan custom field.',
            'status' => Venue::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $activeField = $venue->ownerCustomFields()->create([
            'field_key' => 'jam_operasional',
            'label' => 'Jam operasional',
            'input_type' => OwnerCustomField::TYPE_TEXT,
            'options_json' => null,
            'moderation_status' => OwnerCustomField::STATUS_ACTIVE,
            'sort_order' => 10,
        ]);

        $hiddenField = $venue->ownerCustomFields()->create([
            'field_key' => 'catatan_internal',
            'label' => 'Catatan internal',
            'input_type' => OwnerCustomField::TYPE_TEXT,
            'options_json' => null,
            'moderation_status' => OwnerCustomField::STATUS_HIDDEN_BY_ADMIN,
            'moderation_reason' => 'Informasi tidak layak ditampilkan.',
            'sort_order' => 20,
        ]);

        VenueFieldValue::query()->create([
            'venue_id' => $venue->getKey(),
            'owner_custom_field_id' => $activeField->getKey(),
            'value_text' => 'Sampai 22.00',
        ]);

        VenueFieldValue::query()->create([
            'venue_id' => $venue->getKey(),
            'owner_custom_field_id' => $hiddenField->getKey(),
            'value_text' => 'Jangan tampilkan ini',
        ]);

        Livewire::test('pages::business', ['businessSlug' => $business->slug])
            ->assertSee('Ruang Custom Publik')
            ->assertSee('Jam operasional')
            ->assertSee('Sampai 22.00')
            ->assertDontSee('Catatan internal')
            ->assertDontSee('Jangan tampilkan ini');
    }

    public function test_owner_cannot_attach_a_custom_field_from_another_venues_tenant(): void
    {
        $owner = $this->createOwner();
        $otherOwner = $this->createOwner();
        $business = $this->createBusiness($owner);
        $otherBusiness = $otherOwner->businesses()->create([
            'name' => 'Studio Custom Pemilik Lain',
            'slug' => 'studio-custom-pemilik-lain',
            'address' => 'Jalan Lain',
            'status' => Business::STATUS_DRAFT,
            'published_at' => null,
        ]);
        $category = Category::query()->where('slug', 'meeting')->firstOrFail();
        $template = VenueTemplate::query()
            ->where('category_id', $category->getKey())
            ->where('is_active', true)
            ->firstOrFail();

        $venue = $business->venues()->create([
            'category_id' => $category->getKey(),
            'venue_template_id' => $template->getKey(),
            'name' => 'Venue Milik Saya',
            'slug' => 'venue-milik-saya',
            'status' => Venue::STATUS_DRAFT,
        ]);
        $otherVenue = $otherBusiness->venues()->create([
            'category_id' => $category->getKey(),
            'venue_template_id' => $template->getKey(),
            'name' => 'Venue Pemilik Lain',
            'slug' => 'venue-pemilik-lain',
            'status' => Venue::STATUS_DRAFT,
        ]);
        $foreignField = $otherVenue->ownerCustomFields()->create([
            'field_key' => 'catatan_lain',
            'label' => 'Catatan lain',
            'input_type' => OwnerCustomField::TYPE_TEXT,
            'moderation_status' => OwnerCustomField::STATUS_ACTIVE,
            'sort_order' => 10,
        ]);

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-form', [
                'business' => $business->public_id,
                'venue' => $venue->public_id,
            ])
            ->set('customFields', [[
                'id' => $foreignField->getKey(),
                'field_key' => $foreignField->field_key,
                'label' => $foreignField->label,
                'input_type' => OwnerCustomField::TYPE_TEXT,
                'options_text' => '',
                'sort_order' => 10,
            ]])
            ->set('customFieldValues', ['Tidak boleh disalin'])
            ->call('save')
            ->assertHasErrors(['customFields.0.id']);

        $this->assertDatabaseHas('owner_custom_fields', [
            'id' => $foreignField->getKey(),
            'venue_id' => $otherVenue->getKey(),
            'label' => 'Catatan lain',
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

    private function createBusiness(User $owner, string $status = Business::STATUS_DRAFT): Business
    {
        return $owner->businesses()->create([
            'name' => 'Studio Custom Uji',
            'slug' => 'studio-custom-' . strtolower($status),
            'address' => 'Jalan Contoh Nomor 10',
            'status' => $status,
            'published_at' => null,
        ]);
    }
}
