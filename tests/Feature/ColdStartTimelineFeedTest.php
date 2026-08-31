<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Database\Configs\Table;
use App\Enums\Post\PostStatus;
use App\Enums\Post\PostType;
use App\Enums\User\FollowStatus;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Models\Bookmark;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\UserNotificationSettings;
use App\Services\Timeline\UserInterestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    public function test_new_user_initial_feed_includes_active_reader_posts(): void
    {
        $viewer = $this->createUser('reader-cold-start-viewer', UserType::READER);
        $creator = $this->createUser('reader-cold-start-creator', UserType::READER);
        $readerPost = $this->createPost($creator, 'Reader account post should be visible', now());

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=for_you&refresh_reason=initial')
            ->assertOk()
            ->assertJsonPath('meta.feed.strategy', 'cold_start_chronological');

        $postIds = array_column($response->json('data'), 'id');

        $this->assertContains($readerPost->id, $postIds);
    }

    public function test_fast_start_initial_feed_skips_ranking_for_personalized_users(): void
    {
        $viewer = $this->createUser('fast-start-viewer');
        $followedAuthor = $this->createUser('fast-start-followed-author');
        $latestAuthor = $this->createUser('fast-start-latest-author');

        DB::table(Table::FOLLOWS)->insert([
            'follower_id' => $viewer->id,
            'following_id' => $followedAuthor->id,
            'status' => FollowStatus::FOLLOWING->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $olderPost = $this->createPost($followedAuthor, 'Older followed post', now()->subMinutes(10));
        $newerPost = $this->createPost($latestAuthor, 'Latest fast start post', now());

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=for_you&refresh_reason=initial&fast_start=1')
            ->assertOk()
            ->assertJsonPath('meta.feed.strategy', 'fast_start_chronological')
            ->assertJsonPath('meta.feed.scored', false);

        $postIds = array_column($response->json('data'), 'id');

        $this->assertSame($newerPost->id, $postIds[0]);
        $this->assertContains($olderPost->id, $postIds);
    }

    public function test_home_feed_response_includes_private_swr_headers_and_etag(): void
    {
        Cache::flush();

        $viewer = $this->createUser('home-swr-viewer');
        $author = $this->createUser('home-swr-author');

        $this->createPost($author, 'Home SWR post', now());

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=for_you&refresh_reason=initial&fast_start=1')
            ->assertOk()
            ->assertJsonPath('meta.feed.type', 'for_you');

        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('stale-while-revalidate=60', (string) $response->headers->get('Cache-Control'));
        $this->assertNotEmpty($response->headers->get('ETag'));
        $this->assertNotEmpty($response->headers->get('X-Zulors-Feed-Snapshot'));
        $this->assertSame('miss', $response->headers->get('X-Zulors-Feed-Cache'));
    }

    public function test_home_feed_matching_etag_returns_not_modified(): void
    {
        Cache::flush();

        $viewer = $this->createUser('home-304-viewer');
        $author = $this->createUser('home-304-author');

        $this->createPost($author, 'Home ETag post', now());

        $firstResponse = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=for_you&refresh_reason=initial&fast_start=1')
            ->assertOk();

        $etag = $firstResponse->headers->get('ETag');

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->withHeaders([
                'If-None-Match' => $etag,
            ])
            ->getJson('/api/timeline/feed?type=for_you&refresh_reason=initial&fast_start=1')
            ->assertStatus(304)
            ->assertHeader('ETag', $etag)
            ->assertHeader('X-Zulors-Feed-Cache', 'hit');
    }

    public function test_home_feed_non_matching_etag_returns_fresh_payload(): void
    {
        Cache::flush();

        $viewer = $this->createUser('home-etag-miss-viewer');
        $author = $this->createUser('home-etag-miss-author');

        $latestPost = $this->createPost($author, 'Home non-matching ETag post', now());

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->withHeaders([
                'If-None-Match' => '"zulors-home-feed-stale"',
            ])
            ->getJson('/api/timeline/feed?type=for_you&refresh_reason=initial&fast_start=1')
            ->assertOk()
            ->assertJsonPath('meta.feed.type', 'for_you');

        $postIds = array_column($response->json('data'), 'id');

        $this->assertContains($latestPost->id, $postIds);
        $this->assertNotSame('"zulors-home-feed-stale"', $response->headers->get('ETag'));
    }

    public function test_bootstrap_payload_contains_initial_home_feed_for_authenticated_users(): void
    {
        $viewer = $this->createUser('bootstrap-feed-viewer');
        $preferredAuthor = $this->createUser('bootstrap-feed-preferred-author');
        $latestAuthor = $this->createUser('bootstrap-feed-latest-author');

        DB::table(Table::FOLLOWS)->insert([
            'follower_id' => $viewer->id,
            'following_id' => $preferredAuthor->id,
            'status' => FollowStatus::FOLLOWING->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $preferredPost = $this->createPost($preferredAuthor, 'Preferred bootstrap post #sports', now()->subMinutes(5));
        $latestPost = $this->createPost($latestAuthor, 'Latest bootstrap post #random', now());

        Comment::query()->create([
            'post_id' => $preferredPost->id,
            'user_id' => $viewer->id,
            'parent_id' => null,
            'content' => 'This is the best one',
            'text_language' => 'en',
        ]);

        Bookmark::query()->create([
            'user_id' => $viewer->id,
            'bookmarkable_id' => $preferredPost->id,
            'bookmarkable_type' => Post::class,
        ]);

        app(UserInterestService::class)->recordPostInteraction($viewer, $preferredPost, UserInterestService::EVENT_COMMENT);

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/bootstrap/bootstrap')
            ->assertOk()
            ->assertJsonPath('data.auth.status', true)
            ->assertJsonPath('data.home_feed.type', 'for_you')
            ->assertJsonPath('data.home_feed.refresh_reason', 'initial')
            ->assertJsonPath('data.home_feed.meta.feed.strategy', 'home_ranking_v2')
            ->assertJsonPath('data.home_feed.meta.feed.rank_version', 'home_ranking_v2')
            ->assertJsonPath('data.home_feed.meta.feed.feed_family', 'home')
            ->assertJsonPath('data.home_feed.meta.feed.re_rank_allowed', true)
            ->assertJsonPath('data.home_feed.meta.feed.session_window_size', 50)
            ->assertJsonPath('data.home_feed.meta.feed.scored', true);

        $bootFeedPostIds = array_column($response->json('data.home_feed.posts'), 'id');

        $this->assertSame($preferredPost->id, $bootFeedPostIds[0]);
        $this->assertContains($latestPost->id, $bootFeedPostIds);
    }

    public function test_bootstrap_home_feed_uses_personalized_order_instead_of_public_seed_order(): void
    {
        $viewer = $this->createUser('bootstrap-order-viewer');
        $preferredAuthor = $this->createUser('bootstrap-order-preferred-author');
        $latestAuthor = $this->createUser('bootstrap-order-latest-author');

        $preferredPost = $this->createPost($preferredAuthor, 'Older preferred post #travel', now()->subMinutes(8));
        $latestPost = $this->createPost($latestAuthor, 'Newest public seed post #random', now());

        Comment::query()->create([
            'post_id' => $preferredPost->id,
            'user_id' => $viewer->id,
            'parent_id' => null,
            'content' => 'Show me more like this',
            'text_language' => 'en',
        ]);

        Bookmark::query()->create([
            'user_id' => $viewer->id,
            'bookmarkable_id' => $preferredPost->id,
            'bookmarkable_type' => Post::class,
        ]);

        app(UserInterestService::class)->recordPostInteraction($viewer, $preferredPost, UserInterestService::EVENT_COMMENT);

        $bootstrapResponse = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/bootstrap/bootstrap')
            ->assertOk();

        $publicSeedResponse = $this->withoutMiddleware()
            ->getJson('/api/bootstrap/home-feed-seed')
            ->assertOk();

        $bootstrapPostIds = array_column($bootstrapResponse->json('data.home_feed.posts'), 'id');
        $publicSeedPostIds = array_column($publicSeedResponse->json('data.posts'), 'id');

        $this->assertSame($preferredPost->id, $bootstrapPostIds[0]);
        $this->assertSame($latestPost->id, $publicSeedPostIds[0]);
        $this->assertNotSame($bootstrapPostIds[0], $publicSeedPostIds[0]);
    }

    public function test_public_home_feed_seed_is_available_without_authentication(): void
    {
        $author = $this->createUser('public-seed-author');

        $latestPost = $this->createPost($author, 'Latest public seed post', now());
        $olderPost = $this->createPost($author, 'Older public seed post', now()->subMinutes(4));

        $response = $this->withoutMiddleware()
            ->getJson('/api/bootstrap/home-feed-seed')
            ->assertOk()
            ->assertJsonPath('data.type', 'for_you')
            ->assertJsonPath('data.refresh_reason', 'seed')
            ->assertJsonPath('data.meta.feed.strategy', 'public_seed_cache')
            ->assertJsonPath('data.meta.feed.rank_version', 'chronological_v1')
            ->assertJsonPath('data.meta.feed.scored', false);

        $postIds = array_column($response->json('data.posts'), 'id');

        $this->assertSame($latestPost->id, $postIds[0]);
        $this->assertContains($olderPost->id, $postIds);
    }

    public function test_public_home_feed_seed_route_is_public_with_middleware_enabled(): void
    {
        $author = $this->createUser('public-seed-live-author');

        $this->createPost($author, 'Public middleware seed post', now());

        $this->getJson('/api/bootstrap/home-feed-seed')
            ->assertOk()
            ->assertJsonPath('data.type', 'for_you')
            ->assertJsonPath('data.meta.feed.strategy', 'public_seed_cache');
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
