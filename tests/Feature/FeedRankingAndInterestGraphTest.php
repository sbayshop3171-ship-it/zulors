<?php

namespace Tests\Feature;

use App\Database\Configs\Table;
use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Enums\Media\MediaVisibility;
use App\Enums\Post\PostStatus;
use App\Enums\Post\PostType;
use App\Enums\NotificationType;
use App\Enums\Report\ReportType;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Models\Bookmark;
use App\Models\Comment;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Models\UserNotificationSettings;
use App\Services\Timeline\CandidateGenerationService;
use App\Services\Timeline\TopicExtractionService;
use App\Services\Timeline\UserInterestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FeedRankingAndInterestGraphTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_you_feed_ranks_interacted_posts_above_newer_unrelated_posts(): void
    {
        $viewer = $this->createUser('rank-viewer');
        $preferredAuthor = $this->createUser('preferred-author');
        $unrelatedAuthor = $this->createUser('unrelated-author');

        $preferredPost = $this->createPost($preferredAuthor, 'Older preferred post #sports', now()->subDays(2), [
            'comments_count' => 8,
            'bookmarks_count' => 4,
            'shares_count' => 3,
            'views_count' => 80,
        ]);

        $newerUnrelatedPost = $this->createPost($unrelatedAuthor, 'Newest unrelated post #random', now(), [
            'views_count' => 1,
        ]);

        Comment::query()->create([
            'post_id' => $preferredPost->id,
            'user_id' => $viewer->id,
            'parent_id' => null,
            'content' => 'I like this one',
            'text_language' => 'en',
        ]);

        Bookmark::query()->create([
            'user_id' => $viewer->id,
            'bookmarkable_id' => $preferredPost->id,
            'bookmarkable_type' => Post::class,
        ]);

        app(UserInterestService::class)->recordPostInteraction($viewer, $preferredPost, UserInterestService::EVENT_COMMENT);

        $forYouResponse = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=for_you')
            ->assertOk()
            ->assertJsonPath('meta.feed.type', 'for_you')
            ->assertJsonPath('meta.feed.scored', true);

        $forYouPostIds = array_column($forYouResponse->json('data'), 'id');

        $this->assertLessThan(
            array_search($newerUnrelatedPost->id, $forYouPostIds, true),
            array_search($preferredPost->id, $forYouPostIds, true)
        );

        $preferredPayload = collect($forYouResponse->json('data'))->firstWhere('id', $preferredPost->id);

        $this->assertArrayHasKey('ranking', $preferredPayload['meta']);
        $this->assertArrayHasKey('relationship', $preferredPayload['meta']['ranking']['signals']);

        $latestResponse = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=latest')
            ->assertOk()
            ->assertJsonPath('meta.feed.type', 'latest')
            ->assertJsonPath('meta.feed.scored', false);

        $latestPostIds = array_column($latestResponse->json('data'), 'id');

        $this->assertSame($newerUnrelatedPost->id, $latestPostIds[0]);
    }

    public function test_candidate_generation_caps_at_150_and_pagination_has_no_duplicates(): void
    {
        $viewer = $this->createUser('candidate-viewer');
        $author = $this->createUser('candidate-author');

        foreach(range(1, 170) as $index) {
            $this->createPost($author, "Candidate post {$index}", now()->subMinutes($index));
        }

        $startedAt = microtime(true);
        $candidates = app(CandidateGenerationService::class)->getCandidates($viewer, 'for_you');
        $elapsedMs = (microtime(true) - $startedAt) * 1000;

        $this->assertCount(150, $candidates);
        $this->assertLessThan(1000, $elapsedMs);

        $pageOne = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=for_you&page=1')
            ->assertOk();

        $pageTwo = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=for_you&page=2')
            ->assertOk();

        $pageOneIds = array_column($pageOne->json('data'), 'id');
        $pageTwoIds = array_column($pageTwo->json('data'), 'id');

        $this->assertCount(30, $pageOneIds);
        $this->assertCount(30, $pageTwoIds);
        $this->assertEmpty(array_intersect($pageOneIds, $pageTwoIds));
        $this->assertSame(150, $pageOne->json('meta.feed.candidate_limit'));
    }

    public function test_same_session_pagination_stays_stable_after_page_one_impressions(): void
    {
        $viewer = $this->createUser('session-viewer');
        $author = $this->createUser('session-author');
        $sessionId = 'feed-session-stable';

        foreach(range(1, 170) as $index) {
            $this->createPost($author, "Session post {$index}", now()->subMinutes($index));
        }

        $pageOne = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson("/api/timeline/feed?type=for_you&page=1&session_id={$sessionId}")
            ->assertOk();

        $pageOneIds = array_column($pageOne->json('data'), 'id');

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/timeline/telemetry/events', [
                'events' => collect($pageOneIds)->take(10)->map(fn($postId, $index) => [
                    'event_type' => 'post_impression',
                    'post_id' => $postId,
                    'session_id' => $sessionId,
                    'feed_type' => 'for_you',
                    'source' => 'home',
                    'position' => $index + 1,
                ])->values()->all(),
            ])
            ->assertOk()
            ->assertJsonPath('data.accepted', 10);

        $pageTwo = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson("/api/timeline/feed?type=for_you&page=2&session_id={$sessionId}")
            ->assertOk();

        $pageTwoIds = array_column($pageTwo->json('data'), 'id');

        $this->assertEmpty(array_intersect($pageOneIds, $pageTwoIds));
    }

    public function test_post_level_telemetry_updates_interests_and_seen_penalty_changes_new_session_feed(): void
    {
        $viewer = $this->createUser('telemetry-viewer');
        $author = $this->createUser('telemetry-author');

        $seenPost = $this->createPost($author, 'Seen high value post #travel', now(), [
            'comments_count' => 20,
            'bookmarks_count' => 20,
            'shares_count' => 20,
            'views_count' => 300,
        ]);

        $freshPost = $this->createPost($author, 'Fresh alternative post #coffee', now()->subMinutes(5), [
            'comments_count' => 2,
            'bookmarks_count' => 2,
            'shares_count' => 2,
            'views_count' => 50,
        ]);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/timeline/telemetry/events', [
                'events' => [[
                    'event_type' => 'post_dwell',
                    'post_id' => $seenPost->id,
                    'dwell_time_seconds' => 12,
                    'session_id' => 'feed-seen-session',
                    'feed_type' => 'for_you',
                    'source' => 'home',
                    'position' => 1,
                    'viewport_ratio' => 0.8,
                ]]
            ])
            ->assertOk()
            ->assertJsonPath('data.accepted', 1);

        $this->assertDatabaseHas(Table::FEED_EVENTS, [
            'user_id' => $viewer->id,
            'post_id' => $seenPost->id,
            'event_type' => 'post_dwell',
            'session_id' => 'feed-seen-session',
        ]);

        $this->assertGreaterThan(0, $this->interestScore($viewer, 'travel'));

        $feedResponse = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=for_you&session_id=feed-new-session')
            ->assertOk();

        $feedIds = array_column($feedResponse->json('data'), 'id');

        $this->assertLessThan(
            array_search($seenPost->id, $feedIds, true),
            array_search($freshPost->id, $feedIds, true)
        );

        $seenPayload = collect($feedResponse->json('data'))->firstWhere('id', $seenPost->id);

        $this->assertLessThan(0, $seenPayload['meta']['ranking']['signals']['seen_penalty']);
        $this->assertGreaterThan(0, $seenPayload['meta']['ranking']['signals']['session_jitter']);
    }

    public function test_interest_graph_learns_bangla_and_english_hashtags_and_changes_for_you_feed(): void
    {
        Notification::fake();

        $viewer = $this->createUser('interest-viewer');
        $author = $this->createUser('interest-author');

        $sportsPost = $this->createPost($author, 'Bangla sports post #Sports #খেলা', now()->subDay());
        $techPost = $this->createPost($author, 'Fresh tech post #tech', now());

        $topics = app(TopicExtractionService::class)->extractFromText($sportsPost->content);

        $this->assertContains('sports', $topics);
        $this->assertContains('খেলা', $topics);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/timeline/post/comment/create', [
                'post_id' => $sportsPost->id,
                'content' => 'Great topic',
            ])
            ->assertOk();

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/timeline/post/share/add', [
                'id' => $sportsPost->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas(Table::POST_TOPICS, [
            'post_id' => $sportsPost->id,
            'topic' => 'sports',
        ]);

        $sportsScore = $this->interestScore($viewer, 'sports');
        $this->assertGreaterThan(0, $sportsScore);

        $feedResponse = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=for_you')
            ->assertOk();

        $feedIds = array_column($feedResponse->json('data'), 'id');

        $this->assertLessThan(
            array_search($techPost->id, $feedIds, true),
            array_search($sportsPost->id, $feedIds, true)
        );

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/feedback/report/send', [
                'type' => ReportType::POST->value,
                'reason_index' => 0,
                'reportable_id' => $sportsPost->id,
            ])
            ->assertOk();

        $this->assertLessThan($sportsScore, $this->interestScore($viewer, 'sports'));
    }

    public function test_reels_feed_returns_only_active_playable_video_posts(): void
    {
        $viewer = $this->createUser('reels-video-viewer');
        $author = $this->createUser('reels-video-author');

        $playableVideo = $this->createVideoPost($author, 'Playable video #travel', now());
        $textPost = $this->createPost($author, 'Regular text post #travel', now()->subMinute());
        $failedVideo = $this->createVideoPost($author, 'Failed video #travel', now()->subMinutes(2), [], MediaStatus::FAILED);
        $inactiveVideo = $this->createVideoPost($author, 'Inactive video #travel', now()->subMinutes(3), [
            'status' => PostStatus::PROCESSING_VIDEO,
        ]);

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=reels&session_id=reels-video-only')
            ->assertOk()
            ->assertJsonPath('meta.feed.type', 'reels')
            ->assertJsonPath('meta.feed.scored', true);

        $postIds = array_column($response->json('data'), 'id');

        $this->assertContains($playableVideo->id, $postIds);
        $this->assertNotContains($textPost->id, $postIds);
        $this->assertNotContains($failedVideo->id, $postIds);
        $this->assertNotContains($inactiveVideo->id, $postIds);
    }

    public function test_reels_seed_hash_appears_first_and_pagination_has_no_duplicates(): void
    {
        $viewer = $this->createUser('reels-seed-viewer');
        $author = $this->createUser('reels-seed-author');

        $seedPost = $this->createVideoPost($author, 'Seed reel #design', now()->subHours(2));

        foreach(range(1, 65) as $index) {
            $this->createVideoPost($author, "Ranked reel {$index} #design", now()->subMinutes($index), [
                'views_count' => 200 - $index,
                'comments_count' => 20,
                'shares_count' => 10,
            ]);
        }

        $pageOne = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson("/api/timeline/feed?type=reels&page=1&session_id=reels-seed-session&seed_hash_id={$seedPost->hash_id}")
            ->assertOk();

        $pageOneIds = array_column($pageOne->json('data'), 'id');

        $this->assertSame($seedPost->id, $pageOneIds[0]);

        $pageTwo = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson("/api/timeline/feed?type=reels&page=2&session_id=reels-seed-session&seed_hash_id={$seedPost->hash_id}")
            ->assertOk();

        $pageTwoIds = array_column($pageTwo->json('data'), 'id');

        $this->assertEmpty(array_intersect($pageOneIds, $pageTwoIds));
    }

    public function test_reels_invalid_or_inaccessible_seed_falls_back_without_crashing(): void
    {
        $viewer = $this->createUser('reels-invalid-seed-viewer');
        $author = $this->createUser('reels-invalid-seed-author');

        $textSeed = $this->createPost($author, 'Not a reel seed', now());
        $fallbackVideo = $this->createVideoPost($author, 'Fallback reel #video', now()->subMinute());

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson("/api/timeline/feed?type=reels&session_id=reels-invalid-seed&seed_hash_id={$textSeed->hash_id}")
            ->assertOk()
            ->assertJsonPath('meta.feed.type', 'reels');

        $postIds = array_column($response->json('data'), 'id');

        $this->assertContains($fallbackVideo->id, $postIds);
        $this->assertNotContains($textSeed->id, $postIds);
    }

    public function test_reels_feed_excludes_blocked_and_muted_authors(): void
    {
        $viewer = $this->createUser('reels-safety-viewer');
        $visibleAuthor = $this->createUser('reels-visible-author');
        $mutedAuthor = $this->createUser('reels-muted-author');
        $blockedAuthor = $this->createUser('reels-blocked-author');

        $visibleVideo = $this->createVideoPost($visibleAuthor, 'Visible reel #food', now());
        $mutedVideo = $this->createVideoPost($mutedAuthor, 'Muted reel #food', now()->subMinute());
        $blockedVideo = $this->createVideoPost($blockedAuthor, 'Blocked reel #food', now()->subMinutes(2));

        DB::table(Table::MUTES)->insert([
            'muter_id' => $viewer->id,
            'muted_id' => $mutedAuthor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(Table::BLOCKS)->insert([
            'blocker_id' => $viewer->id,
            'blocked_id' => $blockedAuthor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=reels&session_id=reels-blocks')
            ->assertOk();

        $postIds = array_column($response->json('data'), 'id');

        $this->assertContains($visibleVideo->id, $postIds);
        $this->assertNotContains($mutedVideo->id, $postIds);
        $this->assertNotContains($blockedVideo->id, $postIds);
    }

    public function test_reels_video_telemetry_updates_video_metrics_and_interests(): void
    {
        $viewer = $this->createUser('reels-telemetry-viewer');
        $author = $this->createUser('reels-telemetry-author');
        $post = $this->createVideoPost($author, 'Telemetry reel #music', now());
        $media = $post->media()->first();

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/timeline/telemetry/events', [
                'events' => [[
                    'event_type' => 'video_watch',
                    'post_id' => $post->id,
                    'media_id' => $media->id,
                    'watch_time_seconds' => 4,
                    'duration_seconds' => 6,
                    'completion_rate' => 0.67,
                    'session_id' => 'reels-telemetry-session',
                    'feed_type' => 'reels',
                    'source' => 'reels',
                    'position' => 1,
                ]]
            ])
            ->assertOk()
            ->assertJsonPath('data.accepted', 1);

        $this->assertDatabaseHas(Table::FEED_EVENTS, [
            'user_id' => $viewer->id,
            'post_id' => $post->id,
            'media_id' => $media->id,
            'event_type' => 'video_watch',
            'session_id' => 'reels-telemetry-session',
        ]);

        $this->assertDatabaseHas(Table::POST_VIDEO_METRICS, [
            'post_id' => $post->id,
            'media_id' => $media->id,
        ]);

        $this->assertGreaterThan(0, $this->interestScore($viewer, 'music'));
    }

    private function interestScore(User $user, string $topic): float
    {
        return (float) \App\Models\UserInterestScore::query()
            ->where('user_id', $user->id)
            ->where('topic', $topic)
            ->value('score');
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

    private function createPost(User $author, string $content, $createdAt = null, array $overrides = []): Post
    {
        $createdAt = $createdAt ?: now();

        return Post::query()->create(array_merge([
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
        ], $overrides));
    }

    private function createVideoPost(User $author, string $content, $createdAt = null, array $postOverrides = [], MediaStatus $mediaStatus = MediaStatus::PROCESSED): Post
    {
        $post = $this->createPost($author, $content, $createdAt, array_merge([
            'type' => PostType::VIDEO,
        ], $postOverrides));

        $this->createVideoMedia($post, 12, $mediaStatus);

        return $post;
    }

    private function createVideoMedia(Post $post, int $durationSeconds, MediaStatus $status = MediaStatus::PROCESSED): Media
    {
        return $post->media()->create([
            'source_path' => "posts/videos/{$post->id}.mp4",
            'thumbnail_path' => "posts/video_thumbnails/{$post->id}.jpg",
            'type' => MediaType::VIDEO,
            'status' => $status,
            'disk' => 'public',
            'thumbnail_disk' => 'public',
            'extension' => 'mp4',
            'visibility' => MediaVisibility::VISIBLE,
            'mime' => 'video/mp4',
            'size' => '100',
            'thumbnail_size' => '10',
            'order' => 0,
            'metadata' => [
                'duration' => [
                    'seconds' => $durationSeconds,
                    'formatted' => "0:{$durationSeconds}",
                ],
                'duration_seconds' => $durationSeconds,
                'aspect_ratio' => 0.5625,
                'is_portrait' => true,
            ],
        ]);
    }
}
