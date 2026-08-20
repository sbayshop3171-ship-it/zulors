<?php

namespace Tests\Feature;

use App\Constants\Filesystem;
use App\Enums\Chat\ChatType;
use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Events\User\Chat\MessageDeletedEvent;
use App\Events\User\Chat\MessageMediaReadyEvent;
use App\Events\User\Chat\MessageReceivedEvent;
use App\Jobs\User\Chat\ProcessChatAudio;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use App\Services\Filesystem\Upload\AudioUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatInstantAudioMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_audio_init_creates_pending_message_and_dispatches_message_received_event(): void
    {
        Event::fake([MessageReceivedEvent::class]);
        Notification::fake();

        [$sender, $recipient, $chat] = $this->createDirectChat();

        Sanctum::actingAs($sender);

        $response = $this->postJson('/api/messenger/audio/init', [
            'chat_id' => $chat->chat_id,
            'duration_seconds' => 12,
            'extension' => 'webm',
            'mime_type' => 'audio/webm',
            'file_name' => 'voice-note.webm',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'audio')
            ->assertJsonPath('data.relations.media.type', 'audio')
            ->assertJsonPath('data.relations.media.status', MediaStatus::PROCESSING->value)
            ->assertJsonPath('data.relations.media.source_url', null)
            ->assertJsonPath('data.relations.media.metadata.duration_seconds', 12)
            ->assertJsonPath('data.relations.media.metadata.file_name', 'voice-note.webm');

        $messageData = $chat->messages()->with('media')->latest('id')->firstOrFail();

        $this->assertSame('audio', $messageData->type->value);
        $this->assertSame('', $messageData->media->source_path);
        $this->assertSame(MediaStatus::PROCESSING, $messageData->media->status);
        $this->assertSame('waiting_for_upload', data_get($messageData->media->metadata, 'processing_state'));

        Event::assertDispatched(MessageReceivedEvent::class, 1);
    }

    public function test_audio_upload_direct_ready_marks_media_processed_and_dispatches_ready_event(): void
    {
        Event::fake([MessageMediaReadyEvent::class]);
        Notification::fake();

        [$sender, $recipient, $chat] = $this->createDirectChat();

        $this->mock(RoundRobinService::class, function($mock) {
            $mock->shouldReceive('getNextDisk')->once()->andReturn('public');
        });

        $this->mock(AudioUploadService::class, function($mock) {
            $mock->shouldReceive('setStorageDisk')->twice()->andReturnSelf();
            $mock->shouldReceive('tempSaveLocally')->once()->andReturn([
                'disk' => 'public',
                'audio_path' => 'tmp/audios/direct-voice.m4a',
                'duration' => parse_duration(7),
                'duration_seconds' => 7,
            ]);
            $mock->shouldReceive('setNamespace')->once()->with(Filesystem::mediaNamespace('chats/audios'))->andReturnSelf();
            $mock->shouldReceive('setDefaultExtension')->once()->with('m4a')->andReturnSelf();
            $mock->shouldReceive('upload')->once()->with(storage_local_path('tmp/audios/direct-voice.m4a'))->andReturn([
                'disk' => 'public',
                'audio_path' => 'media/chats/audios/direct-voice.m4a',
            ]);
        });

        Sanctum::actingAs($sender);

        $messageId = $this->initPendingAudioMessage($chat, 7, 'm4a', 'audio/mp4', 'voice-note.m4a');

        $response = $this->post("/api/messenger/audio/{$messageId}/upload", [
            'audio' => \Illuminate\Http\UploadedFile::fake()->create('voice-note.m4a', 16, 'audio/mp4'),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.relations.media.status', MediaStatus::PROCESSED->value)
            ->assertJsonPath('data.relations.media.extension', 'm4a')
            ->assertJsonPath('data.relations.media.metadata.duration_seconds', 7);

        $this->assertNotNull($response->json('data.relations.media.source_url'));

        $messageData = Message::query()->with('media')->findOrFail($messageId);

        $this->assertSame(MediaStatus::PROCESSED, $messageData->media->status);
        $this->assertSame('media/chats/audios/direct-voice.m4a', $messageData->media->source_path);
        $this->assertSame('processed', data_get($messageData->media->metadata, 'processing_state'));

        Event::assertDispatched(MessageMediaReadyEvent::class, 1);
    }

    public function test_audio_upload_webm_queues_processing_job(): void
    {
        Bus::fake();
        Notification::fake();

        [$sender, $recipient, $chat] = $this->createDirectChat();

        $this->mock(RoundRobinService::class, function($mock) {
            $mock->shouldReceive('getNextDisk')->once()->andReturn('public');
        });

        $this->mock(AudioUploadService::class, function($mock) {
            $mock->shouldReceive('setStorageDisk')->once()->andReturnSelf();
            $mock->shouldReceive('tempSaveLocally')->once()->andReturn([
                'disk' => 'public',
                'audio_path' => 'tmp/audios/queued-voice.webm',
                'duration' => parse_duration(9),
                'duration_seconds' => 9,
            ]);
        });

        Sanctum::actingAs($sender);

        $messageId = $this->initPendingAudioMessage($chat, 9, 'webm', 'audio/webm', 'voice-note.webm');

        $response = $this->post("/api/messenger/audio/{$messageId}/upload", [
            'audio' => UploadedFile::fake()->createWithContent('voice-note.webm', hex2bin('1A45DFA3934282847765626D')),
        ]);

        $response->assertAccepted()
            ->assertJsonPath('data.relations.media.status', MediaStatus::PROCESSING->value)
            ->assertJsonPath('data.relations.media.source_url', null);

        $messageData = Message::query()->with('media')->findOrFail($messageId);

        $this->assertSame(MediaStatus::PROCESSING, $messageData->media->status);
        $this->assertSame('queued', data_get($messageData->media->metadata, 'processing_state'));
        $this->assertSame('tmp/audios/queued-voice.webm', data_get($messageData->media->metadata, 'temp_path'));

        Bus::assertDispatchedAfterResponse(ProcessChatAudio::class, function($job) {
            return $job->queue === config('media.queues.audio');
        });
    }

    public function test_process_chat_audio_marks_media_processed_and_dispatches_ready_event(): void
    {
        Event::fake([MessageMediaReadyEvent::class]);

        [$sender, $recipient, $chat] = $this->createDirectChat();
        $participantId = $chat->participants()->where('user_id', $sender->id)->value('id');

        Storage::disk('local')->put('tmp/audios/raw-voice.webm', 'raw-audio');
        Storage::disk('local')->put('tmp/audios/processed-voice.mp3', 'processed-audio');

        $messageData = $chat->messages()->create([
            'content' => '',
            'user_id' => $sender->id,
            'chat_uuid' => $chat->chat_id,
            'participant_id' => $participantId,
            'type' => 'audio',
        ]);

        $messageData->media()->create([
            'source_path' => 'tmp/audios/raw-voice.webm',
            'type' => MediaType::AUDIO,
            'status' => MediaStatus::PROCESSING,
            'disk' => 'local',
            'extension' => 'webm',
            'mime' => 'audio/webm',
            'size' => 16,
            'metadata' => [
                'duration' => parse_duration(2),
                'duration_seconds' => 2,
                'file_name' => 'voice-note.webm',
                'original_name' => 'voice-note.webm',
                'temp_path' => 'tmp/audios/raw-voice.webm',
                'final_disk' => 'public',
                'processing_state' => 'queued',
            ],
        ]);

        $this->mock(AudioUploadService::class, function($mock) {
            $mock->shouldReceive('transcodeToMp3')->once()->with('tmp/audios/raw-voice.webm', (int) config('chat.processing.audio.bitrate', 96))->andReturn('tmp/audios/processed-voice.mp3');
            $mock->shouldReceive('getAudioDurationSeconds')->once()->with('tmp/audios/processed-voice.mp3')->andReturn(11);
            $mock->shouldReceive('setNamespace')->once()->with(Filesystem::mediaNamespace('chats/audios'))->andReturnSelf();
            $mock->shouldReceive('setStorageDisk')->once()->with('public')->andReturnSelf();
            $mock->shouldReceive('setDefaultExtension')->once()->with('mp3')->andReturnSelf();
            $mock->shouldReceive('upload')->once()->with(storage_local_path('tmp/audios/processed-voice.mp3'))->andReturn([
                'disk' => 'public',
                'audio_path' => 'media/chats/audios/processed-voice.mp3',
            ]);
        });

        (new ProcessChatAudio($messageData))->handle();

        $messageData = $messageData->fresh()->load('media');

        $this->assertSame(MediaStatus::PROCESSED, $messageData->media->status);
        $this->assertSame('mp3', $messageData->media->extension);
        $this->assertSame('audio/mpeg', $messageData->media->mime);
        $this->assertSame('media/chats/audios/processed-voice.mp3', $messageData->media->source_path);
        $this->assertSame(11, data_get($messageData->media->metadata, 'duration_seconds'));
        $this->assertSame('processed', data_get($messageData->media->metadata, 'processing_state'));
        $this->assertFalse(Storage::disk('local')->exists('tmp/audios/raw-voice.webm'));
        $this->assertFalse(Storage::disk('local')->exists('tmp/audios/processed-voice.mp3'));

        Event::assertDispatched(MessageMediaReadyEvent::class, 1);
    }

    public function test_audio_fail_marks_message_deleted_and_dispatches_delete_event(): void
    {
        Event::fake([MessageDeletedEvent::class]);
        Notification::fake();

        [$sender, $recipient, $chat] = $this->createDirectChat();

        Sanctum::actingAs($sender);

        $messageId = $this->initPendingAudioMessage($chat, 6, 'webm', 'audio/webm', 'voice-note.webm');

        $this->post("/api/messenger/audio/{$messageId}/fail")
            ->assertNoContent();

        $messageData = Message::query()->findOrFail($messageId);

        $this->assertTrue((bool) $messageData->is_deleted);

        Event::assertDispatched(MessageDeletedEvent::class, 1);
    }

    private function initPendingAudioMessage(
        Chat $chat,
        int $durationSeconds = 8,
        string $extension = 'webm',
        string $mimeType = 'audio/webm',
        string $fileName = 'voice-note.webm'
    ): int {
        $response = $this->postJson('/api/messenger/audio/init', [
            'chat_id' => $chat->chat_id,
            'duration_seconds' => $durationSeconds,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'file_name' => $fileName,
        ]);

        $response->assertCreated();

        return (int) $response->json('data.id');
    }

    private function createDirectChat(): array
    {
        $sender = $this->createUser('voice-sender-' . Str::lower(Str::random(6)));
        $recipient = $this->createUser('voice-recipient-' . Str::lower(Str::random(6)));
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
            'first_name' => 'Voice',
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
}
