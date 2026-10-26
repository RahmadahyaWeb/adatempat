<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\TemplateField;
use App\Models\VenueTemplate;
use Illuminate\Database\Seeder;

class VenueTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'acara' => [
                'name' => 'Informasi acara',
                'fields' => [
                    [
                        'field_key' => 'stage_type',
                        'label' => 'Jenis panggung',
                        'input_type' => TemplateField::TYPE_SELECT,
                        'unit_label' => null,
                        'options_json' => ['Panggung tetap', 'Tanpa panggung', 'Fleksibel'],
                        'validation_rules_json' => [],
                        'is_filterable' => true,
                        'is_required' => false,
                        'sort_order' => 10,
                    ],
                    [
                        'field_key' => 'sound_system',
                        'label' => 'Sistem suara tersedia',
                        'input_type' => TemplateField::TYPE_BOOLEAN,
                        'unit_label' => null,
                        'options_json' => null,
                        'validation_rules_json' => [],
                        'is_filterable' => true,
                        'is_required' => false,
                        'sort_order' => 20,
                    ],
                    [
                        'field_key' => 'parking_spaces',
                        'label' => 'Jumlah ruang parkir',
                        'input_type' => TemplateField::TYPE_NUMBER,
                        'unit_label' => 'kendaraan',
                        'options_json' => null,
                        'validation_rules_json' => ['min' => 0, 'max' => 1000],
                        'is_filterable' => false,
                        'is_required' => false,
                        'sort_order' => 30,
                    ],
                ],
            ],
            'meeting' => [
                'name' => 'Informasi ruang meeting',
                'fields' => [
                    [
                        'field_key' => 'projector_available',
                        'label' => 'Proyektor tersedia',
                        'input_type' => TemplateField::TYPE_BOOLEAN,
                        'unit_label' => null,
                        'options_json' => null,
                        'validation_rules_json' => [],
                        'is_filterable' => true,
                        'is_required' => false,
                        'sort_order' => 10,
                    ],
                    [
                        'field_key' => 'whiteboard_available',
                        'label' => 'Papan tulis tersedia',
                        'input_type' => TemplateField::TYPE_BOOLEAN,
                        'unit_label' => null,
                        'options_json' => null,
                        'validation_rules_json' => [],
                        'is_filterable' => true,
                        'is_required' => false,
                        'sort_order' => 20,
                    ],
                    [
                        'field_key' => 'meeting_layout',
                        'label' => 'Tata ruang',
                        'input_type' => TemplateField::TYPE_SELECT,
                        'unit_label' => null,
                        'options_json' => ['Boardroom', 'Classroom', 'U-shape', 'Theatre', 'Fleksibel'],
                        'validation_rules_json' => [],
                        'is_filterable' => true,
                        'is_required' => false,
                        'sort_order' => 30,
                    ],
                ],
            ],
            'foto' => [
                'name' => 'Informasi studio foto',
                'fields' => [
                    [
                        'field_key' => 'natural_light',
                        'label' => 'Cahaya alami tersedia',
                        'input_type' => TemplateField::TYPE_BOOLEAN,
                        'unit_label' => null,
                        'options_json' => null,
                        'validation_rules_json' => [],
                        'is_filterable' => true,
                        'is_required' => false,
                        'sort_order' => 10,
                    ],
                    [
                        'field_key' => 'backdrop_colors',
                        'label' => 'Pilihan warna backdrop',
                        'input_type' => TemplateField::TYPE_MULTISELECT,
                        'unit_label' => null,
                        'options_json' => ['Putih', 'Hitam', 'Abu-abu', 'Warna khusus'],
                        'validation_rules_json' => [],
                        'is_filterable' => true,
                        'is_required' => false,
                        'sort_order' => 20,
                    ],
                    [
                        'field_key' => 'power_outlets',
                        'label' => 'Jumlah titik listrik',
                        'input_type' => TemplateField::TYPE_NUMBER,
                        'unit_label' => 'titik',
                        'options_json' => null,
                        'validation_rules_json' => ['min' => 0, 'max' => 500],
                        'is_filterable' => false,
                        'is_required' => false,
                        'sort_order' => 30,
                    ],
                ],
            ],
            'outdoor' => [
                'name' => 'Informasi tempat outdoor',
                'fields' => [
                    [
                        'field_key' => 'surface_type',
                        'label' => 'Jenis permukaan',
                        'input_type' => TemplateField::TYPE_SELECT,
                        'unit_label' => null,
                        'options_json' => ['Rumput', 'Paving', 'Tanah', 'Dek kayu', 'Campuran'],
                        'validation_rules_json' => [],
                        'is_filterable' => true,
                        'is_required' => false,
                        'sort_order' => 10,
                    ],
                    [
                        'field_key' => 'covered_area',
                        'label' => 'Memiliki area beratap',
                        'input_type' => TemplateField::TYPE_BOOLEAN,
                        'unit_label' => null,
                        'options_json' => null,
                        'validation_rules_json' => [],
                        'is_filterable' => true,
                        'is_required' => false,
                        'sort_order' => 20,
                    ],
                    [
                        'field_key' => 'noise_restrictions',
                        'label' => 'Batasan kebisingan',
                        'input_type' => TemplateField::TYPE_TEXT,
                        'unit_label' => null,
                        'options_json' => null,
                        'validation_rules_json' => ['max_length' => 180],
                        'is_filterable' => false,
                        'is_required' => false,
                        'sort_order' => 30,
                    ],
                ],
            ],
        ];

        foreach ($definitions as $categorySlug => $definition) {
            $category = Category::query()->where('slug', $categorySlug)->first();

            if ($category === null) {
                continue;
            }

            $template = VenueTemplate::query()->updateOrCreate(
                [
                    'category_id' => $category->getKey(),
                    'name' => $definition['name'],
                    'version' => 1,
                ],
                ['is_active' => true],
            );

            foreach ($definition['fields'] as $field) {
                TemplateField::query()->updateOrCreate(
                    [
                        'venue_template_id' => $template->getKey(),
                        'field_key' => $field['field_key'],
                    ],
                    $field,
                );
            }
        }

        $this->command?->info('Template field default untuk kategori venue berhasil disiapkan.');
    }
}
