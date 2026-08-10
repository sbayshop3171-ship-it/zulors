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

    public function test_story_video_upload_is_stored_in_r2_temp_with_final_disk_metadata(): void
    {
        config([
            'filesystems.disks.r2_temp' => [
                'driver' => 'local',
                'root' => storage_path('app/testing/r2-temp'),
                'enabled' => true,
            ],
            'filesystems.disks.r2_final' => [
                'driver' => 'local',
                'root' => storage_path('app/testing/r2-final'),
                'url' => 'https://media.example.test',
                'enabled' => true,
            ],
            'media.cloudflare.r2.temp_disk' => 'r2_temp',
            'media.cloudflare.r2.final_disk' => 'r2_final',
        ]);

        $owner = $this->createUser('story-r2-owner');
        $thumbnailPath = $this->makeTempFile('story-thumb.jpg', 'thumb');

        $this->mock(VideoUploadService::class, function($mock) {
            $mock->shouldReceive('tempSaveLocally')->once()->andReturn([
                'disk' => 'local',
                'video_path' => 'tmp/videos/story-upload.mp4',
                'duration' => parse_duration(18),
                'seconds' => 18,
                'dimensions' => ['width' => 720, 'height' => 1280],
                'aspect_ratio' => 0.5625,
                'is_portrait' => true,
            ]);
            $mock->shouldReceive('setStorageDisk')->once()->with('r2_temp')->andReturnSelf();
            $mock->shouldReceive('setNamespace')->once()->with(Filesystem::mediaNamespace('stories/raw_videos'))->andReturnSelf();
            $mock->shouldReceive('setDefaultExtension')->once()->with('mp4')->andReturnSelf();
            $mock->shouldReceive('upload')->once()->with(storage_local_path('tmp/videos/story-upload.mp4'))->andReturn([
                'disk' => 'r2_temp',
                'video_path' => 'uploads/stories/raw_videos/story-upload.mp4',
                'video_size' => 345678,
            ]);
        });

        $this->mock(VideoThumbnailService::class, function($mock) use ($thumbnailPath) {
            $mock->shouldReceive('setSecondsOffset')->once()->with(0)->andReturnSelf();
            $mock->shouldReceive('generateThumbnail')->once()->with('tmp/videos/story-upload.mp4')->andReturn($thumbnailPath);
        });

        $this->mock(ImageUploadService::class, function($mock) use ($thumbnailPath) {
            $mock->shouldReceive('load')->once()->with($thumbnailPath)->andReturnSelf();
            $mock->shouldReceive('setNamespace')->once()->with(Filesystem::mediaNamespace('stories/video_thumbnails'))->andReturnSelf();
            $mock->shouldReceive('setStorageDisk')->once()->with('r2_final')->andReturnSelf();
            $mock->shouldReceive('scaleTo1080x1920')->once()->andReturnSelf();
            $mock->shouldReceive('compress')->once()->andReturnSelf();
            $mock->shouldReceive('upload')->once()->andReturn([
                'disk' => 'r2_final',
                'image_path' => 'uploads/stories/video_thumbnails/story-thumb.webp',
                'image_size' => 12345,
            ]);
        });

        $this->mock(Base64ImageService::class, function($mock) use ($thumbnailPath) {
            $mock->shouldReceive('load')->once()->with($thumbnailPath)->andReturnSelf();
            $mock->shouldReceive('getBase64')->once()->andReturn('data:image/webp;base64,thumb');
        });

        $response = $this->actingAs($owner)
            ->withoutMiddleware()
            ->post('/api/story/editor/media/upload', [
                'media_file' => UploadedFile::fake()->create('story-video.mp4', 1024, 'video/mp4'),
            ]);

        $response->assertOk()
            ->assertJsonPath('data.type', 'video')
            ->assertJsonPath('data.metadata.duration_seconds', 18);

        $storyMedia = $owner->fresh()->story->frames()->firstOrFail()->media()->firstOrFail();

        $this->assertSame('r2_temp', $storyMedia->disk);
        $this->assertSame(MediaStatus::UNPROCESSED, $storyMedia->status);
        $this->assertSame('r2_temp', data_get($storyMedia->metadata, 'provider'));
        $this->assertSame('uploaded', data_get($storyMedia->metadata, 'upload_state'));
        $this->assertSame('r2_temp', data_get($storyMedia->metadata, 'temp_disk'));
        $this->assertSame('r2_final', data_get($storyMedia->metadata, 'final_disk'));
    }

    public function test_story_publish_dispatches_processing_for_r2_temp_video(): void
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
            ->assertJsonPath('data.status', StoryStatus::PROCESSING->value);

        $frame->refresh();
        $media = $frame->media()->firstOrFail();

        $this->assertSame(StoryStatus::PROCESSING, $frame->status);
        $this->assertSame(MediaStatus::PROCESSING, $media->status);
        $this->assertSame('queued', data_get($media->metadata, 'processing_state'));

        Queue::assertPushed(ProcessStoryVideo::class);
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
