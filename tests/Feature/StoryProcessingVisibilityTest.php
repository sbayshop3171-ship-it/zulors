<?php

namespace Tests\Feature;

use App\Database\Configs\Table;
use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Enums\Media\MediaVisibility;
use App\Enums\Story\StoryPrivacy;
use App\Enums\Story\StoryStatus;
use App\Enums\Story\StoryType;
use App\Enums\User\UserStatus;
use App\Models\Story;
use App\Models\StoryFrame;
use App\Models\User;
use App\Notifications\User\Important\StoryExpiredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoryProcessingVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_processing_story_is_hidden_from_feed_and_viewer(): void
    {
        $owner = $this->createUser('story-failed-owner');
        $story = $this->createStory($owner);

        $this->createStoryFrame($story, [
            'status' => StoryStatus::PROCESSING,
            'type' => StoryType::VIDEO,
            'created_at' => now()->subMinutes(15),
            'expires_at' => now()->addHours(23),
        ], MediaStatus::PROCESSING, [
            'processing_state' => 'failed',
            'processing_progress' => 99,
        ]);

        $this->actingAs($owner)
            ->withoutMiddleware()
            ->getJson('/api/stories/feed')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($owner)
            ->withoutMiddleware()
            ->getJson("/api/stories/stories/{$story->story_uuid}")
            ->assertNotFound();
    }

    public function test_active_story_stays_open_when_latest_processing_frame_failed(): void
    {
        $owner = $this->createUser('story-active-owner');
        $story = $this->createStory($owner);
        $activeFrame = $this->createStoryFrame($story, [
            'status' => StoryStatus::ACTIVE,
            'type' => StoryType::IMAGE,
            'duration_seconds' => 10,
            'created_at' => now()->subMinutes(20),
            'expires_at' => now()->addHours(23),
        ], MediaStatus::PROCESSED, [
            'processing_state' => 'processed',
            'processing_progress' => 100,
        ]);

        $this->createStoryFrame($story, [
            'status' => StoryStatus::PROCESSING,
            'type' => StoryType::VIDEO,
            'created_at' => now()->subMinutes(15),
            'expires_at' => now()->addHours(23),
        ], MediaStatus::PROCESSING, [
            'processing_state' => 'failed',
            'processing_progress' => 99,
        ]);

        $feedPayload = $this->actingAs($owner)
            ->withoutMiddleware()
            ->getJson('/api/stories/feed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data.0');

        $this->assertSame(StoryStatus::ACTIVE->value, $feedPayload['status']);
        $this->assertNull($feedPayload['frame_id']);
        $this->assertSame('ready', $feedPayload['progress']['stage']);

        $viewerPayload = $this->actingAs($owner)
            ->withoutMiddleware()
            ->getJson("/api/stories/stories/{$story->story_uuid}")
            ->assertOk()
            ->assertJsonCount(1, 'data.0.relations.frames')
            ->json('data.0.relations.frames.0');

        $this->assertSame($activeFrame->id, $viewerPayload['id']);
        $this->assertSame(StoryStatus::ACTIVE->value, $viewerPayload['status']);
    }

    public function test_expired_story_is_hidden_from_feed_viewer_and_view_endpoints(): void
    {
        $owner = $this->createUser('story-expired-owner');
        $viewer = $this->createUser('story-expired-viewer');
        $story = $this->createStory($owner);
        $expiredFrame = $this->createStoryFrame($story, [
            'status' => StoryStatus::ACTIVE,
            'type' => StoryType::VIDEO,
            'created_at' => now()->subDays(2),
            'expires_at' => now()->subHour(),
        ]);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/stories/feed')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson("/api/stories/stories/{$story->story_uuid}")
            ->assertNotFound();

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/stories/views/record', [
                'frame_id' => $expiredFrame->id,
            ])
            ->assertNotFound();

        $this->actingAs($owner)
            ->withoutMiddleware()
            ->getJson("/api/stories/views/{$expiredFrame->id}")
            ->assertNotFound();
    }

    public function test_story_create_resets_publish_time_and_sets_24_hour_expiry(): void
    {
        config(['story.expire_after_hours' => 24]);

        $owner = $this->createUser('story-publish-owner');
        $story = $this->createStory($owner);
        $draftFrame = $this->createStoryFrame($story, [
            'status' => StoryStatus::DRAFT,
            'type' => StoryType::IMAGE,
            'created_at' => now()->subDays(2),
            'expires_at' => null,
        ]);
        $publishedAt = now()->startOfSecond();

        $this->travelTo($publishedAt);

        $this->actingAs($owner)
            ->withoutMiddleware()
            ->postJson('/api/story/editor/create', [
                'content' => 'Fresh story publish time',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', StoryStatus::ACTIVE->value);

        $draftFrame->refresh();

        $this->assertSame(StoryStatus::ACTIVE, $draftFrame->status);
        $this->assertSame($publishedAt->toDateTimeString(), $draftFrame->getRawOriginal('created_at'));
        $this->assertSame($publishedAt->copy()->addHours(24)->toDateTimeString(), $draftFrame->getRawOriginal('expires_at'));
    }

    public function test_story_clear_removes_stale_failed_processing_frames(): void
    {
        Notification::fake();

        config(['story.failed_processing_cleanup_grace_minutes' => 10]);

        $owner = $this->createUser('story-clean-owner');
        $story = $this->createStory($owner);
        $failedFrame = $this->createStoryFrame($story, [
            'status' => StoryStatus::PROCESSING,
            'type' => StoryType::VIDEO,
            'created_at' => now()->subMinutes(15),
            'expires_at' => now()->addHours(23),
        ], MediaStatus::FAILED, [
            'processing_state' => 'failed',
            'processing_progress' => 99,
        ]);

        $this->artisan('story:clear')->assertExitCode(0);

        $this->assertDatabaseMissing(Table::STORY_FRAMES, [
            'id' => $failedFrame->id,
        ]);

        $this->assertDatabaseMissing(Table::MEDIA, [
            'mediaable_id' => $failedFrame->id,
            'mediaable_type' => StoryFrame::class,
        ]);

        Notification::assertNotSentTo($owner, StoryExpiredNotification::class);
    }

    public function test_story_clear_removes_expired_frames_and_notifies_owner(): void
    {
        Notification::fake();

        $owner = $this->createUser('story-clear-expired-owner');
        $story = $this->createStory($owner);
        $expiredFrame = $this->createStoryFrame($story, [
            'status' => StoryStatus::ACTIVE,
            'type' => StoryType::VIDEO,
            'created_at' => now()->subDays(2),
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('story:clear')->assertExitCode(0);

        $this->assertDatabaseMissing(Table::STORY_FRAMES, [
            'id' => $expiredFrame->id,
        ]);

        Notification::assertSentTo($owner, StoryExpiredNotification::class);
    }

    private function createStory(User $user): Story
    {
        return Story::query()->create([
            'story_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'updated_at' => now(),
        ]);
    }

    private function createStoryFrame(
        Story $story,
        array $overrides = [],
        MediaStatus $mediaStatus = MediaStatus::PROCESSED,
        array $metadata = []
    ): StoryFrame {
        $frame = $story->frames()->create(array_merge([
            'content' => null,
            'status' => StoryStatus::ACTIVE,
            'type' => StoryType::IMAGE,
            'privacy' => StoryPrivacy::ALL,
            'views_count' => 0,
            'is_highlight' => false,
            'duration_seconds' => 10,
            'meta' => [],
            'created_at' => now(),
            'expires_at' => now()->addHours(24),
        ], $overrides));

        $frame->media()->create([
            'source_path' => 'testing/story-media.mp4',
            'thumbnail_path' => '',
            'type' => $frame->type->isVideo() ? MediaType::VIDEO : MediaType::IMAGE,
            'status' => $mediaStatus,
            'disk' => 'local',
            'thumbnail_disk' => 'local',
            'extension' => $frame->type->isVideo() ? 'mp4' : 'jpg',
            'visibility' => MediaVisibility::VISIBLE,
            'mime' => $frame->type->isVideo() ? 'video/mp4' : 'image/jpeg',
            'size' => '1024',
            'thumbnail_size' => '',
            'order' => 0,
            'metadata' => $metadata,
        ]);

        return $frame;
    }

    private function createUser(string $username): User
    {
        return User::query()->create([
            'first_name' => 'Story',
            'last_name' => 'Tester',
            'username' => $username,
            'caption' => '@' . $username,
            'email' => "{$username}@example.test",
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
            'role' => 'user',
            'theme' => 'light',
            'publications_count' => 0,
            'followers_count' => 0,
            'following_count' => 0,
            'status' => UserStatus::ACTIVE,
            'type' => 'author',
        ]);
    }
}
