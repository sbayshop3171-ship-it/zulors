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
use App\Models\PostVideoMetric;
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

    public function test_v2_feed_meta_exposes_rank_version_and_candidate_sources(): void
    {
        $viewer = $this->createUser('meta-viewer');
        $followedAuthor = $this->createUser('meta-followed-author');
        $topicAuthor = $this->createUser('meta-topic-author');
        $topicOnlyAuthor = $this->createUser('meta-topic-only-author');

        DB::table(Table::FOLLOWS)->insert([
            'follower_id' => $viewer->id,
            'following_id' => $followedAuthor->id,
            'status' => \App\Enums\User\FollowStatus::FOLLOWING->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $followedPost = $this->createPost($followedAuthor, 'Followed home post #travel', now()->subMinutes(3));
        $topicPost = $this->createPost($topicAuthor, 'Topic matched home post #travel', now()->subMinutes(5));
        $topicOnlyPost = $this->createPost($topicOnlyAuthor, 'Fresh topic-only home post #travel', now()->subMinutes(6));

        app(UserInterestService::class)->recordPostInteraction($viewer, $topicPost, UserInterestService::EVENT_COMMENT);

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=for_you&debug_ranking=1')
            ->assertOk()
            ->assertJsonPath('meta.feed.rank_version', 'home_ranking_v2')
            ->assertJsonPath('meta.feed.feed_family', 'home')
            ->assertJsonPath('meta.feed.re_rank_allowed', true)
            ->assertJsonPath('meta.feed.session_window_size', 50);

        $candidateSources = $response->json('meta.feed.candidate_sources');

        $this->assertContains('followed', $candidateSources);
        $this->assertTrue(
            in_array('topic_match', $candidateSources, true) || in_array('interacted_author', $candidateSources, true)
        );
        $this->assertSame('home_ranking_v2', data_get($response->json('data.0.meta.ranking'), 'version'));
        $this->assertContains($topicOnlyPost->id, array_column($response->json('data'), 'id'));
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

    public function test_repeat_seen_posts_receive_stronger_repeat_penalty_on_new_sessions(): void
    {
        $viewer = $this->createUser('repeat-seen-viewer');
        $author = $this->createUser('repeat-seen-author');

        $repeatPost = $this->createPost($author, 'Repeat high value post #design', now(), [
            'comments_count' => 25,
            'bookmarks_count' => 25,
            'shares_count' => 25,
            'views_count' => 500,
        ]);

        $alternativePost = $this->createPost($author, 'Fresh replacement post #design', now()->subMinutes(4), [
            'comments_count' => 1,
            'bookmarks_count' => 1,
            'shares_count' => 1,
            'views_count' => 10,
        ]);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/timeline/telemetry/events', [
                'events' => [
                    [
                        'event_type' => 'post_dwell',
                        'post_id' => $repeatPost->id,
                        'dwell_time_seconds' => 10,
                        'session_id' => 'repeat-seen-one',
                        'feed_type' => 'for_you',
                        'source' => 'home',
                        'position' => 1,
                    ],
                    [
                        'event_type' => 'post_dwell',
                        'post_id' => $repeatPost->id,
                        'dwell_time_seconds' => 9,
                        'session_id' => 'repeat-seen-two',
                        'feed_type' => 'for_you',
                        'source' => 'home',
                        'position' => 4,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.accepted', 2);

        $feedResponse = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=for_you&session_id=repeat-seen-new-open')
            ->assertOk();

        $feedIds = array_column($feedResponse->json('data'), 'id');

        $this->assertLessThan(
            array_search($repeatPost->id, $feedIds, true),
            array_search($alternativePost->id, $feedIds, true)
        );

        $repeatPayload = collect($feedResponse->json('data'))->firstWhere('id', $repeatPost->id);

        $this->assertLessThanOrEqual(-150, $repeatPayload['meta']['ranking']['signals']['seen_penalty']);
    }

    public function test_explore_posts_use_session_ranked_feed_and_suppress_seen_posts(): void
    {
        $viewer = $this->createUser('explore-posts-viewer');
        $author = $this->createUser('explore-posts-author');

        $seenPost = $this->createPost($author, 'Seen explore post #travel', now(), [
            'comments_count' => 20,
            'bookmarks_count' => 20,
            'shares_count' => 20,
            'views_count' => 300,
        ]);

        $freshPost = $this->createPost($author, 'Fresh explore replacement #coffee', now()->subMinutes(5), [
            'comments_count' => 1,
            'bookmarks_count' => 1,
            'shares_count' => 1,
            'views_count' => 20,
        ]);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/timeline/telemetry/events', [
                'events' => [[
                    'event_type' => 'post_dwell',
                    'post_id' => $seenPost->id,
                    'dwell_time_seconds' => 12,
                    'session_id' => 'explore-posts-old-session',
                    'feed_type' => 'for_you',
                    'source' => 'explore_posts',
                    'position' => 1,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.accepted', 1);

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/explore/posts', [
                'filter' => [
                    'page' => 1,
                    'session_id' => 'explore-posts-new-session',
                    'refresh_reason' => 'open',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('meta.feed.type', 'for_you')
            ->assertJsonPath('meta.feed.scored', true);

        $postIds = array_column($response->json('data'), 'id');

        $this->assertLessThan(
            array_search($seenPost->id, $postIds, true),
            array_search($freshPost->id, $postIds, true)
        );

        $seenPayload = collect($response->json('data'))->firstWhere('id', $seenPost->id);

        $this->assertLessThan(0, $seenPayload['meta']['ranking']['signals']['seen_penalty']);
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
                    'playback_session_id' => 'reels-playback-session',
                    'is_muted' => true,
                    'first_frame_ms' => 220,
                    'stall_count' => 1,
                    'swipe_away_ms' => 4200,
                    '3s_view' => true,
                    '50p_complete' => true,
                    '95p_complete' => false,
                    'follow_after_view' => true,
                    'share_after_view' => false,
                    'mute_state' => 'muted',
                    'visible_window_index' => 1,
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

        $event = \App\Models\FeedEvent::query()
            ->where('user_id', $viewer->id)
            ->where('post_id', $post->id)
            ->first();

        $this->assertSame('reels', $event->metadata['feed_type']);
        $this->assertSame(1, $event->metadata['position']);
        $this->assertSame('reels-playback-session', $event->metadata['playback_session_id']);
        $this->assertTrue($event->metadata['is_muted']);
        $this->assertSame(220.0, (float) $event->metadata['first_frame_ms']);
        $this->assertSame(1, $event->metadata['stall_count']);
        $this->assertTrue($event->metadata['3s_view']);
        $this->assertTrue($event->metadata['50p_complete']);
        $this->assertSame('muted', $event->metadata['mute_state']);

        $this->assertDatabaseHas(Table::POST_VIDEO_METRICS, [
            'post_id' => $post->id,
            'media_id' => $media->id,
        ]);

        $this->assertGreaterThan(0, $this->interestScore($viewer, 'music'));
    }

    public function test_reels_ranking_prefers_retention_quality_over_simple_recency(): void
    {
        $viewer = $this->createUser('reels-retention-viewer');
        $author = $this->createUser('reels-retention-author');

        $newSkippedReel = $this->createVideoPost($author, 'Brand new low retention reel #dance', now());
        $oldLovedReel = $this->createVideoPost($author, 'Older high retention reel #dance', now()->subDays(3));

        PostVideoMetric::query()->create([
            'post_id' => $newSkippedReel->id,
            'media_id' => $newSkippedReel->media()->value('id'),
            'plays_count' => 40,
            'skips_count' => 34,
            'avg_completion_rate' => 0.18,
            'completion_rate' => 0.05,
            'skip_rate' => 0.85,
            'intelligence_score' => -35,
        ]);

        PostVideoMetric::query()->create([
            'post_id' => $oldLovedReel->id,
            'media_id' => $oldLovedReel->media()->value('id'),
            'plays_count' => 40,
            'completions_count' => 32,
            'loops_count' => 8,
            'rewatches_count' => 10,
            'avg_completion_rate' => 0.98,
            'completion_rate' => 0.80,
            'skip_rate' => 0.05,
            'rewatch_rate' => 0.25,
            'intelligence_score' => 62,
        ]);

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=reels&session_id=reels-retention-quality')
            ->assertOk();

        $postIds = array_column($response->json('data'), 'id');

        $this->assertLessThan(
            array_search($newSkippedReel->id, $postIds, true),
            array_search($oldLovedReel->id, $postIds, true)
        );
    }

    public function test_reels_seen_video_is_suppressed_after_app_reopen_but_topic_interest_remains(): void
    {
        $viewer = $this->createUser('reels-reopen-viewer');
        $author = $this->createUser('reels-reopen-author');

        $seenReel = $this->createVideoPost($author, 'Watched high retention reel #music', now(), [
            'comments_count' => 30,
            'bookmarks_count' => 30,
            'shares_count' => 30,
            'views_count' => 1000,
        ]);
        $alternativeReel = $this->createVideoPost($author, 'Related fresh replacement reel #music', now()->subMinutes(5), [
            'comments_count' => 1,
            'bookmarks_count' => 1,
            'shares_count' => 1,
            'views_count' => 20,
        ]);
        $media = $seenReel->media()->first();

        PostVideoMetric::query()->create([
            'post_id' => $seenReel->id,
            'media_id' => $media->id,
            'plays_count' => 60,
            'completions_count' => 54,
            'loops_count' => 12,
            'rewatches_count' => 16,
            'avg_completion_rate' => 1.10,
            'completion_rate' => 0.90,
            'skip_rate' => 0.02,
            'rewatch_rate' => 0.27,
            'intelligence_score' => 65,
        ]);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/timeline/telemetry/events', [
                'events' => [[
                    'event_type' => 'video_complete',
                    'post_id' => $seenReel->id,
                    'media_id' => $media->id,
                    'watch_time_seconds' => 12,
                    'duration_seconds' => 12,
                    'completion_rate' => 1,
                    'session_id' => 'reels-old-app-session',
                    'feed_type' => 'reels',
                    'source' => 'reels',
                    'position' => 1,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.accepted', 1);

        $feedResponse = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=reels&session_id=reels-new-app-session')
            ->assertOk();

        $feedIds = array_column($feedResponse->json('data'), 'id');

        $this->assertLessThan(
            array_search($seenReel->id, $feedIds, true),
            array_search($alternativeReel->id, $feedIds, true)
        );

        $seenPayload = collect($feedResponse->json('data'))->firstWhere('id', $seenReel->id);

        $this->assertLessThanOrEqual(-180, $seenPayload['meta']['ranking']['signals']['seen_penalty']);
        $this->assertGreaterThan(0, $this->interestScore($viewer, 'music'));
    }

    public function test_recently_seen_reels_stay_out_of_early_slots_when_unseen_pool_exists(): void
    {
        $viewer = $this->createUser('reels-unseen-pool-viewer');
        $seenAuthor = $this->createUser('reels-unseen-pool-seen-author');

        $seenReel = $this->createVideoPost($seenAuthor, 'Already watched hero reel #travel', now(), [
            'comments_count' => 40,
            'bookmarks_count' => 40,
            'shares_count' => 40,
            'views_count' => 4000,
        ]);
        $seenMedia = $seenReel->media()->first();

        PostVideoMetric::query()->create([
            'post_id' => $seenReel->id,
            'media_id' => $seenMedia->id,
            'plays_count' => 120,
            'completions_count' => 110,
            'loops_count' => 24,
            'rewatches_count' => 28,
            'avg_completion_rate' => 1.16,
            'completion_rate' => 0.91,
            'skip_rate' => 0.01,
            'rewatch_rate' => 0.23,
            'intelligence_score' => 65,
        ]);

        foreach(range(1, 14) as $index) {
            $author = $this->createUser("reels-unseen-pool-alt-{$index}");
            $this->createVideoPost($author, "Fresh unseen reel {$index} #travel", now()->subMinutes($index), [
                'comments_count' => 2,
                'bookmarks_count' => 2,
                'shares_count' => 1,
                'views_count' => 40 + $index,
            ]);
        }

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/timeline/telemetry/events', [
                'events' => [[
                    'event_type' => 'video_complete',
                    'post_id' => $seenReel->id,
                    'media_id' => $seenMedia->id,
                    'watch_time_seconds' => 11,
                    'duration_seconds' => 11,
                    'completion_rate' => 1,
                    'session_id' => 'reels-unseen-pool-old-session',
                    'feed_type' => 'reels',
                    'source' => 'reels',
                    'position' => 1,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.accepted', 1);

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=reels&session_id=reels-unseen-pool-new-session')
            ->assertOk();

        $postIds = array_column($response->json('data'), 'id');

        $this->assertNotContains($seenReel->id, array_slice($postIds, 0, 10));
    }

    public function test_reels_skip_event_suppresses_a_quickly_skipped_reel_after_reopen(): void
    {
        $viewer = $this->createUser('reels-skip-viewer');
        $author = $this->createUser('reels-skip-author');

        $skippedReel = $this->createVideoPost($author, 'Quickly skipped reel #sports', now(), [
            'comments_count' => 20,
            'bookmarks_count' => 20,
            'shares_count' => 20,
            'views_count' => 1800,
        ]);
        $replacementReel = $this->createVideoPost($author, 'Fresh replacement reel #sports', now()->subMinutes(4), [
            'comments_count' => 2,
            'bookmarks_count' => 1,
            'shares_count' => 1,
            'views_count' => 20,
        ]);
        $media = $skippedReel->media()->first();

        PostVideoMetric::query()->create([
            'post_id' => $skippedReel->id,
            'media_id' => $media->id,
            'plays_count' => 90,
            'completions_count' => 82,
            'loops_count' => 10,
            'rewatches_count' => 9,
            'avg_completion_rate' => 0.94,
            'completion_rate' => 0.83,
            'skip_rate' => 0.03,
            'rewatch_rate' => 0.10,
            'intelligence_score' => 58,
        ]);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/timeline/telemetry/events', [
                'events' => [[
                    'event_type' => 'video_skip',
                    'post_id' => $skippedReel->id,
                    'media_id' => $media->id,
                    'watch_time_seconds' => 0.2,
                    'duration_seconds' => 9,
                    'completion_rate' => 0.02,
                    'session_id' => 'reels-skip-old-session',
                    'feed_type' => 'reels',
                    'source' => 'reels',
                    'position' => 1,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.accepted', 1);

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=reels&session_id=reels-skip-new-session')
            ->assertOk();

        $postIds = array_column($response->json('data'), 'id');
        $skippedPayload = collect($response->json('data'))->firstWhere('id', $skippedReel->id);

        $this->assertDatabaseHas(Table::FEED_EVENTS, [
            'user_id' => $viewer->id,
            'post_id' => $skippedReel->id,
            'event_type' => 'video_skip',
        ]);
        $this->assertLessThan(
            array_search($skippedReel->id, $postIds, true),
            array_search($replacementReel->id, $postIds, true)
        );
        $this->assertLessThanOrEqual(-240, $skippedPayload['meta']['ranking']['signals']['seen_penalty']);
    }

    public function test_reels_ranking_limits_single_author_takeover_when_alternatives_exist(): void
    {
        $viewer = $this->createUser('reels-diversity-viewer');
        $dominantAuthor = $this->createUser('reels-dominant-author');

        $dominantIds = [];

        foreach(range(1, 4) as $index) {
            $post = $this->createVideoPost($dominantAuthor, "Dominant reel {$index} #tech", now()->subMinutes($index), [
                'comments_count' => 18,
                'bookmarks_count' => 18,
                'shares_count' => 18,
                'views_count' => 1000 + $index,
            ]);

            $dominantIds[] = $post->id;

            PostVideoMetric::query()->create([
                'post_id' => $post->id,
                'media_id' => $post->media()->value('id'),
                'plays_count' => 60,
                'completions_count' => 48,
                'loops_count' => 8,
                'rewatches_count' => 7,
                'avg_completion_rate' => 0.95,
                'completion_rate' => 0.80,
                'skip_rate' => 0.05,
                'rewatch_rate' => 0.12,
                'intelligence_score' => 59,
            ]);
        }

        foreach(range(1, 4) as $index) {
            $author = $this->createUser("reels-diversity-alt-{$index}");
            $post = $this->createVideoPost($author, "Alternative reel {$index} #tech", now()->subMinutes(10 + $index), [
                'comments_count' => 14,
                'bookmarks_count' => 14,
                'shares_count' => 14,
                'views_count' => 900 + $index,
            ]);

            PostVideoMetric::query()->create([
                'post_id' => $post->id,
                'media_id' => $post->media()->value('id'),
                'plays_count' => 58,
                'completions_count' => 44,
                'loops_count' => 7,
                'rewatches_count' => 6,
                'avg_completion_rate' => 0.92,
                'completion_rate' => 0.76,
                'skip_rate' => 0.06,
                'rewatch_rate' => 0.10,
                'intelligence_score' => 55,
            ]);
        }

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=reels&session_id=reels-author-diversity')
            ->assertOk();

        $postIds = array_column($response->json('data'), 'id');
        $topThreeDominantCount = count(array_intersect(array_slice($postIds, 0, 3), $dominantIds));

        $this->assertLessThanOrEqual(2, $topThreeDominantCount);
    }

    public function test_reels_not_interested_event_excludes_the_reel_and_records_negative_interest(): void
    {
        $viewer = $this->createUser('reels-not-interested-viewer');
        $author = $this->createUser('reels-not-interested-author');
        $alternateAuthor = $this->createUser('reels-not-interested-alt-author');

        $suppressedReel = $this->createVideoPost($author, 'Do not show me this reel #fashion', now(), [
            'comments_count' => 18,
            'bookmarks_count' => 14,
            'shares_count' => 12,
            'views_count' => 950,
        ]);
        $alternateReel = $this->createVideoPost($alternateAuthor, 'Fresh fashion alternative reel #fashion', now()->subMinutes(4), [
            'comments_count' => 4,
            'bookmarks_count' => 4,
            'shares_count' => 3,
            'views_count' => 140,
        ]);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/timeline/telemetry/events', [
                'events' => [[
                    'event_type' => 'post_not_interested',
                    'post_id' => $suppressedReel->id,
                    'session_id' => 'reels-not-interested-old-session',
                    'feed_type' => 'reels',
                    'source' => 'reels',
                    'position' => 1,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.accepted', 1);

        $this->assertDatabaseHas(Table::FEED_EVENTS, [
            'user_id' => $viewer->id,
            'post_id' => $suppressedReel->id,
            'event_type' => 'post_not_interested',
        ]);
        $this->assertLessThan(0, $this->interestScore($viewer, 'fashion'));

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=reels&session_id=reels-not-interested-new-session')
            ->assertOk();

        $postIds = array_column($response->json('data'), 'id');

        $this->assertContains($alternateReel->id, $postIds);
        $this->assertNotContains($suppressedReel->id, $postIds);
    }

    public function test_reels_hide_event_never_reintroduces_the_same_reel_in_fallback_slots(): void
    {
        $viewer = $this->createUser('reels-hide-viewer');
        $author = $this->createUser('reels-hide-author');
        $alternateAuthor = $this->createUser('reels-hide-alt-author');

        $hiddenReel = $this->createVideoPost($author, 'Hide this reel forever #travel', now(), [
            'comments_count' => 24,
            'bookmarks_count' => 20,
            'shares_count' => 18,
            'views_count' => 1200,
        ]);
        $remainingReel = $this->createVideoPost($alternateAuthor, 'Only remaining travel reel #travel', now()->subMinutes(6), [
            'comments_count' => 2,
            'bookmarks_count' => 2,
            'shares_count' => 1,
            'views_count' => 60,
        ]);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/timeline/telemetry/events', [
                'events' => [[
                    'event_type' => 'post_hide',
                    'post_id' => $hiddenReel->id,
                    'session_id' => 'reels-hide-old-session',
                    'feed_type' => 'reels',
                    'source' => 'reels',
                    'position' => 1,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.accepted', 1);

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=reels&session_id=reels-hide-new-session')
            ->assertOk();

        $postIds = array_column($response->json('data'), 'id');

        $this->assertContains($remainingReel->id, $postIds);
        $this->assertNotContains($hiddenReel->id, $postIds);
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
