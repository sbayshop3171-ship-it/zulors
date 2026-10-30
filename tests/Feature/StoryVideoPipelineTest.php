<?php

namespace Tests\Feature;

use App\Constants\Filesystem;
use App\Enums\Media\MediaStatus;
use App\Enums\Story\StoryPrivacy;
use App\Enums\Story\StoryStatus;
use App\Enums\Story\StoryType;
use App\Enums\User\UserStatus;
use App\Jobs\User\Story\ProcessStoryVideo;
use App\Models\Story;
use App\Models\User;
use App\Services\Filesystem\Upload\ImageUploadService;
use App\Services\Filesystem\Upload\VideoThumbnailService;
use App\Services\Filesystem\Upload\VideoUploadService;
use App\Services\Filesystem\Base64Image\Base64ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoryVideoPipelineTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Support\CreatesVideoFixture;

    public function test_direct_only_story_blocks_fallback_and_unavailable_direct_creation(): void
    {
        config(['media.uploads.video.direct_only' => true, 'media.cloudflare.r2.direct_upload_enabled' => false]);
        $this->actingAs($this->createUser('strict-story-owner'))->withoutMiddleware();
        $this->postJson('/api/story/editor/media/upload', [
            'media_file' => UploadedFile::fake()->create('clip.mp4', 10, 'video/mp4'),
        ])->assertStatus(409)->assertJsonPath('code', 'direct_upload_required');
        $this->postJson('/api/story/editor/media/video/direct/create', ['size' => 1024])
            ->assertStatus(503)->assertJsonPath('code', 'direct_upload_unavailable');
        $this->assertDatabaseCount('media', 0);
    }

    public function test_story_worker_transcodes_r2_source_and_deletes_original(): void
    {
        $original = $this->createVideoFixture();
        $story = $this->createStory($this->createUser('story-worker-owner'));
        $frame = $story->frames()->create([
            'status' => StoryStatus::PROCESSING, 'type' => StoryType::VIDEO, 'privacy' => StoryPrivacy::ALL,
            'duration_seconds' => 2, 'meta' => ['video' => ['duration_seconds' => 2, 'clip_start_seconds' => 1]],
        ]);
        $media = $frame->media()->create($original);
        (new ProcessStoryVideo($frame))->handle();
        $this->assertOptimizedVideo($media, $original);
        $this->assertSame(['width' => 320, 'height' => 180], data_get($media->refresh()->metadata, 'dimensions'));
        $thumbnailInfo = getimagesize(\Illuminate\Support\Facades\Storage::disk('r2_final')->path($media->thumbnail_path));
        $this->assertSame([320, 180], [$thumbnailInfo[0], $thumbnailInfo[1]]);
        $this->assertSame(StoryStatus::ACTIVE, $frame->refresh()->status);
        $path = $media->source_path;
        (new ProcessStoryVideo($frame))->handle();
        $this->assertSame($path, $media->refresh()->source_path);
    }

    public function test_legacy_story_upload_rejects_video_bytes_at_the_api_server(): void
    {
        $owner = $this->createUser('story-r2-owner');
        $this->actingAs($owner)->withoutMiddleware()->post('/api/story/editor/media/upload', [
            'media_file' => UploadedFile::fake()->create('story-video.mp4', 1024, 'video/mp4'),
        ])->assertStatus(409)->assertJsonPath('code', 'direct_upload_required');

        $this->assertDatabaseCount('media', 0);
    }

    public function test_story_publish_makes_uploaded_r2_video_active_and_dispatches_background_processing(): void
    {
        Queue::fake();

        config([
            'filesystems.disks.r2_temp' => ['driver' => 'local', 'root' => storage_path('app/testing/r2-temp'), 'enabled' => true],
            'filesystems.disks.r2_final' => ['driver' => 'local', 'root' => storage_path('app/testing/r2-final'), 'enabled' => true],
        ]);

        $owner = $this->createUser('story-publish-r2-owner');
        $story = $this->createStory($owner);
        $frame = $story->frames()->create([
            'content' => null,
            'status' => StoryStatus::DRAFT,
            'type' => StoryType::VIDEO,
            'privacy' => StoryPrivacy::ALL,
            'views_count' => 0,
            'is_highlight' => false,
            'duration_seconds' => 10,
            'meta' => [
                'video' => [
                    'duration_seconds' => 10,
                    'original_duration_seconds' => 18,
                    'clip_start_seconds' => 0,
                    'clip_end_seconds' => 10,
                ],
            ],
            'created_at' => now(),
            'expires_at' => null,
        ]);

        $frame->media()->create([
            'source_path' => 'uploads/stories/raw_videos/story-upload.mp4',
            'thumbnail_path' => 'uploads/stories/video_thumbnails/story-thumb.webp',
            'type' => \App\Enums\Media\MediaType::VIDEO,
            'status' => MediaStatus::UNPROCESSED,
            'disk' => 'r2_temp',
            'thumbnail_disk' => 'r2_final',
            'extension' => 'mp4',
            'mime' => 'video/mp4',
            'size' => 345678,
            'thumbnail_size' => 12345,
            'metadata' => [
                'provider' => 'r2_temp',
                'upload_state' => 'uploaded',
                'temp_disk' => 'r2_temp',
                'final_disk' => 'r2_final',
            ],
        ]);

        $this->actingAs($owner)
            ->withoutMiddleware()
            ->postJson('/api/story/editor/create', [
                'content' => 'Queued story video',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', StoryStatus::ACTIVE->value);

        $frame->refresh();
        $media = $frame->media()->firstOrFail();

        $this->assertSame(StoryStatus::ACTIVE, $frame->status);
        $this->assertSame(MediaStatus::PROCESSED, $media->status);
        $this->assertSame('queued', data_get($media->metadata, 'processing_state'));
        $this->assertSame(100, data_get($media->metadata, 'processing_progress'));
        $this->assertSame('queued', data_get($media->metadata, 'background_processing_state'));
        $this->assertSame(0, data_get($media->metadata, 'background_processing_progress'));
        $this->assertTrue(data_get($media->metadata, 'instant_publish'));

        Queue::assertPushed(ProcessStoryVideo::class);
    }

    public function test_story_direct_upload_publishes_immediately_and_optimizes_in_background(): void
    {
        Queue::fake();
        \Illuminate\Support\Facades\Storage::fake('r2_temp');
        \Illuminate\Support\Facades\Storage::fake('r2_final');
        config(['filesystems.disks.r2_final.enabled' => true]);
        app()->instance(\App\Services\Media\Cloudflare\R2DirectUploadService::class, new \Tests\Support\FakeR2DirectUploadService());
        $owner = $this->createUser('story-direct-owner');
        $this->actingAs($owner)->withoutMiddleware();
        $created = $this->postJson('/api/story/editor/media/video/direct/create', [
            'name' => 'sample.mp4', 'size' => 1024, 'duration_seconds' => 10,
            'mime' => 'video/mp4', 'extension' => 'mp4',
        ])->assertOk()->assertJsonPath('data.direct_upload', true)->assertJsonPath('data.source_url', null);
        $upload = $created->json('data');
        $identity = ['media_id' => $upload['media_id'], 'uid' => $upload['uid'], 'upload_id' => $upload['upload_id']];
        $this->postJson('/api/story/editor/create', [])->assertUnprocessable();
        $this->postJson('/api/story/editor/media/video/direct/complete', $identity)->assertUnprocessable();
        \Illuminate\Support\Facades\Storage::disk('r2_temp')->put($upload['uid'], str_repeat('x', 1024));
        $this->postJson('/api/story/editor/media/video/direct/complete', $identity + [
            'parts' => [['part_number' => 1, 'etag' => 'test']],
        ])->assertOk()->assertJsonPath('data.metadata.upload_state', 'uploaded')->assertJsonPath('data.source_url', null);
        Queue::assertNothingPushed();
        $this->postJson('/api/story/editor/create', ['content' => 'Ready to process'])
            ->assertOk()
            ->assertJsonPath('data.status', StoryStatus::ACTIVE->value)
            ->assertJsonPath('data.can_open', true)
            ->assertJsonPath('data.progress.stage', 'ready');
        Queue::assertPushedOn(config('media.queues.video_high'), ProcessStoryVideo::class);
        \Illuminate\Support\Facades\Storage::disk('r2_final')->assertDirectoryEmpty('/');

        $story = $owner->fresh()->story;
        $viewerPayload = $this->getJson("/api/stories/stories/{$story->story_uuid}")
            ->assertOk()
            ->assertJsonPath('data.0.relations.frames.0.status', StoryStatus::ACTIVE->value)
            ->assertJsonPath('data.0.relations.frames.0.media.status', MediaStatus::PROCESSED->value)
            ->json('data.0.relations.frames.0');

        $this->assertNotEmpty($viewerPayload['media']['source_url']);
        $this->assertSame('queued', data_get($viewerPayload, 'media.metadata.background_processing_state'));
        $this->assertTrue(data_get($viewerPayload, 'media.metadata.instant_publish'));
    }

    private function createStory(User $user): Story
    {
        return Story::query()->create([
            'story_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'updated_at' => now(),
        ]);
    }

    private function createUser(string $username): User
    {
        return User::query()->create([
            'first_name' => 'Story',
            'last_name' => 'Pipeline',
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

    private function makeTempFile(string $name, string $contents): string
    {
        $path = sys_get_temp_dir() . '/' . Str::uuid() . '-' . $name;
        file_put_contents($path, $contents);

        return $path;
    }
}
