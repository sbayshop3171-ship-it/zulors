<?php

namespace Tests\Feature;

use App\Database\Configs\Table;
use App\Enums\Ad\AdApproval;
use App\Enums\Ad\AdStatus;
use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Enums\Media\MediaVisibility;
use App\Enums\NotificationType;
use App\Enums\Post\PostStatus;
use App\Enums\Post\PostType;
use App\Enums\Report\ReportType;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Enums\Wallet\TransactionType;
use App\Enums\Wallet\TransactionStatus;
use App\Enums\Wallet\TransactionDirection;
use App\Models\Ad;
use App\Models\AdImpression;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Actions\Ad\AdShowAction;
use App\Actions\Ad\DeleteAdAction;
use App\Livewire\Business\Ads\Upsert as AdUpsert;
use App\Models\UserNotificationSettings;
use App\Services\Timeline\UserInterestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class VideoSafetyAdsTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_watch_events_update_metrics_and_change_for_you_ranking(): void
    {
        $viewer = $this->createUser('video-viewer');
        $author = $this->createUser('video-author');

        $skippedPost = $this->createPost($author, 'Skipped video #clips', now(), ['type' => PostType::VIDEO]);
        $skippedMedia = $this->createVideoMedia($skippedPost, 10);

        $loopedPost = $this->createPost($author, 'Looped video #clips', now()->subHour(), ['type' => PostType::VIDEO]);
        $loopedMedia = $this->createVideoMedia($loopedPost, 10);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/timeline/telemetry/events', [
                'events' => [[
                    'event_type' => 'video_watch',
                    'post_id' => $skippedPost->id,
                    'media_id' => $skippedMedia->id,
                    'watch_time_seconds' => 3,
                    'duration_seconds' => 10,
                    'completion_rate' => 0.3,
                    'session_id' => 'skip-session',
                ]]
            ])
            ->assertOk()
            ->assertJsonPath('data.accepted', 1);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/timeline/telemetry/events', [
                'events' => [[
                    'event_type' => 'video_watch',
                    'post_id' => $loopedPost->id,
                    'media_id' => $loopedMedia->id,
                    'watch_time_seconds' => 22,
                    'duration_seconds' => 10,
                    'completion_rate' => 2.2,
                    'loop_count' => 1,
                    'session_id' => 'loop-session',
                ]]
            ])
            ->assertOk()
            ->assertJsonPath('data.accepted', 1);

        $this->assertDatabaseHas(Table::FEED_EVENTS, [
            'post_id' => $skippedPost->id,
            'event_type' => 'video_skip',
        ]);

        $this->assertDatabaseHas(Table::FEED_EVENTS, [
            'post_id' => $loopedPost->id,
            'event_type' => 'video_loop',
        ]);

        $this->assertDatabaseHas(Table::POST_VIDEO_METRICS, [
            'post_id' => $skippedPost->id,
            'skips_count' => 1,
        ]);

        $this->assertDatabaseHas(Table::POST_VIDEO_METRICS, [
            'post_id' => $loopedPost->id,
            'completions_count' => 1,
            'rewatches_count' => 1,
        ]);

        $feedResponse = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=for_you')
            ->assertOk();

        $feedIds = array_column($feedResponse->json('data'), 'id');

        $this->assertLessThan(
            array_search($skippedPost->id, $feedIds, true),
            array_search($loopedPost->id, $feedIds, true)
        );

        $loopedPayload = collect($feedResponse->json('data'))->firstWhere('id', $loopedPost->id);

        $this->assertGreaterThan(0, $loopedPayload['meta']['ranking']['signals']['video_intelligence']);
    }

    public function test_spam_burst_freezes_posting_and_report_updates_safety_penalty(): void
    {
        $spammer = $this->createUser('spam-author');

        foreach(range(1, 10) as $index) {
            $this->actingAs($spammer)
                ->withoutMiddleware()
                ->postJson('/api/post/editor/create', [
                    'content' => "Burst post {$index}",
                ])
                ->assertOk();
        }

        $this->assertDatabaseHas(Table::USER_SAFETY_SCORES, [
            'user_id' => $spammer->id,
            'post_burst_count' => 10,
        ]);

        $this->actingAs($spammer)
            ->withoutMiddleware()
            ->postJson('/api/post/editor/create', [
                'content' => 'Blocked burst post',
            ])
            ->assertStatus(429);

        $reporter = $this->createUser('safety-reporter');
        $reportedAuthor = $this->createUser('reported-author');
        $reportedPost = $this->createPost($reportedAuthor, 'Reported unsafe post #risk');

        $this->actingAs($reporter)
            ->withoutMiddleware()
            ->postJson('/api/feedback/report/send', [
                'type' => ReportType::POST->value,
                'reason_index' => 0,
                'reportable_id' => $reportedPost->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas(Table::USER_SAFETY_SCORES, [
            'user_id' => $reportedAuthor->id,
            'content_reports_count' => 1,
        ]);

        $feedResponse = $this->actingAs($reporter)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=for_you')
            ->assertOk();

        $reportedPayload = collect($feedResponse->json('data'))->firstWhere('id', $reportedPost->id);

        $this->assertLessThan(0, $reportedPayload['meta']['ranking']['signals']['safety_penalty']);
    }

    public function test_ads_use_interest_targeting_and_frequency_cap(): void
    {
        $viewer = $this->createUser('ad-viewer');

        app(UserInterestService::class)->applyScore($viewer->id, 'tech', 60);

        $techAd = $this->createAd('Tech launch', ['tech']);
        $sportsAd = $this->createAd('Sports sale', ['sports']);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/ads/ad')
            ->assertOk()
            ->assertJsonPath('data.id', $techAd->id);

        $this->actingAs($viewer)->withoutMiddleware()->getJson('/api/ads/ad')->assertOk()->assertJsonPath('data.id', $techAd->id);
        $this->actingAs($viewer)->withoutMiddleware()->getJson('/api/ads/ad')->assertOk()->assertJsonPath('data.id', $techAd->id);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/ads/ad')
            ->assertOk()
            ->assertJsonPath('data.id', $sportsAd->id);

        $this->assertSame(3, AdImpression::query()
            ->where('ad_id', $techAd->id)
            ->where('fingerprint', "user:{$viewer->id}")
            ->value('impressions_count'));
    }

    public function test_business_ad_form_saves_bid_target_topics_and_allocates_budget(): void
    {
        $advertiser = $this->createUser('campaign-owner');
        $this->createWallet($advertiser, 50);
        $ad = $advertiser->advertising()->create([
            'status' => AdStatus::DRAFT,
        ]);

        $this->createAdMedia($ad);

        $this->actingAs($advertiser);

        Livewire::test(AdUpsert::class, [
            'adData' => $ad,
            'upsertType' => 'create',
        ])
            ->set('formData.title', 'AI creator campaign')
            ->set('formData.content', 'A practical offer for creators who want better AI tools, workflow templates, and faster publishing results.')
            ->set('formData.cta_text', 'Open now')
            ->set('formData.total_budget', 10)
            ->set('formData.price_per_view', 0.05)
            ->set('formData.target_topics', '#Tech, AI, laravel, tech')
            ->set('formData.target_url', 'https://example.com/ad')
            ->call('submitForm')
            ->assertRedirect(route('business.ads.index'));

        $ad->refresh();
        $advertiser->wallet->refresh();

        $this->assertSame(AdStatus::PUBLISHED, $ad->status);
        $this->assertEquals(10.00, (float) $ad->total_budget);
        $this->assertEquals(0.05, (float) $ad->price_per_view);
        $this->assertSame(['tech', 'ai', 'laravel'], $ad->target_topics);
        $this->assertEquals(40.00, $advertiser->wallet->balance->getAmount());

        $this->assertDatabaseHas(Table::WALLET_TRANSACTIONS, [
            'wallet_id' => $advertiser->wallet->id,
            'amount' => 10,
            'transaction_type' => TransactionType::ADVERTISING->value,
            'direction' => TransactionDirection::OUTGOING->value,
            'status' => TransactionStatus::COMPLETED->value,
        ]);
    }

    public function test_ad_delivery_charges_bid_without_overspending_and_completes(): void
    {
        $ad = $this->createAd('Capped tech campaign', ['tech'], [
            'total_budget' => 0.05,
            'price_per_view' => 0.03,
        ]);

        (new AdShowAction($ad))->execute();

        $ad->refresh();

        $this->assertEquals(0.03, (float) $ad->spent_budget);
        $this->assertSame(AdStatus::PUBLISHED, $ad->status);
        $this->assertSame(1, $ad->views_count);

        $ad->forceFill([
            'last_charge_at' => now()->subMinutes(config('ads.charge_interval') + 1),
        ])->save();

        (new AdShowAction($ad->refresh()))->execute();

        $ad->refresh();

        $this->assertEquals(0.05, (float) $ad->spent_budget);
        $this->assertSame(AdStatus::COMPLETED, $ad->status);
        $this->assertSame(2, $ad->views_count);
    }

    public function test_ad_clicks_are_tracked_and_redirect_to_target_url(): void
    {
        $viewer = $this->createUser('ad-click-viewer');
        $ad = $this->createAd('Tracked click campaign', ['tech']);

        $this->actingAs($viewer)
            ->get("/api/ads/click/{$ad->id}")
            ->assertRedirect('https://example.com');

        $this->assertDatabaseHas(Table::ADS, [
            'id' => $ad->id,
            'clicks_count' => 1,
        ]);

        $this->assertDatabaseHas(Table::AD_IMPRESSIONS, [
            'ad_id' => $ad->id,
            'fingerprint' => "user:{$viewer->id}",
            'clicks_count' => 1,
        ]);
    }

    public function test_unused_ad_budget_is_refunded_when_campaign_is_deleted(): void
    {
        $advertiser = $this->createUser('refund-campaign-owner');
        $this->createWallet($advertiser, 0);
        $ad = $this->createAd('Refundable campaign', ['tech'], [
            'owner' => $advertiser,
            'total_budget' => 10,
            'spent_budget' => 3,
        ]);

        (new DeleteAdAction($ad))->execute();

        $advertiser->wallet->refresh();

        $this->assertDatabaseMissing(Table::ADS, [
            'id' => $ad->id,
        ]);

        $this->assertEquals(7.00, $advertiser->wallet->balance->getAmount());

        $this->assertDatabaseHas(Table::WALLET_TRANSACTIONS, [
            'wallet_id' => $advertiser->wallet->id,
            'amount' => 7,
            'transaction_type' => TransactionType::REFUND->value,
            'direction' => TransactionDirection::INCOMING->value,
            'status' => TransactionStatus::COMPLETED->value,
        ]);
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

    private function createVideoMedia(Post $post, int $durationSeconds): Media
    {
        return $post->media()->create([
            'source_path' => "posts/videos/{$post->id}.mp4",
            'thumbnail_path' => "posts/video_thumbnails/{$post->id}.jpg",
            'type' => MediaType::VIDEO,
            'status' => MediaStatus::PROCESSED,
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
                'is_portrait' => false,
            ],
        ]);
    }

    private function createAd(string $title, array $targetTopics, array $overrides = []): Ad
    {
        $owner = $overrides['owner'] ?? $this->createUser(strtolower(str_replace(' ', '-', $title)) . '-owner');
        unset($overrides['owner']);

        return Ad::query()->create(array_merge([
            'user_id' => $owner->id,
            'title' => $title,
            'content' => "{$title} content for testing targeted delivery.",
            'cta_text' => 'Open',
            'status' => AdStatus::PUBLISHED,
            'type' => 'image',
            'total_budget' => 100,
            'spent_budget' => 0,
            'price_per_view' => 0.01,
            'target_url' => 'https://example.com',
            'target_topics' => $targetTopics,
            'approval' => AdApproval::APPROVED,
            'views_count' => 0,
            'clicks_count' => 0,
        ], $overrides));
    }

    private function createWallet(User $user, float $balance): void
    {
        $user->wallet()->create([
            'wallet_number' => 'ZLR-TEST-' . strtoupper($user->username),
            'balance' => $balance,
            'currency' => config('app.default_currency'),
        ]);
    }

    private function createAdMedia(Ad $ad): Media
    {
        return $ad->media()->create([
            'source_path' => "ads/creatives/{$ad->id}.jpg",
            'type' => MediaType::IMAGE,
            'status' => MediaStatus::PROCESSED,
            'disk' => 'public',
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => '100',
            'metadata' => [],
        ]);
    }
}
