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

    public function test_legacy_audio_init_is_rejected_without_creating_an_orphan_message(): void
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

        $response->assertStatus(409)->assertJsonPath('code', 'direct_upload_required');
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('media', 0);
        Event::assertNotDispatched(MessageReceivedEvent::class);
    }

    public function test_audio_upload_direct_ready_marks_media_processed_and_dispatches_ready_event(): void
    {
        [$sender] = $this->createDirectChat();
        Sanctum::actingAs($sender);

        $this->post('/api/messenger/audio/999/upload', [
            'audio' => \Illuminate\Http\UploadedFile::fake()->create('voice-note.m4a', 16, 'audio/mp4'),
        ])->assertStatus(409)->assertJsonPath('code', 'direct_upload_required');
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_audio_upload_webm_queues_processing_job(): void
    {
        [$sender] = $this->createDirectChat();
        Sanctum::actingAs($sender);

        $this->post('/api/messenger/audio/999/upload', [
            'audio' => UploadedFile::fake()->createWithContent('voice-note.webm', hex2bin('1A45DFA3934282847765626D')),
        ])->assertStatus(409)->assertJsonPath('code', 'direct_upload_required');
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('media', 0);
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
        $participantId = $chat->participants()->where('user_id', me()->id)->value('id');
        $message = $chat->messages()->create([
            'content' => null, 'user_id' => me()->id, 'chat_uuid' => $chat->chat_id,
            'participant_id' => $participantId, 'type' => \App\Enums\Chat\MessageType::AUDIO,
            'text_language' => '',
        ]);
        $message->media()->create([
            'source_path' => '', 'type' => MediaType::AUDIO, 'status' => MediaStatus::PROCESSING,
            'disk' => '', 'extension' => '', 'mime' => '', 'size' => 0,
            'metadata' => ['duration_seconds' => $durationSeconds, 'file_name' => $fileName,
                'requested_extension' => $extension, 'requested_mime' => $mimeType,
                'processing_state' => 'waiting_for_upload'],
        ]);

        return (int) $message->id;
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
