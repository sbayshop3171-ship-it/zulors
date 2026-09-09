<?php

namespace Tests\Feature;

use App\Enums\BlacklistType;
use App\Enums\CensorLevel;
use App\Enums\Chat\ChatType;
use App\Enums\Media\MediaStatus;
use App\Enums\Post\PostStatus;
use App\Enums\Story\StoryPrivacy;
use App\Enums\Story\StoryStatus;
use App\Enums\User\FollowStatus;
use App\Enums\User\PrivacyPermit;
use App\Enums\User\UserStatus;
use App\Events\Admin\User\UserBannedEvent;
use App\Events\User\Chat\MessageReceivedEvent;
use App\Events\User\Story\StoryCreatedEvent;
use App\Events\User\Timeline\PostCreatedEvent;
use App\Events\User\Timeline\PublicTimelinePostCreatedEvent;
use App\Jobs\Media\DeliverPublicationEvent;
use App\Jobs\User\Story\ProcessStoryVideo;
use App\Jobs\User\Timeline\ConvertAndCompressPostVideo;
use App\Listeners\User\Story\HandleStoryCreation;
use App\Listeners\User\Timeline\HandlePostCreation;
use App\Models\Chat;
use App\Models\Block;
use App\Models\Blacklist;
use App\Models\Censor;
use App\Models\Follow;
use App\Models\Media;
use App\Models\MediaPublication;
use App\Models\Message;
use App\Models\Post;
use App\Models\StoryFrame;
use App\Models\User;
use App\Models\UserPushToken;
use App\Notifications\User\Chat\MessageReceivedNotification;
use App\Services\Censor\CensorService;
use App\Services\Media\Publication\PublicationFinalizer;
use App\Services\Media\Publication\PublicationService;
use App\Services\Media\Cloudflare\R2DirectUploadService;
use App\Services\Notifications\FirebaseCloudMessagingService;
use App\Services\Safety\SafetyService;
use App\Services\Timeline\TopicExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PublicationFinalizerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        config(['notifications.push.enabled' => false, 'notifications.email.enabled' => false]);
    }

    public function test_waits_for_every_attachment_and_then_publishes_once_with_all_metadata_and_counters(): void
    {
        $actor = $this->user();
        $quote = Post::create(['user_id' => $actor->id, 'content' => 'Quote', 'status' => PostStatus::ACTIVE]);
        $publication = $this->publication($actor, 'post', ['image', 'image'], [
            'content' => '#Photography <b>two images</b>', 'quoted_post_id' => $quote->id,
            'marks' => ['is_sensitive' => true, 'is_ai_generated' => true],
        ]);
        $last = $publication->items()->orderByDesc('position')->first();
        $last->update(['status' => 'processing']);

        $this->assertSame('processing', $this->finalize($publication)->status);
        $this->assertSame(1, Post::count());
        $this->assertSame(0, Media::count());
        $this->assertSame(0, DB::table('media_publication_events')->count());

        $last->update(['status' => 'processed']);
        $published = $this->finalize($publication);
        $this->assertSame('published', $published->status);
        $this->assertSame($published->result, $this->finalize($publication)->result);
        $post = Post::with('media')->findOrFail($published->result['id']);
        $this->assertSame('post', $published->result['type']);
        $this->assertSame($post->url, $published->result['url']);
        $this->assertSame(PostStatus::ACTIVE, $post->status);
        $this->assertTrue($post->is_sensitive);
        $this->assertTrue((bool) $post->is_ai_generated);
        $this->assertStringContainsString('&lt;b&gt;', $post->content);
        $this->assertSame(2, $post->media->count());
        foreach ($post->media->sortBy('order')->values() as $position => $media) {
            $this->assertSame(MediaStatus::PROCESSED, $media->status);
            $this->assertSame('image/webp', $media->mime);
            $this->assertSame('webp', $media->extension);
            $this->assertSame('public', $media->disk);
            $this->assertSame(1234, (int) $media->size);
            $this->assertSame('ready/'.$position.'.webp', $media->source_path);
            $this->assertSame(600, data_get($media->metadata, 'dimensions.width'));
            $this->assertSame('kept', data_get($media->metadata, 'custom'));
            $this->assertSame($publication->id, data_get($media->metadata, 'publication_id'));
        }
        $this->assertSame(1, (int) $actor->fresh()->publications_count);
        $this->assertSame(1, (int) $quote->fresh()->quotes_count);
        $this->assertSame(['photography'], $post->topics()->pluck('topic')->all());
        $this->assertSame(3, DB::table('media_publication_events')->count());
        Bus::assertDispatchedTimes(DeliverPublicationEvent::class, 3);
        $this->assertNull(Auth::user());
    }

    public function test_empty_or_incomplete_processed_output_cannot_create_a_visible_entity(): void
    {
        $publication = $this->publication($this->user());
        $item = $publication->items()->first();
        $item->update(['output' => []]);
        $this->assertSame('processing', $this->finalize($publication)->status);
        $item->update(['output' => ['source_path' => 'ready/file.webp']]);
        $this->assertSame('invalid_processed_output', $this->finalize($publication)->error);
        $this->assertSame(0, Post::count());
        $this->assertSame(0, Media::count());
    }

    public function test_rejects_unsupported_privacy_and_invalid_attachment_combinations(): void
    {
        $actor = $this->user();
        foreach (['post', 'story'] as $kind) {
            foreach (['followers', 'selected_users'] as $privacy) {
                $publication = $this->publication($actor, $kind, ['image'], ['privacy' => $privacy]);
                $this->assertSame('unsupported_privacy', $this->finalize($publication)->error);
            }
        }
        foreach ([['post', ['image', 'video']], ['post', ['video', 'video']], ['post', array_fill(0, 11, 'image')], ['story', ['image', 'image']], ['chat', ['image', 'image']]] as [$kind, $types]) {
            $publication = $this->publication($actor, $kind, $types);
            $this->assertSame('invalid_attachments', $this->finalize($publication)->error);
        }
        $this->assertSame(0, Post::count() + StoryFrame::count() + Message::count());
        $this->assertSame(0, DB::table('media_publication_events')->count());
    }

    public function test_rechecks_actor_activity_and_safety_after_processing(): void
    {
        $actor = $this->user();
        $publication = $this->publication($actor);
        $actor->update(['status' => UserStatus::SUSPENDED]);
        $this->assertSame('actor_inactive', $this->finalize($publication)->error);
        $actor->update(['status' => UserStatus::ACTIVE]);
        app(SafetyService::class)->userSafety($actor)->update(['frozen_until' => now()->addHour()]);
        try {
            $this->finalize($publication);
            $this->fail('Frozen actor was allowed to publish.');
        } catch (HttpException $exception) {
            $this->assertSame(429, $exception->getStatusCode());
        }
        $this->assertSame(0, Post::count());
        $this->assertNull(Auth::user());
    }

    public function test_story_lifetime_begins_at_finalization_and_keeps_clip_and_poster(): void
    {
        $this->freezeTime();
        $publication = $this->publication($this->user(), 'story', ['video'], ['clip_start_seconds' => 12, 'clip_duration_seconds' => 8]);
        $publication->update(['created_at' => now()->subHours(30)]);
        $published = $this->finalize($publication);
        $frame = StoryFrame::with(['story', 'media'])->findOrFail($published->result['id']);
        $this->assertSame(StoryStatus::ACTIVE, $frame->status);
        $this->assertSame(StoryPrivacy::ALL, $frame->privacy);
        $this->assertSame(now()->toDateTimeString(), $frame->getRawOriginal('created_at'));
        $this->assertSame(now()->addHours((int) config('story.expire_after_hours', 24))->toDateTimeString(), $frame->getRawOriginal('expires_at'));
        $this->assertSame(8, (int) $frame->duration_seconds);
        $this->assertSame(12, (int) data_get($frame->meta, 'video.clip_start_seconds'));
        $this->assertSame($frame->story->url, $published->result['url']);
        $this->assertSame('ready/poster.webp', $frame->media->first()->thumbnail_path);
        $this->assertSame('public', $frame->media->first()->thumbnail_disk);
        $this->assertSame(321, (int) $frame->media->first()->thumbnail_size);
        $this->finalize($publication);
        $this->assertSame(1, StoryFrame::count());
    }

    public function test_chat_membership_removed_after_enqueue_prevents_message_creation(): void
    {
        [$actor, $recipient, $chat] = $this->chat();
        $publication = $this->publication($actor, 'chat', ['image'], ['chat_id' => $chat->chat_id]);
        $chat->participants()->where('user_id', $actor->id)->delete();
        try {
            $this->finalize($publication);
            $this->fail('Removed participant was allowed to publish.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(0, Message::count());
        $this->assertSame(0, Media::count());
        $this->assertNull(Auth::user());
    }

    public function test_story_capacity_is_rechecked_after_enqueue_and_expired_frames_release_capacity(): void
    {
        config(['story.max_frames_per_story' => 2]);
        $actor = $this->user();
        $pending = $this->publication($actor, 'story');
        $first = $this->finalize($this->publication($actor, 'story'));
        $this->finalize($this->publication($actor, 'story'));
        $this->assertSame(2, StoryFrame::count());
        $this->assertSame('story_frame_limit', $this->finalize($pending)->error);
        $this->assertSame(2, StoryFrame::count());
        $this->assertSame(0, DB::table('media_publication_events')->where('publication_id', $pending->id)->count());

        StoryFrame::findOrFail($first->result['id'])->update(['expires_at' => now()->subSecond()]);
        $published = $this->finalize($pending);
        $this->assertSame('published', $published->status);
        $this->assertSame(2, $actor->story->activeFramesCount());
        $this->assertSame(3, StoryFrame::count());
        $this->assertSame($actor->story->url, $published->result['url']);
    }

    public function test_create_rejects_full_stories_without_an_outbox_or_upload_and_allows_expired_capacity(): void
    {
        config(['media.publications.enabled' => true, 'media.publications.allowed_user_ids' => [],
            'media.publications.kinds' => ['story'], 'media.publications.per_user_limit' => 10, 'story.max_frames_per_story' => 1]);
        $this->mock(R2DirectUploadService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldNotReceive('createVideoUpload');
        });
        $actor = $this->user();
        $payload = [
            'kind' => 'story', 'client_uid' => (string) Str::uuid(), 'content' => '', 'privacy' => 'all', 'selected_user_ids' => [],
            'items' => [['client_uid' => (string) Str::uuid(), 'name' => 'photo.jpg', 'mime' => 'image/jpeg', 'type' => 'image', 'size' => 100]],
        ];
        $service = app(PublicationService::class);
        $accepted = $service->create($actor, $payload);
        $ready = $this->finalize($this->publication($actor, 'story'));
        $this->assertSame($accepted->id, $service->create($actor->fresh(), $payload)->id);
        $payload['client_uid'] = (string) Str::uuid();
        try {
            $service->create($actor->fresh(), $payload);
            $this->fail('Full story accepted a new publication.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertSame(2, MediaPublication::count());
        $this->assertSame(2, DB::table('media_publication_items')->count());
        StoryFrame::findOrFail($ready->result['id'])->update(['expires_at' => now()->subSecond()]);
        $this->assertSame('uploading', $service->create($actor->fresh(), $payload)->status);
        $this->assertSame(3, MediaPublication::count());
        $this->assertSame(1, StoryFrame::count());
    }

    public function test_all_kinds_reject_current_inactive_account_status(): void
    {
        foreach ([UserStatus::BLOCKED, UserStatus::SUSPENDED, UserStatus::ONBOARDING] as $status) {
            $actor = $this->user();
            foreach (['post', 'story', 'chat'] as $kind) {
                $publication = $this->publication($actor, $kind);
                $actor->update(['status' => $status]);
                $this->assertSame('actor_inactive', $this->finalize($publication)->error);
            }
        }
        $this->assertSame(0, Post::count() + StoryFrame::count() + Message::count());
        $this->assertSame(0, DB::table('media_publication_events')->count());
        $this->assertNull(Auth::user());
    }

    public function test_direct_chat_finalization_rejects_new_blocks_in_either_direction_or_missing_recipient(): void
    {
        [$actor, $recipient, $chat] = $this->chat();
        foreach ([[$actor, $recipient], [$recipient, $actor]] as [$blocker, $blocked]) {
            $publication = $this->publication($actor, 'chat', ['image'], ['chat_id' => $chat->chat_id]);
            $block = Block::create(['blocker_id' => $blocker->id, 'blocked_id' => $blocked->id]);
            $this->assertSame('chat_recipient_unavailable', $this->finalize($publication)->error);
            $block->delete();
        }
        $publication = $this->publication($actor, 'chat', ['image'], ['chat_id' => $chat->chat_id]);
        $chat->participants()->where('user_id', $recipient->id)->delete();
        $this->assertSame('chat_recipient_unavailable', $this->finalize($publication)->error);
        $this->assertSame(0, Message::count());
        $this->assertSame(0, Media::count());
        $this->assertSame(0, DB::table('media_publication_events')->count());
        $this->assertSame(0, $chat->participants()->where('user_id', $actor->id)->value('last_read_message_id'));
    }

    public function test_direct_chat_rechecks_recipient_permits_status_and_confirmed_follows(): void
    {
        [$actor, $recipient, $chat] = $this->chat();
        $publication = $this->publication($actor, 'chat', ['image'], ['chat_id' => $chat->chat_id]);
        $settings = $recipient->permitSettings()->updateOrCreate([], ['direct_messages' => PrivacyPermit::NOBODY]);
        $this->assertSame('chat_recipient_unavailable', $this->finalize($publication)->error);
        $settings->update(['direct_messages' => PrivacyPermit::ALL]);
        $recipient->update(['status' => UserStatus::SUSPENDED]);
        $this->assertSame('chat_recipient_unavailable', $this->finalize($publication)->error);
        $recipient->update(['status' => UserStatus::ACTIVE]);
        $follow = Follow::create(['follower_id' => $actor->id, 'following_id' => $recipient->id, 'status' => FollowStatus::REQUESTED]);
        foreach ([PrivacyPermit::FOLLOWERS, PrivacyPermit::APPROVED] as $permit) {
            $settings->update(['direct_messages' => $permit]);
            $this->assertSame('chat_recipient_unavailable', $this->finalize($publication)->error);
        }
        $this->assertSame(0, Message::count());
        $follow->update(['status' => FollowStatus::FOLLOWING]);
        $this->assertSame('published', $this->finalize($publication)->status);
        $this->assertSame(1, Message::count());
    }

    public function test_quotes_recheck_author_status_and_blocks_without_incrementing_counters(): void
    {
        $actor = $this->user();
        $author = $this->user();
        $quote = Post::create(['user_id' => $author->id, 'content' => 'Original', 'status' => PostStatus::ACTIVE]);
        $publication = $this->publication($actor, 'post', ['image'], ['quoted_post_id' => $quote->id]);
        $block = Block::create(['blocker_id' => $author->id, 'blocked_id' => $actor->id]);
        $this->assertSame('quoted_post_unavailable', $this->finalize($publication)->error);
        $block->delete();
        $author->update(['status' => UserStatus::SUSPENDED]);
        $this->assertSame('quoted_post_unavailable', $this->finalize($publication)->error);
        $this->assertSame(0, (int) $quote->fresh()->quotes_count);
        $this->assertSame(0, (int) $actor->fresh()->publications_count);
        $this->assertSame(1, Post::count());
        $this->assertSame(0, Media::count());
        $this->assertSame(0, DB::table('media_publication_events')->count());
    }

    public function test_chat_delivery_rechecks_blocks_permits_and_membership_after_recipient_events_are_queued(): void
    {
        config(['notifications.push.enabled' => true]);
        Notification::fake();
        Event::fake([MessageReceivedEvent::class]);
        $this->mock(FirebaseCloudMessagingService::class)->shouldNotReceive('sendToToken');
        foreach (['block', 'permit', 'membership'] as $restriction) {
            [$actor, $recipient, $chat] = $this->chat();
            $this->token($recipient);
            $publication = $this->publication($actor, 'chat', ['image'], ['chat_id' => $chat->chat_id]);
            $this->finalize($publication);
            $eventId = DB::table('media_publication_events')->where('publication_id', $publication->id)->where('type', 'chat_recipient')->value('id');
            (new DeliverPublicationEvent($eventId))->handle();
            $this->assertSame(1, DB::table('media_publication_events')->where('publication_id', $publication->id)->where('type', 'chat_push')->count());
            match ($restriction) {
                'block' => Block::create(['blocker_id' => $recipient->id, 'blocked_id' => $actor->id]),
                'permit' => $recipient->permitSettings()->updateOrCreate([], ['direct_messages' => PrivacyPermit::NOBODY]),
                'membership' => $chat->participants()->where('user_id', $recipient->id)->delete(),
            };
            $this->deliverAll();
            $this->replayAll();
            $this->assertSame('published', $publication->fresh()->status);
        }
        Notification::assertNothingSent();
        Event::assertNotDispatched(MessageReceivedEvent::class);
        $this->assertNull(Auth::user());
    }

    public function test_delayed_mentions_recheck_blocks_and_mention_permissions_for_posts_and_stories(): void
    {
        config(['notifications.broadcast.enabled' => false]);
        foreach (['post', 'story'] as $kind) {
            foreach (['block', 'permit'] as $restriction) {
                $actor = $this->user();
                $recipient = $this->user();
                $recipient->resolvePushNotificationSettings()->update(['mentions' => true]);
                $publication = $this->publication($actor, $kind, ['image'], ['content' => '@'.$recipient->username]);
                $this->finalize($publication);
                $eventId = DB::table('media_publication_events')->where('publication_id', $publication->id)->where('type', $kind.'_mention')->value('id');
                (new DeliverPublicationEvent($eventId))->handle();
                match ($restriction) {
                    'block' => Block::create(['blocker_id' => $recipient->id, 'blocked_id' => $actor->id]),
                    'permit' => $recipient->permitSettings()->updateOrCreate([], ['mentions' => PrivacyPermit::NOBODY]),
                };
                $this->deliverAll();
                $this->assertSame(0, $recipient->notifications()->count());
            }
        }
    }

    public function test_delayed_domain_delivery_does_not_announce_suspended_accounts_or_expired_stories(): void
    {
        Event::fake([PostCreatedEvent::class, PublicTimelinePostCreatedEvent::class, StoryCreatedEvent::class]);
        $actor = $this->user();
        $this->finalize($this->publication($actor));
        $actor->update(['status' => UserStatus::SUSPENDED]);
        $story = $this->finalize($this->publication($this->user(), 'story'));
        StoryFrame::findOrFail($story->result['id'])->update(['expires_at' => now()->subSecond()]);
        $this->deliverAll();
        Event::assertNotDispatched(PostCreatedEvent::class);
        Event::assertNotDispatched(PublicTimelinePostCreatedEvent::class);
        Event::assertNotDispatched(StoryCreatedEvent::class);
        $this->assertNull(Auth::user());
    }

    public function test_censored_content_is_rejected_before_any_post_attachment_counter_or_completion_event_exists(): void
    {
        config(['admin.notifications.user_banned' => false]);
        Event::fake([PostCreatedEvent::class, PublicTimelinePostCreatedEvent::class]);
        Censor::create(['word' => 'blocked-policy-token', 'level' => CensorLevel::BANNED]);
        Cache::forget('censor_banned_words');
        $actor = $this->user();
        $actor->update(['ip_address' => '192.0.2.55']);
        $previous = $this->user();
        $this->actingAs($previous);
        $quote = Post::create(['user_id' => $previous->id, 'content' => 'Existing quote', 'status' => PostStatus::ACTIVE]);
        $publication = $this->publication($actor, 'post', ['image'], [
            'content' => '#topic blocked-policy-token', 'quoted_post_id' => $quote->id,
        ]);
        $observed = [];
        Event::listen(UserBannedEvent::class, function ($event) use ($publication, $actor, &$observed) {
            $observed[] = [
                'actor_id' => me()->id, 'user_id' => $event->userData->id,
                'status' => $publication->fresh()->status,
                'posts' => Post::where('user_id', $actor->id)->count(),
                'attachments' => Media::count(),
                'deliveries' => DB::table('media_publication_events')->count(),
            ];
        });

        $rejected = $this->finalize($publication);
        $this->assertSame('failed', $rejected->status);
        $this->assertSame('content_rejected', $rejected->error);
        $this->assertNull($rejected->published_at);
        $this->assertNull($rejected->result);
        $this->assertSame([[
            'actor_id' => $actor->id, 'user_id' => $actor->id, 'status' => 'publishing',
            'posts' => 0, 'attachments' => 0, 'deliveries' => 0,
        ]], $observed);
        $this->assertTrue(Blacklist::where('type', BlacklistType::EMAIL)->where('blacklistable', $actor->email)->exists());
        $this->assertSame(UserStatus::ACTIVE, $actor->fresh()->status);
        $this->assertSame(0, (int) $actor->fresh()->publications_count);
        $this->assertSame(0, (int) $quote->fresh()->quotes_count);
        $this->assertSame(0, (int) app(SafetyService::class)->userSafety($actor)->post_burst_count);
        $this->assertSame(0, Post::where('user_id', $actor->id)->count());
        $this->assertSame(0, Media::count());
        $this->assertSame(0, DB::table('media_publication_events')->count());
        $this->assertSame($previous->id, Auth::id());
        $this->assertSame('content_rejected', $this->finalize($publication)->error);
        $this->assertCount(1, $observed);
        $this->assertSame(2, Blacklist::count());
        Bus::assertNotDispatched(DeliverPublicationEvent::class);
        Event::assertNotDispatched(PostCreatedEvent::class);
        Event::assertNotDispatched(PublicTimelinePostCreatedEvent::class);
    }

    public function test_accepted_post_is_censored_before_insert_and_is_not_censored_again_during_event_delivery(): void
    {
        $actor = $this->user();
        $publication = $this->publication($actor, 'post', ['image'], ['content' => 'Reviewed <content>']);
        $checked = false;
        $this->mock(CensorService::class, function ($mock) use ($actor, $publication, &$checked) {
            $mock->shouldReceive('setUser')->once()->withArgs(fn ($user) => $user->id === $actor->id)->andReturnSelf();
            $mock->shouldReceive('censor')->once()->with('Reviewed &lt;content&gt;')->andReturnUsing(function () use ($actor, $publication, &$checked) {
                $this->assertSame($actor->id, me()->id);
                $this->assertSame('publishing', $publication->fresh()->status);
                $this->assertSame(0, Post::count());
                $this->assertSame(0, DB::table('media_publication_events')->count());
                $checked = true;
            });
        });
        Event::listen('eloquent.creating: '.Post::class, function () use (&$checked) {
            $this->assertTrue($checked, 'A post was inserted before censorship completed.');
        });
        $this->assertSame('published', $this->finalize($publication)->status);
        $this->deliverAll();
        $this->replayAll();
        $this->finalize($publication);
        $this->assertSame(1, Post::count());
        $this->assertNull(Auth::user());
    }

    public function test_censor_failure_cannot_publish_and_restores_worker_auth(): void
    {
        $actor = $this->user();
        $previous = $this->user();
        $this->actingAs($previous);
        $publication = $this->publication($actor);
        $this->mock(CensorService::class, function ($mock) {
            $mock->shouldReceive('setUser')->once()->andReturnSelf();
            $mock->shouldReceive('censor')->once()->andThrow(new RuntimeException('Censorship unavailable'));
        });
        try {
            $this->finalize($publication);
            $this->fail('Censor failure was ignored.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Censorship unavailable', $exception->getMessage());
        }
        $this->assertSame('processing', $publication->fresh()->status);
        $this->assertSame(0, Post::count());
        $this->assertSame(0, Media::count());
        $this->assertSame(0, DB::table('media_publication_events')->count());
        $this->assertSame(0, (int) $actor->fresh()->publications_count);
        $this->assertSame($previous->id, Auth::id());
    }

    public function test_legacy_post_creation_still_invokes_the_existing_censor_listener(): void
    {
        $actor = $this->user();
        $post = Post::create(['user_id' => $actor->id, 'content' => 'Legacy post', 'type' => 'text', 'status' => PostStatus::ACTIVE]);
        $this->mock(CensorService::class, function ($mock) use ($actor) {
            $mock->shouldReceive('setUser')->once()->withArgs(fn ($user) => $user->id === $actor->id)->andReturnSelf();
            $mock->shouldReceive('censor')->once()->with('Legacy post');
        });
        PublicationFinalizer::asActor($actor, fn () => app(HandlePostCreation::class)->handle(new PostCreatedEvent($post)));
        $this->assertNull(Auth::user());
    }

    public function test_chat_keeps_parent_and_membership_state_and_mute_is_respected_at_delivery(): void
    {
        config(['notifications.push.enabled' => true]);
        Notification::fake();
        Event::fake([MessageReceivedEvent::class]);
        [$actor, $recipient, $chat] = $this->chat();
        $this->token($recipient);
        $parent = $chat->messages()->create([
            'user_id' => $recipient->id, 'chat_uuid' => $chat->chat_id, 'content' => 'Earlier message',
            'participant_id' => $chat->participants()->where('user_id', $recipient->id)->value('id'),
        ]);
        $chat->participants()->where('user_id', $recipient->id)->update(['notifications_muted_until' => now()->addHour()]);
        $publication = $this->publication($actor, 'chat', ['video'], ['chat_id' => $chat->chat_id, 'parent_id' => $parent->id]);
        $published = $this->finalize($publication);
        $message = Message::with('media')->findOrFail($published->result['id']);
        $this->assertSame('message', $published->result['type']);
        $this->assertSame('video_circle', $message->type->value);
        $this->assertSame($parent->id, $message->parent_id);
        $this->assertSame(MediaStatus::PROCESSED, $message->media->status);
        $this->assertSame($message->id, $chat->participants()->where('user_id', $actor->id)->value('last_read_message_id'));
        $this->deliverAll();
        $this->replayAll();
        $this->assertSame(0, DB::table('media_publication_events')->where('type', 'chat_push')->count());
        Notification::assertSentToTimes($recipient, MessageReceivedNotification::class, 1);
        Event::assertDispatched(MessageReceivedEvent::class, 1);
        $this->assertSame(2, Message::count());
    }

    public function test_repeated_domain_delivery_preserves_counters_and_actor_context(): void
    {
        $actor = $this->user();
        $previous = $this->user();
        $this->actingAs($previous);
        $publication = $this->publication($actor, 'post', ['video']);
        $published = $this->finalize($publication);
        $this->assertSame($previous->id, Auth::id());
        $burst = app(SafetyService::class)->userSafety($actor)->post_burst_count;
        $seen = [];
        Event::listen(PostCreatedEvent::class, function ($event) use (&$seen) {
            $seen[] = [me()->id, $event->postData->media->first()->status];
        });
        $this->deliverAll();
        $this->replayAll();
        $this->finalize($publication);
        $this->assertSame([[$actor->id, MediaStatus::PROCESSED]], $seen);
        $this->assertSame($previous->id, Auth::id());
        $this->assertSame(1, (int) $actor->fresh()->publications_count);
        $this->assertSame($burst, app(SafetyService::class)->userSafety($actor)->post_burst_count);
        Bus::assertNotDispatched(ConvertAndCompressPostVideo::class);
        $this->assertNotNull(Post::find($published->result['id']));
    }

    public function test_failed_domain_delivery_remains_pending_and_restores_auth_before_retry(): void
    {
        $actor = $this->user();
        $publication = $this->publication($actor);
        $this->finalize($publication);
        $attempts = 0;
        Event::listen(PostCreatedEvent::class, function () use (&$attempts) {
            if (++$attempts === 1) {
                throw new RuntimeException('Delivery unavailable');
            }
        });
        $id = $this->eventId('domain_created');
        try {
            (new DeliverPublicationEvent($id))->handle();
            $this->fail('Delivery failure was swallowed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Delivery unavailable', $exception->getMessage());
        }
        $this->assertNull(DB::table('media_publication_events')->where('id', $id)->value('delivered_at'));
        $this->assertNull(Auth::user());
        (new DeliverPublicationEvent($id))->handle();
        (new DeliverPublicationEvent($id))->handle();
        $this->assertSame(2, $attempts);
        $this->assertSame(1, (int) $actor->fresh()->publications_count);
    }

    public function test_push_retries_only_failed_token_with_stable_ids_and_string_completion_data(): void
    {
        config(['notifications.push.enabled' => true]);
        $actor = $this->user();
        $first = $this->token($actor);
        $second = $this->token($actor);
        $publication = $this->publication($actor);
        $this->finalize($publication);
        (new DeliverPublicationEvent($this->eventId('owner_completion')))->handle();
        $attempts = [];
        $messages = [];
        $this->mock(FirebaseCloudMessagingService::class, function ($mock) use ($first, &$attempts, &$messages) {
            $mock->shouldReceive('sendToToken')->times(3)->andReturnUsing(function ($token, $message) use ($first, &$attempts, &$messages) {
                $attempts[$token->id] = ($attempts[$token->id] ?? 0) + 1;
                $messages[$token->id][] = $message;
                return $token->id === $first->id || $attempts[$token->id] > 1;
            });
        });
        $events = DB::table('media_publication_events')->where('type', 'owner_push')->orderBy('id')->get();
        (new DeliverPublicationEvent($events[0]->id))->handle();
        try {
            (new DeliverPublicationEvent($events[1]->id))->handle();
            $this->fail('Failed token was marked delivered.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('push delivery failed', $exception->getMessage());
        }
        (new DeliverPublicationEvent($events[0]->id))->handle();
        (new DeliverPublicationEvent($events[1]->id))->handle();
        $this->assertSame([$first->id => 1, $second->id => 2], $attempts);
        $data = $messages[$second->id][0]['data'];
        $this->assertSame($data['event_id'], $messages[$second->id][1]['data']['event_id']);
        $this->assertSame($data['event_id'], $data['notification_tag']);
        $this->assertSame('media_publication', $data['type']);
        $this->assertSame($publication->id, $data['publication_id']);
        $this->assertSame($publication->client_uid, $data['client_uid']);
        $this->assertSame('published', $data['status']);
        $this->assertSame((string) $actor->id, $data['user_id']);
        foreach ($data as $value) {
            $this->assertIsString($value);
        }
        $this->assertNull(Auth::user());
    }

    public function test_mention_database_notification_has_deterministic_id_and_replay_does_not_duplicate_it(): void
    {
        config(['notifications.broadcast.enabled' => false]);
        $actor = $this->user();
        $recipient = $this->user();
        $recipient->resolvePushNotificationSettings()->update(['mentions' => true]);
        $publication = $this->publication($actor, 'post', ['image'], ['content' => '@'.$recipient->username]);
        $this->finalize($publication);
        $this->deliverAll();
        $this->replayAll();
        $notification = $recipient->notifications()->sole();
        $expected = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, $publication->id.':post_mention:'.$recipient->id);
        $this->assertSame($expected, $notification->id);
        $this->assertSame($actor->id, data_get($notification->data, 'actor.id'));
        DB::table('media_publication_events')->where('type', 'post_mention_database')->update(['delivered_at' => null]);
        (new DeliverPublicationEvent($this->eventId('post_mention_database')))->handle();
        $this->assertSame(1, $recipient->notifications()->count());
    }

    public function test_processed_videos_skip_legacy_transcodes_while_unprocessed_videos_still_dispatch(): void
    {
        $actor = $this->user();
        $postPublication = $this->finalize($this->publication($actor, 'post', ['video']));
        $storyPublication = $this->finalize($this->publication($actor, 'story', ['video']));
        $post = Post::with('media')->findOrFail($postPublication->result['id']);
        $frame = StoryFrame::with('media')->findOrFail($storyPublication->result['id']);
        PublicationFinalizer::asActor($actor, function () use ($post, $frame) {
            app(HandlePostCreation::class)->handle(new PostCreatedEvent($post));
            app(HandleStoryCreation::class)->handle(new StoryCreatedEvent($frame));
        });
        Bus::assertNotDispatched(ConvertAndCompressPostVideo::class);
        Bus::assertNotDispatched(ProcessStoryVideo::class);
        foreach ([$post, $frame] as $entity) {
            $entity->media->first()->update(['status' => MediaStatus::UNPROCESSED, 'metadata' => []]);
            $entity->load('media');
        }
        PublicationFinalizer::asActor($actor, function () use ($post, $frame) {
            app(HandlePostCreation::class)->handle(new PostCreatedEvent($post));
            app(HandleStoryCreation::class)->handle(new StoryCreatedEvent($frame));
        });
        Bus::assertDispatchedTimes(ConvertAndCompressPostVideo::class, 1);
        Bus::assertDispatchedTimes(ProcessStoryVideo::class, 1);
    }

    public function test_creation_failure_rolls_back_entity_quote_and_counters_and_restores_actor(): void
    {
        $actor = $this->user();
        $previous = $this->user();
        $this->actingAs($previous);
        $quote = Post::create(['user_id' => $actor->id, 'status' => PostStatus::ACTIVE, 'content' => 'quote']);
        $publication = $this->publication($actor, 'post', ['image'], ['quoted_post_id' => $quote->id]);
        $this->mock(TopicExtractionService::class)->shouldReceive('syncPostTopics')->once()->andThrow(new RuntimeException('Topics unavailable'));
        try {
            $this->finalize($publication);
            $this->fail('Finalization failure was swallowed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Topics unavailable', $exception->getMessage());
        }
        $this->assertSame('processing', $publication->fresh()->status);
        $this->assertSame(1, Post::count());
        $this->assertSame(0, (int) $quote->fresh()->quotes_count);
        $this->assertSame(0, (int) $actor->fresh()->publications_count);
        $this->assertSame(0, DB::table('media_publication_events')->count());
        $this->assertSame($previous->id, Auth::id());
    }

    public function test_cancelled_and_missing_publications_create_nothing(): void
    {
        $publication = $this->publication($this->user());
        $publication->update(['status' => 'cancelled']);
        $this->assertSame('cancelled', $this->finalize($publication)->status);
        $this->assertNull(app(PublicationFinalizer::class)->finalize((string) Str::uuid()));
        $this->assertSame(0, Post::count());
        $this->assertSame(0, DB::table('media_publication_events')->count());
    }

    public function test_outer_transaction_rollback_does_not_dispatch_delivery_and_recovery_only_dispatches_pending_events(): void
    {
        $publication = $this->publication($this->user());
        try {
            DB::transaction(function () use ($publication) {
                $this->finalize($publication);
                $this->assertSame(3, DB::table('media_publication_events')->count());
                Bus::assertNotDispatched(DeliverPublicationEvent::class);
                throw new RuntimeException('Outer transaction rolled back');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Outer transaction rolled back', $exception->getMessage());
        }
        $this->assertSame(0, Post::count());
        $this->assertSame(0, DB::table('media_publication_events')->count());
        Bus::assertNotDispatched(DeliverPublicationEvent::class);

        $this->finalize($publication);
        DB::table('media_publication_events')->where('type', 'domain_created')->update(['delivered_at' => now()]);
        Bus::fake();
        DeliverPublicationEvent::dispatchPending();
        Bus::assertDispatchedTimes(DeliverPublicationEvent::class, 2);
        Bus::assertNotDispatched(DeliverPublicationEvent::class, fn ($job) => $job->eventId === $this->eventId('domain_created'));
    }

    public function test_story_mention_delivery_uses_the_story_actor_and_a_single_database_notification(): void
    {
        config(['notifications.broadcast.enabled' => false]);
        $actor = $this->user();
        $recipient = $this->user();
        $recipient->resolvePushNotificationSettings()->update(['mentions' => true]);
        $publication = $this->publication($actor, 'story', ['image'], ['content' => '@'.$recipient->username]);
        $published = $this->finalize($publication);
        $this->deliverAll();
        $this->replayAll();
        $notification = $recipient->notifications()->sole();
        $this->assertSame($actor->id, data_get($notification->data, 'actor.id'));
        $this->assertSame(StoryFrame::find($published->result['id'])->story->story_uuid, data_get($notification->data, 'entity.story_uuid'));
        $this->assertNull(Auth::user());
    }

    private function finalize(MediaPublication $publication): MediaPublication
    {
        return app(PublicationFinalizer::class)->finalize($publication->id);
    }

    private function eventId(string $type): int
    {
        return (int) DB::table('media_publication_events')->where('type', $type)->value('id');
    }

    private function deliverAll(): void
    {
        for ($pass = 0; $pass < 5; $pass++) {
            $ids = DB::table('media_publication_events')->whereNull('delivered_at')->pluck('id');
            if ($ids->isEmpty()) {
                return;
            }
            foreach ($ids as $id) {
                (new DeliverPublicationEvent($id))->handle();
            }
        }
        $this->fail('Publication events did not drain.');
    }

    private function replayAll(): void
    {
        foreach (DB::table('media_publication_events')->pluck('id') as $id) {
            (new DeliverPublicationEvent($id))->handle();
        }
    }

    private function publication(User $actor, string $kind = 'post', array $types = ['image'], array $payload = []): MediaPublication
    {
        $publication = MediaPublication::create([
            'id' => (string) Str::uuid(), 'user_id' => $actor->id, 'client_uid' => (string) Str::uuid(),
            'request_hash' => str_repeat('a', 64), 'kind' => $kind, 'status' => 'processing',
            'has_video' => in_array('video', $types, true), 'profile' => [],
            'payload' => array_merge(['kind' => $kind, 'content' => '', 'privacy' => 'all', 'selected_user_ids' => []], $payload),
            'expires_at' => now()->addDays(3),
        ]);
        foreach ($types as $position => $type) {
            $extension = $type === 'image' ? 'webp' : 'mp4';
            $publication->items()->create([
                'id' => (string) Str::uuid(), 'client_uid' => (string) Str::uuid(), 'position' => $position,
                'type' => $type, 'name' => 'original.'.$extension, 'mime' => $type.'/'.$extension,
                'size' => 4567, 'status' => 'processed', 'progress' => 100, 'metadata' => ['custom' => 'kept'],
                'output' => [
                    'source_path' => 'ready/'.$position.'.'.$extension, 'disk' => 'public',
                    'mime' => $type.'/'.$extension, 'extension' => $extension, 'size' => 1234,
                    'thumbnail_path' => 'ready/poster.webp', 'thumbnail_disk' => 'public', 'thumbnail_size' => 321,
                    'metadata' => ['dimensions' => ['width' => 600, 'height' => 800], 'duration_seconds' => 8],
                ],
            ]);
        }
        return $publication;
    }

    private function chat(): array
    {
        $actor = $this->user();
        $recipient = $this->user();
        $chat = Chat::create(['chat_id' => (string) Str::uuid(), 'type' => ChatType::DIRECT, 'last_activity' => now()]);
        foreach ([$actor, $recipient] as $user) {
            $chat->participants()->create([
                'user_id' => $user->id, 'last_read_message_id' => 0, 'metadata' => ['color' => '#111111'], 'joined_at' => now(),
            ]);
        }
        return [$actor, $recipient, $chat];
    }

    private function token(User $user): UserPushToken
    {
        $token = Str::random(40);
        return $user->pushTokens()->create([
            'provider' => 'fcm', 'platform' => 'android', 'token' => $token, 'token_hash' => hash('sha256', $token),
        ]);
    }

    private function user(): User
    {
        $username = 'publisher_'.Str::lower(Str::random(10));
        return User::create([
            'first_name' => 'Publication', 'last_name' => 'Tester', 'username' => $username,
            'caption' => '@'.$username, 'email' => $username.'@example.com', 'password' => bcrypt('password'),
            'phone' => '', 'website' => '', 'bio' => '', 'gender' => 'male', 'language' => 'en',
            'last_active' => now()->timestamp, 'tips' => [], 'email_verified_at' => now(),
            'role' => 'user', 'theme' => 'light', 'status' => UserStatus::ACTIVE, 'type' => 'author',
            'publications_count' => 0, 'followers_count' => 0, 'following_count' => 0,
        ]);
    }
}
