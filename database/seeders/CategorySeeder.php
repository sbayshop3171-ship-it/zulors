<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use App\Enums\Category\CategoryType;
use Illuminate\Support\Facades\Schema;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        Category::truncate();

        $this->marketplaceCategories();
        $this->jobCategories();

        Schema::enableForeignKeyConstraints();
    }

    private function marketplaceCategories()
    {
        $categories = require(database_path('data/product_categories.php'));

        foreach ($categories as $categoryName) {
            Category::create([
                'localization' => [
                    'en' => $categoryName,
                ],
                'categorizable_type' => CategoryType::PRODUCT,
                'parent_id' => null,
                'depth' => 1
            ]);
        }
    }

    private function jobCategories()
    {
        $categories = require(database_path('data/job_categories.php'));
        
        foreach ($categories as $categoryName) {
            Category::create([
                'localization' => [
                    'en' => $categoryName,
                ],
                'categorizable_type' => CategoryType::JOB,
                'parent_id' => null,
                'depth' => 1
            ]);
        }
    }
}
