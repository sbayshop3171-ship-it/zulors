<?php

namespace Tests\Feature;

use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PostDeleteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_post_delete_is_idempotent_for_stale_cached_timeline_items(): void
    {
        $author = $this->createUser('stale-delete-owner');

        $this->actingAs($author)
            ->withoutMiddleware()
            ->deleteJson('/api/timeline/post/delete', [
                'id' => 5245,
            ])
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    private function createUser(string $username): User
    {
        return User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => $username,
            'caption' => '@' . $username,
            'email' => "{$username}@example.com",
            'phone' => '',
            'website' => '',
            'bio' => '',
            'country' => null,
            'city' => null,
            'birth_day' => null,
            'birth_month' => null,
            'birth_year' => null,
            'age' => null,
            'gender' => 'male',
            'last_active' => now()->timestamp,
            'language' => 'en',
            'avatar' => null,
            'cover' => null,
            'verified' => false,
            'tips' => [],
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => UserRole::USER,
            'theme' => 'light',
            'publications_count' => 0,
            'followers_count' => 0,
            'following_count' => 0,
            'status' => UserStatus::ACTIVE,
            'type' => UserType::AUTHOR,
        ]);
    }
}
