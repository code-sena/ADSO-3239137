<?php

namespace Database\Seeders;

use App\Models\TechnicalAidCategory;
use Illuminate\Database\Seeder;

class TechnicalAidCategorySeeder extends Seeder
{
    /**
     * Seed the technical aid categories.
     */
    public function run(): void
    {
        $categories = [
            'Wheelchairs',
            'Crutches',
            'Walkers',
            'Canes',
            'Cushions',
        ];

        foreach ($categories as $category) {
            TechnicalAidCategory::firstOrCreate([
                'name' => $category,
            ]);
        }
    }
}