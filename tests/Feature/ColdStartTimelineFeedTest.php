<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\Post\PostStatus;
use App\Enums\Post\PostType;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Models\Post;
use App\Models\User;
use App\Models\UserNotificationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ColdStartTimelineFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_initial_for_you_feed_returns_latest_posts_without_waiting_for_personalization(): void
    {
        $viewer = $this->createUser('cold-start-viewer');
        $author = $this->createUser('cold-start-author');

        $olderPost = $this->createPost($author, 'Older cold start post', now()->subMinutes(5));
        $newerPost = $this->createPost($author, 'Newer cold start post', now());

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=for_you&refresh_reason=initial')
            ->assertOk()
            ->assertJsonPath('meta.feed.type', 'for_you')
            ->assertJsonPath('meta.feed.strategy', 'cold_start_chronological')
            ->assertJsonPath('meta.feed.scored', false);

        $postIds = array_column($response->json('data'), 'id');

        $this->assertSame($newerPost->id, $postIds[0]);
        $this->assertContains($olderPost->id, $postIds);
    }

    private function createUser(string $username, UserType $type = UserType::AUTHOR): User
    {
        $user = User::query()->create([
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
            'type' => $type,
        ]);

        UserNotificationSettings::query()->create([
            'user_id' => $user->id,
            'type' => NotificationType::EMAIL,
        ]);

        UserNotificationSettings::query()->create([
            'user_id' => $user->id,
            'type' => NotificationType::PUSH,
        ]);

        return $user;
    }

    private function createPost(User $author, string $content, $createdAt): Post
    {
        return Post::query()->create([
            'user_id' => $author->id,
            'quote_post_id' => null,
            'title' => '',
            'content' => $content,
            'status' => PostStatus::ACTIVE,
            'type' => PostType::TEXT,
            'text_language' => 'en',
            'edited' => false,
            'profile_pinned' => false,
            'global_pinned' => false,
            'is_sensitive' => false,
            'is_ai_generated' => false,
            'views_count' => 0,
            'comments_count' => 0,
            'shares_count' => 0,
            'bookmarks_count' => 0,
            'quotes_count' => 0,
            'preview_lqip_base64' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
