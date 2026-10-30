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
    use \Tests\Support\CreatesVideoFixture;

    public function test_direct_only_chat_blocks_video_fallback_but_not_text_validation(): void
    {
        config(['media.uploads.video.direct_only' => true, 'media.cloudflare.r2.direct_upload_enabled' => false]);
        [$sender, $recipient, $chat] = $this->createDirectChat();
        $this->actingAs($sender)->withoutMiddleware();
        $this->postJson('/api/messenger/send', ['chat_id' => $chat->chat_id, 'media_type' => 'video'])
            ->assertStatus(409)->assertJsonPath('code', 'direct_upload_required');
        $this->postJson('/api/v1/chats/' . $chat->chat_id . '/media/video/direct/create', ['size' => 1024])
            ->assertStatus(503)->assertJsonPath('code', 'direct_upload_unavailable');
        $this->postJson('/api/messenger/send', ['content' => 'hello'])->assertUnprocessable();
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_chat_worker_transcodes_r2_even_when_legacy_compression_flag_is_disabled(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\User\Chat\MessageMediaReadyEvent::class]);
        $original = $this->createVideoFixture();
        config(['chat.enable_video_compression' => false]);
        [$sender, $recipient, $chat] = $this->createDirectChat();
        $message = $chat->messages()->create([
            'user_id' => $sender->id, 'chat_uuid' => $chat->chat_id,
            'participant_id' => $chat->participants()->where('user_id', $sender->id)->first()->id,
            'type' => \App\Enums\Chat\MessageType::VIDEO, 'content' => null,
        ]);
        $media = $message->media()->create($original);
        (new ProcessChatVideo($message))->handle();
        $this->assertOptimizedVideo($media, $original);
        $path = $media->source_path;
        (new ProcessChatVideo($message))->handle();
        $this->assertSame($path, $media->refresh()->source_path);
        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\User\Chat\MessageMediaReadyEvent::class);
    }

    public function test_legacy_chat_video_upload_rejects_video_bytes_at_the_api_server(): void
    {
        [$sender, $recipient, $chat] = $this->createDirectChat();
        $this->actingAs($sender)->withoutMiddleware()->postJson('/api/messenger/send', [
            'chat_id' => $chat->chat_id,
            'media_type' => 'video',
            'media_duration' => 14,
            'media' => UploadedFile::fake()->create('chat-video.mp4', 1024, 'video/mp4'),
        ])->assertStatus(409)->assertJsonPath('code', 'direct_upload_required');

        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_chat_direct_upload_queues_once_and_checks_ownership_and_size(): void
    {
        Bus::fake();
        Notification::fake();
        \Illuminate\Support\Facades\Event::fake([\App\Events\User\Chat\MessageReceivedEvent::class]);
        \Illuminate\Support\Facades\Storage::fake('r2_temp');
        \Illuminate\Support\Facades\Storage::fake('r2_final');
        config(['filesystems.disks.r2_final.enabled' => true]);
        app()->instance(\App\Services\Media\Cloudflare\R2DirectUploadService::class, new \Tests\Support\FakeR2DirectUploadService());
        [$sender, $recipient, $chat] = $this->createDirectChat();
        Sanctum::actingAs($sender);
        $base = '/api/v1/chats/' . $chat->chat_id . '/media/video/direct/';
        $clientUid = (string) Str::uuid();
        $createPayload = [
            'name' => 'sample.mp4', 'size' => 1024, 'duration_seconds' => 10,
            'extension' => 'mp4', 'client_uid' => $clientUid,
        ];
        $created = $this->postJson($base . 'create', [
            ...$createPayload,
        ])->assertCreated()->assertJsonPath('data.direct_upload', true);
        $upload = $created->json('data');
        $duplicateCreate = $this->postJson($base . 'create', $createPayload)
            ->assertOk()
            ->assertJsonPath('data.already_received', true);
        $this->assertSame($upload['message']['id'], $duplicateCreate->json('data.result.message.id'));
        $this->assertSame(1, $chat->messages()->where('client_uid', $clientUid)->count());
        $this->postJson($base . 'create', [...$createPayload, 'name' => 'different.mp4'])->assertStatus(409);
        $payload = ['media_id' => $upload['media_id'], 'uid' => $upload['uid'],
            'upload_id' => $upload['upload_id'], 'parts' => [['part_number' => 1, 'etag' => 'test']]];
        Sanctum::actingAs($recipient);
        $this->postJson($base . 'complete', $payload)->assertNotFound();
        Sanctum::actingAs($sender);
        $this->postJson($base . 'complete', $payload)->assertUnprocessable();
        \Illuminate\Support\Facades\Storage::disk('r2_temp')->put($upload['uid'], 'wrong size');
        $this->postJson($base . 'complete', $payload)->assertUnprocessable();
        \Illuminate\Support\Facades\Storage::disk('r2_temp')->put($upload['uid'], str_repeat('x', 1024));
        $this->postJson($base . 'complete', $payload)->assertOk()
            ->assertJsonPath('data.media.metadata.processing_state', 'queued')
            ->assertJsonPath('data.media.source_url', null);
        $this->postJson($base . 'complete', $payload)->assertOk();
        \Illuminate\Support\Facades\Event::assertDispatchedTimes(\App\Events\User\Chat\MessageReceivedEvent::class, 1);
        Notification::assertSentToTimes($recipient, \App\Notifications\User\Chat\MessageReceivedNotification::class, 1);
        Bus::assertDispatchedTimes(ProcessChatVideo::class, 1);
        Bus::assertNotDispatchedAfterResponse(ProcessChatVideo::class);
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
