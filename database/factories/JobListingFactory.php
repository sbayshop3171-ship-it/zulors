<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Category;
use App\Enums\Job\JobType;
use App\Enums\Job\JobStatus;
use App\Enums\Job\JobApproval;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobListingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => function() {
                return User::active()->inRandomOrder()->first()->id;
            },
            'category_id' => function() {
                return Category::jobs()->inRandomOrder()->first()->id;
            },
            'title' => fake()->jobTitle(),
            'description' => fake()->paragraph(),
            'overview' => fake()->paragraph(),
            'status' => JobStatus::ACTIVE,
            'views_count' => fake()->numberBetween(0, 1000),
            'applications_count' => fake()->numberBetween(0, 1000),
            'bookmarks_count' => fake()->numberBetween(0, 1000),
            'income' => fake()->numberBetween(10000, 100000),
            'is_start_income' => fake()->boolean(),
            'currency' => fake()->currencyCode(),
            'approval' => JobApproval::APPROVED,
            'location' => fake()->city(),
            'is_remote' => fake()->boolean(),
            'is_urgent' => fake()->boolean(),
            'type' => JobType::VACANCY,
            'last_contacted_at' => fake()->dateTime(),
        ];
    }
}
