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

    public function test_ad_delivery_respects_requested_placement_and_schedule(): void
    {
        $viewer = $this->createUser('placement-viewer');
        $feedAd = $this->createAd('Feed placement ad', [], [
            'placement_flags' => ['feed'],
            'start_at' => now()->subMinute(),
            'end_at' => now()->addMinute(),
        ]);
        $expiredAd = $this->createAd('Expired feed ad', [], [
            'placement_flags' => ['feed'],
            'end_at' => now()->subMinute(),
        ]);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/ads/ad?placement=feed')
            ->assertOk()
            ->assertJsonPath('data.id', $feedAd->id)
            ->assertJsonPath('data.placement_flags.0', 'feed');

        $this->assertDatabaseHas('ad_impressions', [
            'ad_id' => $feedAd->id,
            'placement' => 'feed',
            'device' => 'desktop',
        ]);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/ads/ad?placement=reels')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('ad_impressions', ['ad_id' => $expiredAd->id]);
    }

    public function test_home_feed_injects_native_sponsored_item_without_replacing_posts(): void
    {
        $viewer = $this->createUser('native-feed-viewer');
        $author = $this->createUser('native-feed-author');

        foreach(range(1, 4) as $index) {
            $this->createPost($author, "Organic feed post {$index}", now()->subMinutes($index));
        }

        $ad = $this->createAd('Native feed campaign', [], [
            'owner' => $author,
            'placement_flags' => ['feed'],
        ]);
        $this->createAdMedia($ad);

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=for_you&candidate_limit=20')
            ->assertOk();

        $data = $response->json('data');
        $this->assertContains('ad', array_column($data, 'type'));
        $this->assertSame($ad->id, collect($data)->firstWhere('type', 'ad')['ad']['id']);
        $this->assertGreaterThanOrEqual(4, count(array_filter($data, fn($item) => $item['type'] !== 'ad')));
        $this->assertDatabaseHas('ad_impressions', [
            'ad_id' => $ad->id,
            'placement' => 'feed',
        ]);
    }

    public function test_reels_feed_injects_only_reels_placement_ads(): void
    {
        $viewer = $this->createUser('native-reels-viewer');
        $author = $this->createUser('native-reels-author');
        $this->createPost($author, 'Organic reel', now(), ['type' => PostType::VIDEO]);

        $reelsAd = $this->createAd('Native reels campaign', [], [
            'owner' => $author,
            'placement_flags' => ['reels'],
        ]);
        $this->createAdMedia($reelsAd);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=reels')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'ad')
            ->assertJsonPath('data.0.ad.id', $reelsAd->id)
            ->assertJsonPath('data.0.meta.placement', 'reels');

        $this->assertDatabaseHas('ad_impressions', [
            'ad_id' => $reelsAd->id,
            'placement' => 'reels',
        ]);
    }

    public function test_no_button_ad_has_no_resolvable_destination(): void
    {
        $ad = $this->createAd('No button campaign', [], ['cta_type' => 'NO_BUTTON']);

        $this->assertNull(app(\App\Services\Ad\AdDestinationResolver::class)->resolve($ad));
    }

    public function test_business_ad_form_allows_no_button_without_target_url(): void
    {
        $advertiser = $this->createUser('no-button-owner');
        $this->createWallet($advertiser, 50);
        $ad = $advertiser->advertising()->create(['status' => AdStatus::DRAFT]);
        $this->createAdMedia($ad);

        Livewire::actingAs($advertiser)
            ->test(AdUpsert::class, ['adData' => $ad, 'upsertType' => 'create'])
            ->set('formData.title', 'No button campaign')
            ->set('formData.content', 'A campaign without a call to action destination.')
            ->set('formData.cta_type', 'NO_BUTTON')
            ->set('formData.cta_text', 'Send Message')
            ->set('formData.target_url', '')
            ->set('formData.total_budget', 10)
            ->set('formData.price_per_view', 0.05)
            ->call('submitForm')
            ->assertRedirect(route('business.ads.index'));

        $this->assertSame('NO_BUTTON', $ad->refresh()->cta_type);
        $this->assertNull($ad->target_url);
    }

    public function test_profile_destination_uses_profile_url_before_target_url(): void
    {
        $owner = $this->createUser('profile-destination-owner');
        $ad = $this->createAd('Profile campaign', [], [
            'owner' => $owner,
            'destination_type' => 'profile',
            'target_url' => 'https://fallback.example/profile',
        ]);

        $this->assertSame(
            $owner->profile_url,
            app(\App\Services\Ad\AdDestinationResolver::class)->resolve($ad)
        );
    }

    public function test_ad_event_endpoint_records_reels_watch_metrics(): void
    {
        $viewer = $this->createUser('ad-event-viewer');
        $ad = $this->createAd('Tracked reels campaign', [], ['placement_flags' => ['reels']]);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson("/api/ads/event/{$ad->id}?placement=reels", [
                'event_type' => 'completed_view',
                'watch_time_seconds' => 9.5,
                'completion_rate' => 1,
                'session_id' => 'reels-session',
            ])
            ->assertOk();

        $this->assertDatabaseHas('ad_events', [
            'ad_id' => $ad->id,
            'event_type' => 'completed_view',
            'placement' => 'reels',
            'watch_time_seconds' => 9.5,
        ]);
    }

    public function test_business_ad_form_submits_pending_campaign_without_allocating_budget(): void
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
        $this->assertSame(AdApproval::PENDING, $ad->approval);
        $this->assertEquals(10.00, (float) $ad->total_budget);
        $this->assertEquals(0.05, (float) $ad->price_per_view);
        $this->assertSame(['tech', 'ai', 'laravel'], $ad->target_topics);
        $this->assertNull($ad->funding_metadata);
        $this->assertNull($ad->pause_reason);
        $this->assertEquals(50.00, $advertiser->wallet->balance->getAmount());

        $this->assertDatabaseMissing(Table::WALLET_TRANSACTIONS, [
            'wallet_id' => $advertiser->wallet->id,
            'amount' => 10,
            'transaction_type' => TransactionType::ADVERTISING->value,
            'direction' => TransactionDirection::OUTGOING->value,
            'status' => TransactionStatus::COMPLETED->value,
        ]);
    }

    public function test_business_ad_form_rejects_malformed_target_url(): void
    {
        $advertiser = $this->createUser('malformed-url-owner');
        $this->createWallet($advertiser, 50);
        $ad = $advertiser->advertising()->create([
            'status' => AdStatus::DRAFT,
        ]);

        $this->createAdMedia($ad);

        Livewire::actingAs($advertiser)
            ->test(AdUpsert::class, [
                'adData' => $ad,
                'upsertType' => 'create',
            ])
            ->set('formData.title', 'Malformed URL campaign')
            ->set('formData.content', 'A practical offer for users who want a clear destination URL.')
            ->set('formData.cta_text', 'Learn More')
            ->set('formData.total_budget', 10)
            ->set('formData.price_per_view', 0.05)
            ->set('formData.target_url', 'createhttp://127.0.0.1:8765')
            ->call('submitForm')
            ->assertHasErrors(['formData.target_url']);

        $this->assertNull($ad->refresh()->target_url);
    }

    public function test_business_ad_form_persists_structured_campaign_fields(): void
    {
        $advertiser = $this->createUser('structured-campaign-owner');
        $this->createWallet($advertiser, 50);
        $ad = $advertiser->advertising()->create(['status' => AdStatus::DRAFT]);
        $this->createAdMedia($ad);

        Livewire::actingAs($advertiser)
            ->test(AdUpsert::class, ['adData' => $ad, 'upsertType' => 'create'])
            ->set('formData.title', 'Structured campaign title')
            ->set('formData.content', 'A practical offer for users who want a structured campaign destination.')
            ->set('formData.cta_text', 'Learn More')
            ->set('formData.cta_type', 'LEARN_MORE')
            ->set('formData.destination_type', 'external_url')
            ->set('formData.objective', 'offer')
            ->set('formData.placement_flags', ['feed', 'reels'])
            ->set('formData.frequency_cap', 2)
            ->set('formData.target_url', 'https://example.com/offer')
            ->set('formData.total_budget', 10)
            ->set('formData.price_per_view', 0.05)
            ->call('submitForm')
            ->assertRedirect(route('business.ads.index'));

        $ad->refresh();

        $this->assertSame('offer', $ad->objective);
        $this->assertSame(['feed', 'reels'], $ad->placement_flags);
        $this->assertSame('LEARN_MORE', $ad->cta_type);
        $this->assertSame('external_url', $ad->destination_type);
        $this->assertSame(2, $ad->frequency_cap);
    }

    public function test_business_ad_form_can_boost_existing_post_without_creative_fields(): void
    {
        $advertiser = $this->createUser('boost-post-owner');
        $this->createWallet($advertiser, 0);
        $post = $this->createPost($advertiser, 'Boost this post for a seasonal sale #offers', now(), [
            'title' => 'Seasonal sale post',
            'type' => PostType::IMAGE,
        ]);
        $this->createImageMedia($post);
        $ad = $advertiser->advertising()->create([
            'status' => AdStatus::DRAFT,
        ]);

        $this->actingAs($advertiser);

        Livewire::test(AdUpsert::class, [
            'adData' => $ad,
            'upsertType' => 'create',
        ])
            ->call('setSourceType', 'post')
            ->set('formData.source_post_id', $post->id)
            ->set('formData.cta_text', 'Shop Now')
            ->set('formData.target_url', $post->url)
            ->set('formData.total_budget', 10)
            ->set('formData.price_per_view', 0.05)
            ->set('formData.target_topics', '#sale')
            ->call('submitForm')
            ->assertRedirect(route('business.ads.index'));

        $ad->refresh();

        $this->assertSame('post', $ad->source_type);
        $this->assertSame($post->id, $ad->source_post_id);
        $this->assertSame('Seasonal sale post', $ad->title);
        $this->assertSame('image', $ad->type);
        $this->assertSame('Shop Now', $ad->cta_text);
        $this->assertCount(0, $ad->media);
    }

    public function test_boost_post_picker_only_allows_current_users_active_supported_posts(): void
    {
        $advertiser = $this->createUser('boost-picker-owner');
        $otherUser = $this->createUser('boost-picker-other');
        $ownPost = $this->createPost($advertiser, 'Own active boostable post', now(), [
            'type' => PostType::TEXT,
        ]);
        $otherPost = $this->createPost($otherUser, 'Other user post should not appear', now(), [
            'type' => PostType::TEXT,
        ]);
        $draftPost = $this->createPost($advertiser, 'Draft post should not appear', now(), [
            'type' => PostType::TEXT,
            'status' => PostStatus::DRAFT,
        ]);
        $ad = $advertiser->advertising()->create([
            'status' => AdStatus::DRAFT,
        ]);

        $this->actingAs($advertiser);

        Livewire::test(AdUpsert::class, [
            'adData' => $ad,
            'upsertType' => 'create',
        ])
            ->call('setSourceType', 'post')
            ->assertSee('Own active boostable post')
            ->assertDontSee('Other user post should not appear')
            ->assertDontSee('Draft post should not appear')
            ->set('formData.source_post_id', $otherPost->id)
            ->set('formData.cta_text', 'Learn More')
            ->set('formData.target_url', $otherPost->url)
            ->set('formData.total_budget', 10)
            ->set('formData.price_per_view', 0.05)
            ->call('submitForm')
            ->assertHasErrors(['formData.source_post_id']);
    }

    public function test_post_sourced_ad_is_not_served_when_source_post_is_unavailable(): void
    {
        $viewer = $this->createUser('unavailable-source-viewer');
        $advertiser = $this->createUser('unavailable-source-owner');
        $post = $this->createPost($advertiser, 'Deleted source post', now(), [
            'status' => PostStatus::DELETED,
            'type' => PostType::TEXT,
        ]);
        $this->createAd('Unavailable post ad', ['tech'], [
            'owner' => $advertiser,
            'source_type' => 'post',
            'source_post_id' => $post->id,
        ]);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/ads/ad')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_ad_api_returns_post_video_media_payload(): void
    {
        $viewer = $this->createUser('post-video-ad-viewer');
        $advertiser = $this->createUser('post-video-ad-owner');
        $post = $this->createPost($advertiser, 'Watch the product demo video', now(), [
            'title' => 'Product demo',
            'type' => PostType::VIDEO,
        ]);
        $this->createVideoMedia($post, 12);
        $ad = $this->createAd('Video boost ad', ['tech'], [
            'owner' => $advertiser,
            'source_type' => 'post',
            'source_post_id' => $post->id,
            'title' => 'Product demo',
            'content' => $post->content,
            'type' => 'video',
        ]);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/ads/ad')
            ->assertOk()
            ->assertJsonPath('data.id', $ad->id)
            ->assertJsonPath('data.source_type', 'post')
            ->assertJsonPath('data.source_post_id', $post->id)
            ->assertJsonPath('data.media_type', 'video')
            ->assertJsonPath('data.title', 'Product demo');
    }

    public function test_admin_approval_pauses_campaign_when_ad_funds_are_unavailable(): void
    {
        $advertiser = $this->createUser('approval-zero-owner');
        $this->createWallet($advertiser, 0);
        $ad = $this->createAd('Needs funding', ['tech'], [
            'owner' => $advertiser,
            'approval' => AdApproval::PENDING,
            'total_budget' => 25,
            'funding_metadata' => null,
        ]);

        $this->withoutMiddleware()
            ->post(route('admin.ads.approve', $ad->id))
            ->assertRedirect();

        $ad->refresh();

        $this->assertSame(AdApproval::APPROVED, $ad->approval);
        $this->assertSame(AdStatus::PAUSED, $ad->status);
        $this->assertSame('insufficient_funds', $ad->pause_reason);
        $this->assertNull($ad->funding_metadata);
        $this->assertEquals(0.00, $advertiser->wallet->refresh()->balance->getAmount());
    }

    public function test_user_can_resume_unfunded_paused_campaign_after_deposit(): void
    {
        $advertiser = $this->createUser('resume-funded-owner');
        $this->createWallet($advertiser, 0);
        $ad = $this->createAd('Resume funding', ['tech'], [
            'owner' => $advertiser,
            'status' => AdStatus::PAUSED,
            'approval' => AdApproval::APPROVED,
            'total_budget' => 30,
            'pause_reason' => 'insufficient_funds',
            'funding_metadata' => null,
        ]);

        $advertiser->wallet()->update(['balance' => 30]);

        $this->actingAs($advertiser)
            ->withoutMiddleware()
            ->post(route('business.ads.publish', $ad->id))
            ->assertRedirect(route('business.ads.show', $ad->id));

        $ad->refresh();

        $this->assertSame(AdStatus::PUBLISHED, $ad->status);
        $this->assertNull($ad->pause_reason);
        $this->assertEquals(0.00, (float) data_get($ad->funding_metadata, 'reward_amount'));
        $this->assertEquals(30.00, (float) data_get($ad->funding_metadata, 'cash_amount'));
        $this->assertEquals(0.00, $advertiser->wallet->refresh()->balance->getAmount());
    }

    public function test_admin_approval_allocates_reward_credit_before_cash(): void
    {
        config([
            'wallet.ads_reward.enabled' => true,
            'wallet.ads_reward.monthly_amount' => 300,
        ]);

        $advertiser = $this->createUser('approval-reward-owner');
        $advertiser->update([
            'verified' => true,
            'verified_at' => now(),
        ]);
        $this->createWallet($advertiser, 550);
        $ad = $this->createAd('Reward mixed funding', ['tech'], [
            'owner' => $advertiser,
            'approval' => AdApproval::PENDING,
            'total_budget' => 400,
            'funding_metadata' => null,
        ]);

        $this->withoutMiddleware()
            ->post(route('admin.ads.approve', $ad->id))
            ->assertRedirect();

        $ad->refresh();

        $this->assertSame(AdApproval::APPROVED, $ad->approval);
        $this->assertSame(AdStatus::PUBLISHED, $ad->status);
        $this->assertNull($ad->pause_reason);
        $this->assertEquals(300.00, (float) data_get($ad->funding_metadata, 'reward_amount'));
        $this->assertEquals(100.00, (float) data_get($ad->funding_metadata, 'cash_amount'));
        $this->assertEquals(450.00, $advertiser->wallet->refresh()->balance->getAmount());
        $this->assertDatabaseHas(Table::AD_REWARD_ACCOUNTS, [
            'user_id' => $advertiser->id,
            'available_amount' => 0,
        ]);
    }

    public function test_paused_campaign_is_not_served_by_ads_api(): void
    {
        $viewer = $this->createUser('paused-ad-viewer');

        $this->createAd('Paused campaign', ['tech'], [
            'status' => AdStatus::PAUSED,
            'approval' => AdApproval::APPROVED,
            'pause_reason' => 'insufficient_funds',
            'funding_metadata' => null,
        ]);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/ads/ad')
            ->assertOk()
            ->assertJsonPath('data', null);
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

    public function test_ad_resource_preserves_legacy_cta_and_click_placement(): void
    {
        $viewer = $this->createUser('ad-resource-viewer');
        $ad = $this->createAd('Legacy resource campaign', [], [
            'cta_type' => null,
            'cta_text' => 'Visit website',
            'target_url' => 'https://example.com/legacy',
            'placement_flags' => ['feed'],
        ]);
        $this->createAdMedia($ad);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/ads/ad?placement=feed')
            ->assertOk()
            ->assertJsonPath('data.cta_type', 'VISIT_WEBSITE')
            ->assertJsonPath('data.click_url', url("/api/ads/click/{$ad->id}?placement=feed"));
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

    private function createImageMedia(Post $post): Media
    {
        return $post->media()->create([
            'source_path' => "posts/images/{$post->id}.jpg",
            'type' => MediaType::IMAGE,
            'status' => MediaStatus::PROCESSED,
            'disk' => 'public',
            'extension' => 'jpg',
            'visibility' => MediaVisibility::VISIBLE,
            'mime' => 'image/jpeg',
            'size' => '100',
            'metadata' => [],
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
            'currency' => config('app.default_currency') ?: 'USD',
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
