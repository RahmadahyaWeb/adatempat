<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed the initial venue categories.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Acara',
                'slug' => 'acara',
                'status' => Category::STATUS_ACTIVE,
            ],
            [
                'name' => 'Meeting',
                'slug' => 'meeting',
                'status' => Category::STATUS_ACTIVE,
            ],
            [
                'name' => 'Foto',
                'slug' => 'foto',
                'status' => Category::STATUS_ACTIVE,
            ],
            [
                'name' => 'Outdoor',
                'slug' => 'outdoor',
                'status' => Category::STATUS_ACTIVE,
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                $category,
            );
        }
    }
}
