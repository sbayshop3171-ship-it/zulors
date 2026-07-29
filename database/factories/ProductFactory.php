<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Category;
use App\Enums\Product\ProductStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => function() {
                return User::active()->inRandomOrder()->first()->id;
            },
            'title' => $this->faker->words(12, true),
            'category_id' => function() {
                return Category::marketplace()->inRandomOrder()->first()->id;
            },
            'description' => $this->faker->words(30, true),
            'stock_quantity' => $this->faker->numberBetween(10, 100),
            'status' => ProductStatus::ACTIVE,
            'price' => $this->faker->numberBetween(10, 5000),
            'discount' => $this->faker->numberBetween(0, 100),
            'address' => $this->faker->address(),
            'views_count' => $this->faker->numberBetween(0, 1000),
            'contacts_count' => $this->faker->numberBetween(0, 1000),
            'bookmarks_count' => $this->faker->numberBetween(0, 1000),
            'last_contacted_at' => $this->faker->dateTime()
        ];
    }
}
