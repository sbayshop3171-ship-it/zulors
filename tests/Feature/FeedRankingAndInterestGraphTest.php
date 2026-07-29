<?php

namespace Tests\Feature;

use App\Database\Configs\Table;
use App\Enums\Post\PostStatus;
use App\Enums\Post\PostType;
use App\Enums\NotificationType;
use App\Enums\Report\ReportType;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Models\Bookmark;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\UserNotificationSettings;
use App\Services\Timeline\CandidateGenerationService;
use App\Services\Timeline\TopicExtractionService;
use App\Services\Timeline\UserInterestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
