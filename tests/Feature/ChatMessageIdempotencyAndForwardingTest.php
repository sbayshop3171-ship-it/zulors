<?php

namespace Tests\Feature;

use App\Actions\Media\DeleteMediaAction;
use App\Enums\Chat\ChatType;
use App\Enums\Chat\MessageType;
use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Events\User\Chat\MessageReceivedEvent;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use App\Notifications\User\Chat\MessageReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatMessageIdempotencyAndForwardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_send_with_same_client_uid_returns_the_same_message_once(): void
    {
        [$sender, $recipient, $chat] = $this->createDirectChat();
        Sanctum::actingAs($sender);
        Event::fake([MessageReceivedEvent::class]);
        Notification::fake();

        $payload = [
            'chat_id' => $chat->chat_id,
            'content' => 'Hello once',
            'client_uid' => (string) Str::uuid(),
        ];

        $first = $this->postJson('/api/messenger/send', $payload)->assertOk();
        $second = $this->postJson('/api/messenger/send', $payload)->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, $chat->messages()->where('client_uid', $payload['client_uid'])->count());
        Event::assertDispatched(MessageReceivedEvent::class, 1);
        Notification::assertSentToTimes($recipient, MessageReceivedNotification::class, 1);
    }

    public function test_reusing_client_uid_for_different_content_is_rejected(): void
    {
        [$sender, , $chat] = $this->createDirectChat();
        Sanctum::actingAs($sender);
        $clientUid = (string) Str::uuid();

        $this->postJson('/api/messenger/send', [
            'chat_id' => $chat->chat_id,
            'content' => 'First content',
            'client_uid' => $clientUid,
        ])->assertOk();

        $this->postJson('/api/messenger/send', [
            'chat_id' => $chat->chat_id,
            'content' => 'Different content',
            'client_uid' => $clientUid,
        ])->assertStatus(409);

        $this->assertSame(1, $chat->messages()->where('client_uid', $clientUid)->count());
    }

    public function test_forward_creates_attributed_message_and_retry_is_idempotent(): void
    {
        [$sender, $sourceRecipient, $sourceChat] = $this->createDirectChat();
        [, $destinationRecipient, $destinationChat] = $this->createDirectChat($sender);
        $sourceMessage = $sourceChat->messages()->create([
            'chat_uuid' => $sourceChat->chat_id,
            'user_id' => $sourceRecipient->id,
            'participant_id' => $sourceChat->participants()->where('user_id', $sourceRecipient->id)->value('id'),
            'content' => 'Forward this text',
            'text_language' => 'en',
            'type' => MessageType::TEXT,
        ]);

        Sanctum::actingAs($sender);
        Event::fake([MessageReceivedEvent::class]);
        Notification::fake();
        $payload = [
            'destination_chat_id' => $destinationChat->chat_id,
            'client_uid' => (string) Str::uuid(),
        ];

        $first = $this->postJson("/api/messenger/chat/message/{$sourceMessage->id}/forward", $payload)->assertOk();
        $second = $this->postJson("/api/messenger/chat/message/{$sourceMessage->id}/forward", $payload)->assertOk();
        $sourceMessage->update(['is_deleted' => true]);
        $retryAfterSourceDelete = $this->postJson("/api/messenger/chat/message/{$sourceMessage->id}/forward", $payload)->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame($first->json('data.id'), $retryAfterSourceDelete->json('data.id'));
        $this->assertSame($sender->id, $first->json('data.user_id'));
        $this->assertSame('Forward this text', $first->json('data.content'));
        $this->assertSame($sourceRecipient->id, $first->json('data.meta.forwarded_from.user.id'));
        $this->assertSame(1, $destinationChat->messages()->where('client_uid', $payload['client_uid'])->count());
        Event::assertDispatched(MessageReceivedEvent::class, 1);
        Notification::assertSentToTimes($destinationRecipient, MessageReceivedNotification::class, 1);
    }

    public function test_forwarded_media_survives_deleting_the_source_message_media(): void
    {
        Storage::fake('public');
        [$sender, $sourceAuthor, $sourceChat] = $this->createDirectChat();
        [, , $destinationChat] = $this->createDirectChat($sender);
        $sourceMessage = $sourceChat->messages()->create([
            'chat_uuid' => $sourceChat->chat_id,
            'user_id' => $sourceAuthor->id,
            'participant_id' => $sourceChat->participants()->where('user_id', $sourceAuthor->id)->value('id'),
            'content' => 'Photo caption',
            'text_language' => 'en',
            'type' => MessageType::IMAGE,
        ]);
        Storage::disk('public')->put('uploads/chats/images/source.png', 'image-data');
        $sourceMedia = $sourceMessage->media()->create([
            'source_path' => 'uploads/chats/images/source.png',
            'thumbnail_path' => '',
            'type' => MediaType::IMAGE,
            'status' => MediaStatus::PROCESSED,
            'disk' => 'public',
            'thumbnail_disk' => 'public',
            'extension' => 'png',
            'mime' => 'image/png',
            'size' => 10,
            'thumbnail_size' => 0,
            'metadata' => [],
        ]);

        Sanctum::actingAs($sender);

        $response = $this->postJson("/api/messenger/chat/message/{$sourceMessage->id}/forward", [
            'destination_chat_id' => $destinationChat->chat_id,
            'client_uid' => (string) Str::uuid(),
        ])->assertOk();

        $forwardedMediaPath = Message::query()->findOrFail($response->json('data.id'))->media->source_path;
        $this->assertNotSame($sourceMedia->source_path, $forwardedMediaPath);
        Storage::disk('public')->assertExists($forwardedMediaPath);

        (new DeleteMediaAction($sourceMedia))->execute();

        Storage::disk('public')->assertMissing('uploads/chats/images/source.png');
        Storage::disk('public')->assertExists($forwardedMediaPath);
    }

    public function test_non_participant_cannot_forward_a_message(): void
    {
        [, $author, $sourceChat] = $this->createDirectChat();
        [, , $destinationChat] = $this->createDirectChat();
        $outsider = $this->createUser('outsider');
        $sourceMessage = $sourceChat->messages()->create([
            'chat_uuid' => $sourceChat->chat_id,
            'user_id' => $author->id,
            'participant_id' => $sourceChat->participants()->where('user_id', $author->id)->value('id'),
            'content' => 'Private text',
            'text_language' => 'en',
            'type' => MessageType::TEXT,
        ]);

        Sanctum::actingAs($outsider);

        $this->postJson("/api/messenger/chat/message/{$sourceMessage->id}/forward", [
            'destination_chat_id' => $destinationChat->chat_id,
            'client_uid' => (string) Str::uuid(),
        ])->assertNotFound();

        $this->assertSame(0, $destinationChat->messages()->count());
    }

    private function createDirectChat(?User $knownUser = null): array
    {
        $first = $knownUser ?: $this->createUser('chat-first');
        $second = $this->createUser('chat-second');
        $chat = Chat::query()->create([
            'chat_id' => (string) Str::uuid(),
            'type' => ChatType::DIRECT,
            'last_activity' => now(),
        ]);

        foreach ([$first, $second] as $user) {
            $chat->participants()->create([
                'user_id' => $user->id,
                'last_read_message_id' => 0,
                'metadata' => ['color' => '#4f46e5'],
                'joined_at' => now(),
            ]);
        }

        return [$first, $second, $chat];
    }

    private function createUser(string $prefix): User
    {
        $username = $prefix . '-' . Str::lower(Str::random(8));

        return User::query()->create([
            'first_name' => 'Chat',
            'last_name' => 'Tester',
            'username' => $username,
            'caption' => '@' . $username,
            'email' => $username . '@example.com',
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
