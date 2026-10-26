<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\TemplateField;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueTemplate;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminVenueTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
    }

    public function test_super_admin_can_create_a_category_template_and_audited_field_definition(): void
    {
        $admin = $this->createAdmin();
        $category = Category::query()->where('slug', 'meeting')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('pages::admin.venue-templates')
            ->call('startCreate')
            ->set('name', 'Template Meeting Uji')
            ->set('categoryId', (string) $category->getKey())
            ->set('isActive', true)
            ->set('fields', [
                [
                    'field_key' => 'room_layout',
                    'label' => 'Tata ruang',
                    'input_type' => TemplateField::TYPE_SELECT,
                    'unit_label' => '',
                    'options_text' => "Classroom\nBoardroom",
                    'is_required' => true,
                    'is_filterable' => true,
                    'sort_order' => 10,
                    'min' => '',
                    'max' => '',
                    'max_length' => '',
                ],
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Template berhasil disimpan sebagai versi baru');

        $template = VenueTemplate::query()
            ->where('category_id', $category->getKey())
            ->where('name', 'Template Meeting Uji')
            ->firstOrFail();

        $this->assertSame(1, $template->version);
        $this->assertTrue($template->is_active);
        $this->assertDatabaseHas('template_fields', [
            'venue_template_id' => $template->getKey(),
            'field_key' => 'room_layout',
            'label' => 'Tata ruang',
            'input_type' => TemplateField::TYPE_SELECT,
            'is_required' => 1,
            'is_filterable' => 1,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'venue_template.created',
            'auditable_type' => VenueTemplate::class,
            'auditable_id' => $template->getKey(),
            'actor_user_id' => $admin->getKey(),
        ]);
    }

    public function test_select_template_field_requires_at_least_one_allowed_option(): void
    {
        $admin = $this->createAdmin();
        $category = Category::query()->where('slug', 'meeting')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('pages::admin.venue-templates')
            ->call('startCreate')
            ->set('name', 'Template Tanpa Opsi')
            ->set('categoryId', (string) $category->getKey())
            ->set('fields', [
                [
                    'field_key' => 'room_layout',
                    'label' => 'Tata ruang',
                    'input_type' => TemplateField::TYPE_SELECT,
                    'unit_label' => '',
                    'options_text' => '',
                    'is_required' => false,
                    'is_filterable' => false,
                    'sort_order' => 10,
                    'min' => '',
                    'max' => '',
                    'max_length' => '',
                ],
            ])
            ->call('save')
            ->assertHasErrors(['fields.0.options_text']);

        $this->assertDatabaseMissing('venue_templates', [
            'category_id' => $category->getKey(),
            'name' => 'Template Tanpa Opsi',
        ]);
    }

    public function test_editing_a_template_creates_a_new_version_without_mutating_existing_venues(): void
    {
        $admin = $this->createAdmin();
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $category = Category::query()->where('slug', 'meeting')->firstOrFail();

        $template = $category->venueTemplates()->create([
            'name' => 'Template Ruang',
            'version' => 1,
            'is_active' => true,
        ]);

        $field = $template->fields()->create([
            'field_key' => 'room_layout',
            'label' => 'Tata ruang',
            'input_type' => TemplateField::TYPE_SELECT,
            'unit_label' => null,
            'options_json' => ['Classroom', 'Boardroom'],
            'validation_rules_json' => ['required' => true],
            'is_filterable' => true,
            'is_required' => true,
            'sort_order' => 10,
        ]);

        $business = $owner->businesses()->create([
            'name' => 'Studio Uji Template',
            'slug' => 'studio-uji-template',
            'address' => 'Jalan Contoh',
            'status' => Business::STATUS_DRAFT,
        ]);

        $venue = $business->venues()->create([
            'category_id' => $category->getKey(),
            'venue_template_id' => $template->getKey(),
            'name' => 'Venue Template Lama',
            'slug' => 'venue-template-lama',
            'status' => Venue::STATUS_DRAFT,
        ]);

        Livewire::actingAs($admin)
            ->test('pages::admin.venue-templates')
            ->call('editTemplate', $template->getKey())
            ->set('fields.0.label', 'Tata ruang utama')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Template berhasil disimpan sebagai versi baru');

        $template->refresh();
        $venue->refresh();

        $newTemplate = VenueTemplate::query()
            ->where('category_id', $category->getKey())
            ->where('name', 'Template Ruang')
            ->where('version', 2)
            ->firstOrFail();

        $this->assertFalse($template->is_active);
        $this->assertTrue($newTemplate->is_active);
        $this->assertSame($template->getKey(), $venue->venue_template_id);
        $this->assertSame('Tata ruang', $field->fresh()->label);
        $this->assertDatabaseHas('template_fields', [
            'venue_template_id' => $newTemplate->getKey(),
            'field_key' => 'room_layout',
            'label' => 'Tata ruang utama',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'venue_template.version_created',
            'auditable_type' => VenueTemplate::class,
            'auditable_id' => $newTemplate->getKey(),
            'actor_user_id' => $admin->getKey(),
        ]);
    }

    public function test_active_template_cannot_be_deleted_through_the_livewire_action(): void
    {
        $admin = $this->createAdmin();
        $category = Category::query()->where('slug', 'meeting')->firstOrFail();

        $template = $category->venueTemplates()->create([
            'name' => 'Template Aktif Uji',
            'version' => 1,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test('pages::admin.venue-templates')
            ->call('deleteUnusedTemplate', $template->getKey())
            ->assertSee('Nonaktifkan template aktif');

        $this->assertDatabaseHas('venue_templates', [
            'id' => $template->getKey(),
            'is_active' => 1,
        ]);
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }
}
