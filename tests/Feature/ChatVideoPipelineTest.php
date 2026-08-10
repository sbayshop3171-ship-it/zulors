<?php

namespace Tests\Feature;

use App\Constants\Filesystem;
use App\Enums\Chat\ChatType;
use App\Enums\Media\MediaStatus;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Jobs\User\Chat\ProcessChatVideo;
use App\Models\Chat;
use App\Models\User;
use App\Services\Filesystem\Upload\ImageUploadService;
use App\Services\Filesystem\Upload\VideoThumbnailService;
use App\Services\Filesystem\Upload\VideoUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatVideoPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_video_upload_is_queued_through_r2_temp_pipeline(): void
    {
        Bus::fake();
        Notification::fake();

        config([
            'filesystems.disks.r2_temp' => [
                'driver' => 'local',
                'root' => storage_path('app/testing/r2-temp'),
                'enabled' => true,
            ],
            'filesystems.disks.r2_final' => [
                'driver' => 'local',
                'root' => storage_path('app/testing/r2-final'),
                'enabled' => true,
            ],
            'media.cloudflare.r2.temp_disk' => 'r2_temp',
            'media.cloudflare.r2.final_disk' => 'r2_final',
        ]);

        [$sender, $recipient, $chat] = $this->createDirectChat();
        $thumbnailPath = $this->makeTempFile('chat-thumb.jpg', 'thumb');

        $this->mock(VideoUploadService::class, function($mock) {
            $mock->shouldReceive('tempSaveLocally')->once()->andReturn([
                'disk' => 'local',
                'video_path' => 'tmp/videos/chat-upload.mp4',
                'duration' => parse_duration(14),
                'seconds' => 14,
                'dimensions' => ['width' => 720, 'height' => 1280],
                'aspect_ratio' => 0.5625,
                'is_portrait' => true,
            ]);
            $mock->shouldReceive('setStorageDisk')->once()->with('r2_temp')->andReturnSelf();
            $mock->shouldReceive('setNamespace')->once()->with(Filesystem::mediaNamespace('chats/raw_videos'))->andReturnSelf();
            $mock->shouldReceive('setDefaultExtension')->once()->with('mp4')->andReturnSelf();
            $mock->shouldReceive('upload')->once()->with(storage_local_path('tmp/videos/chat-upload.mp4'))->andReturn([
                'disk' => 'r2_temp',
                'video_path' => 'uploads/chats/raw_videos/chat-upload.mp4',
                'video_size' => 456789,
            ]);
        });

        $this->mock(VideoThumbnailService::class, function($mock) use ($thumbnailPath) {
            $mock->shouldReceive('setSecondsOffset')->once()->with(1)->andReturnSelf();
            $mock->shouldReceive('generateThumbnail')->once()->with('tmp/videos/chat-upload.mp4')->andReturn($thumbnailPath);
        });

        $this->mock(ImageUploadService::class, function($mock) use ($thumbnailPath) {
            $mock->shouldReceive('load')->once()->with($thumbnailPath)->andReturnSelf();
            $mock->shouldReceive('setNamespace')->once()->with(Filesystem::mediaNamespace('chats/video_thumbnails'))->andReturnSelf();
            $mock->shouldReceive('setStorageDisk')->once()->with('r2_final')->andReturnSelf();
            $mock->shouldReceive('compress')->once()->andReturnSelf();
            $mock->shouldReceive('upload')->once()->andReturn([
                'disk' => 'r2_final',
                'image_path' => 'uploads/chats/video_thumbnails/chat-thumb.webp',
                'image_size' => 12345,
            ]);
        });

        Sanctum::actingAs($sender);

        $response = $this->postJson('/api/messenger/send', [
            'chat_id' => $chat->chat_id,
            'media_type' => 'video',
            'media_duration' => 14,
            'media' => UploadedFile::fake()->create('chat-video.mp4', 1024, 'video/mp4'),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.type', 'video')
            ->assertJsonPath('data.relations.media.type', 'video')
            ->assertJsonPath('data.relations.media.status', MediaStatus::PROCESSING->value)
            ->assertJsonPath('data.relations.media.metadata.provider', 'r2_temp')
            ->assertJsonPath('data.relations.media.metadata.temp_disk', 'r2_temp')
            ->assertJsonPath('data.relations.media.metadata.final_disk', 'r2_final');

        $message = $chat->messages()->with('media')->latest('id')->firstOrFail();
        $media = $message->media;

        $this->assertNotNull($media);
        $this->assertSame('r2_temp', $media->disk);
        $this->assertSame(MediaStatus::PROCESSING, $media->status);
        $this->assertSame('r2_temp', data_get($media->metadata, 'provider'));
        $this->assertSame('uploaded', data_get($media->metadata, 'upload_state'));
        $this->assertSame('r2_temp', data_get($media->metadata, 'temp_disk'));
        $this->assertSame('r2_final', data_get($media->metadata, 'final_disk'));
        $this->assertSame(14, data_get($media->metadata, 'duration_seconds'));

        Bus::assertDispatchedAfterResponse(ProcessChatVideo::class);
    }

    private function createDirectChat(): array
    {
        $sender = $this->createUser('video-sender-' . Str::lower(Str::random(6)));
        $recipient = $this->createUser('video-recipient-' . Str::lower(Str::random(6)));
        $chat = Chat::query()->create([
            'chat_id' => (string) Str::uuid(),
            'type' => ChatType::DIRECT,
            'last_activity' => now(),
        ]);

        $chat->participants()->create([
            'user_id' => $sender->id,
            'last_read_message_id' => 0,
            'metadata' => ['color' => '#111111'],
            'joined_at' => now(),
        ]);

        $chat->participants()->create([
            'user_id' => $recipient->id,
            'last_read_message_id' => 0,
            'metadata' => ['color' => '#4f46e5'],
            'joined_at' => now(),
        ]);

        return [$sender, $recipient, $chat];
    }

    private function createUser(string $username): User
    {
        return User::query()->create([
            'first_name' => 'Video',
            'last_name' => 'Tester',
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
            'type' => UserType::AUTHOR,
        ]);
    }

    private function makeTempFile(string $name, string $contents): string
    {
        $path = sys_get_temp_dir() . '/' . Str::uuid() . '-' . $name;
        file_put_contents($path, $contents);

        return $path;
    }
}
