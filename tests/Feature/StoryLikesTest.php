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
use App\Notifications\User\Story\StoryLikedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoryLikesTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_can_toggle_private_story_like_and_owner_is_notified(): void
    {
        Notification::fake();

        $owner = $this->createUser('story-like-owner');
        $viewer = $this->createUser('story-like-viewer');
        $story = $this->createStory($owner);
        $frame = $this->createStoryFrame($story);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/stories/likes/toggle', [
                'frame_id' => $frame->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.frame_id', $frame->id)
            ->assertJsonPath('data.activity.has_liked', true)
            ->assertJsonPath('data.activity.is_seen', true)
            ->assertJsonPath('data.likes_count.raw', 1);

        $this->assertDatabaseHas(Table::REACTIONS, [
            'reactable_type' => StoryFrame::class,
            'reactable_id' => $frame->id,
            'unified_id' => StoryFrame::PRIVATE_LIKE_UNIFIED_ID,
            'reactions_count' => 1,
        ]);

        $this->assertDatabaseHas(Table::STORY_VIEWS, [
            'story_frame_id' => $frame->id,
            'user_id' => $viewer->id,
        ]);

        $this->assertDatabaseHas(Table::STORY_FRAMES, [
            'id' => $frame->id,
            'views_count' => 1,
        ]);

        Notification::assertSentTo($owner, StoryLikedNotification::class);
    }

    public function test_second_toggle_removes_private_story_like(): void
    {
        $owner = $this->createUser('story-unlike-owner');
        $viewer = $this->createUser('story-unlike-viewer');
        $story = $this->createStory($owner);
        $frame = $this->createStoryFrame($story);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/stories/likes/toggle', [
                'frame_id' => $frame->id,
            ])
            ->assertOk();

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/stories/likes/toggle', [
                'frame_id' => $frame->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.activity.has_liked', false)
            ->assertJsonPath('data.likes_count.raw', 0);

        $this->assertDatabaseMissing(Table::REACTIONS, [
            'reactable_type' => StoryFrame::class,
            'reactable_id' => $frame->id,
            'unified_id' => StoryFrame::PRIVATE_LIKE_UNIFIED_ID,
        ]);
    }

    public function test_owner_cannot_like_own_story(): void
    {
        $owner = $this->createUser('story-like-self-owner');
        $story = $this->createStory($owner);
        $frame = $this->createStoryFrame($story);

        $this->actingAs($owner)
            ->withoutMiddleware()
            ->postJson('/api/stories/likes/toggle', [
                'frame_id' => $frame->id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', __('api/story.cannot_like_own_story'));
    }

    public function test_owner_story_views_include_private_like_state_and_counts(): void
    {
        Notification::fake();

        $owner = $this->createUser('story-views-owner');
        $viewer = $this->createUser('story-views-viewer');
        $story = $this->createStory($owner);
        $frame = $this->createStoryFrame($story);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/stories/likes/toggle', [
                'frame_id' => $frame->id,
            ])
            ->assertOk();

        $this->actingAs($owner)
            ->withoutMiddleware()
            ->getJson("/api/stories/views/{$frame->id}")
            ->assertOk()
            ->assertJsonPath('meta.likes_count.raw', 1)
            ->assertJsonPath('meta.views_count.raw', 1)
            ->assertJsonPath('data.0.relations.user.id', $viewer->id)
            ->assertJsonPath('data.0.activity.has_liked', true);
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
            'content' => 'A private likeable story',
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
            'source_path' => 'testing/story-media.jpg',
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
            'last_name' => 'Liker',
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
        ]);
    }
}
