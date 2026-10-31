<?php

namespace Tests\Feature;

use App\Enums\Chat\ChatType;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Models\Chat;
use App\Models\User;
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use App\Services\Filesystem\Upload\AudioUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatAudioMessageProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_chat_audio_body_is_rejected_before_message_creation(): void
    {
        Notification::fake();
        config(['chat.processing.audio.preferred_extension' => '']);

        [$sender, $recipient, $chat] = $this->createDirectChat();

        Sanctum::actingAs($sender);

        $response = $this->postJson('/api/messenger/send', [
            'chat_id' => $chat->chat_id,
            'media_type' => 'audio',
            'media_duration' => 1,
            'media' => $this->makeWaveUpload('voice-message.wav'),
        ]);

        $response->assertStatus(409)->assertJsonPath('code', 'direct_upload_required');
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_chat_validation_configuration_allows_mobile_audio_extensions_and_mime_types(): void
    {
        $this->assertStringContainsString('m4a', config('chat.validation.message.media.mimes'));
        $this->assertStringContainsString('wav', config('chat.validation.message.media.mimes'));
        $this->assertStringContainsString('audio/mp4', config('chat.validation.message.media.mimetypes'));
        $this->assertStringContainsString('audio/webm', config('chat.validation.message.media.mimetypes'));
        $this->assertStringContainsString('audio/x-wav', config('chat.validation.message.media.mimetypes'));
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

    private function makeWaveUpload(string $name): UploadedFile
    {
        $sampleRate = 8000;
        $channels = 1;
        $bitsPerSample = 16;
        $byteRate = $sampleRate * $channels * ($bitsPerSample / 8);
        $blockAlign = $channels * ($bitsPerSample / 8);

        $waveHeader = 'RIFF'
            . pack('V', 36)
            . 'WAVE'
            . 'fmt '
            . pack('V', 16)
            . pack('v', 1)
            . pack('v', $channels)
            . pack('V', $sampleRate)
            . pack('V', $byteRate)
            . pack('v', $blockAlign)
            . pack('v', $bitsPerSample)
            . 'data'
            . pack('V', 0);

        return UploadedFile::fake()->createWithContent($name, $waveHeader);
    }
}
