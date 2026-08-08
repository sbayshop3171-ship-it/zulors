<?php

namespace Tests\Feature;

use App\Enums\Call\CallMediaType;
use App\Enums\Call\CallStatus;
use App\Enums\Chat\ChatType;
use App\Enums\Chat\MessageType;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Jobs\User\Chat\ExpireRingingCallJob;
use App\Models\Block;
use App\Models\CallSession;
use App\Models\Chat;
use App\Models\User;
use App\Notifications\User\Call\IncomingCallNotification;
use App\Notifications\User\Call\MissedCallNotification;
use App\Services\Calls\CallLifecycleService;
use App\Services\Notifications\NotificationActionTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AudioCallsTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_start_direct_audio_call(): void
    {
        Notification::fake();
        Queue::fake();

        [$caller, $receiver, $chat] = $this->createDirectChat();

        Sanctum::actingAs($caller);

        $this->postJson('/api/messenger/calls/start', [
            'chat_id' => $chat->chat_id,
            'media_type' => 'audio',
        ])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.call.media_type', 'audio')
            ->assertJsonPath('data.call.status', 'ringing');

        $this->assertDatabaseHas('call_sessions', [
            'chat_id' => $chat->id,
            'initiator_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'media_type' => CallMediaType::AUDIO->value,
            'status' => CallStatus::RINGING->value,
        ]);

        $this->assertSame(2, CallSession::query()->firstOrFail()->participants()->count());
        Notification::assertSentTo($receiver, IncomingCallNotification::class);
        Queue::assertPushed(ExpireRingingCallJob::class);
    }

    public function test_non_participant_cannot_start_or_signal_call(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();
        $outsider = $this->createUser('call-outsider-' . Str::lower(Str::random(6)));
        $call = $this->createRingingCall($caller, $receiver, $chat);

        Sanctum::actingAs($outsider);

        $this->postJson('/api/messenger/calls/start', [
            'chat_id' => $chat->chat_id,
        ])->assertNotFound();

        $this->postJson("/api/messenger/calls/{$call->call_uuid}/signal", [
            'signal_type' => 'offer',
            'signal' => ['type' => 'offer', 'sdp' => 'test'],
        ])->assertNotFound();
    }

    public function test_blocked_direct_chat_user_cannot_start_call(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();

        Block::query()->create([
            'blocker_id' => $receiver->id,
            'blocked_id' => $caller->id,
        ]);

        Sanctum::actingAs($caller);

        $this->postJson('/api/messenger/calls/start', [
            'chat_id' => $chat->chat_id,
        ])->assertForbidden();
    }

    public function test_receiver_can_answer_and_either_side_can_end_call(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat);

        Sanctum::actingAs($receiver);

        $this->postJson("/api/messenger/calls/{$call->call_uuid}/answer")
            ->assertOk()
            ->assertJsonPath('data.call.status', 'accepted');

        $this->assertDatabaseHas('call_sessions', [
            'id' => $call->id,
            'status' => CallStatus::ACCEPTED->value,
            'receiver_id' => $receiver->id,
        ]);

        Sanctum::actingAs($caller);

        $this->postJson("/api/messenger/calls/{$call->call_uuid}/end")
            ->assertOk()
            ->assertJsonPath('data.call.status', 'ended');

        $this->assertDatabaseHas('call_sessions', [
            'id' => $call->id,
            'status' => CallStatus::ENDED->value,
            'end_reason' => 'user_ended',
        ]);

        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'type' => MessageType::CALL->value,
        ]);
    }

    public function test_receiver_can_decline_call(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat);

        Sanctum::actingAs($receiver);

        $this->postJson("/api/messenger/calls/{$call->call_uuid}/decline")
            ->assertOk()
            ->assertJsonPath('data.call.status', 'declined');

        $this->assertDatabaseHas('call_sessions', [
            'id' => $call->id,
            'status' => CallStatus::DECLINED->value,
            'end_reason' => 'declined',
        ]);
    }

    public function test_busy_user_rejects_new_call(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();
        [$thirdCaller, $thirdReceiver, $thirdChat] = $this->createDirectChatWith($receiver);

        $this->createRingingCall($caller, $receiver, $chat);

        Sanctum::actingAs($thirdCaller);

        $this->postJson('/api/messenger/calls/start', [
            'chat_id' => $thirdChat->chat_id,
        ])->assertConflict()
            ->assertJsonPath('data.status', 'busy');
    }

    public function test_expired_ringing_call_becomes_missed_and_creates_history_card(): void
    {
        Notification::fake();

        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat, [
            'expires_at' => now()->subSecond(),
        ]);

        (new ExpireRingingCallJob($call->call_uuid))->handle(app(CallLifecycleService::class));

        $this->assertDatabaseHas('call_sessions', [
            'id' => $call->id,
            'status' => CallStatus::MISSED->value,
            'end_reason' => 'no_answer',
        ]);

        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'user_id' => $caller->id,
            'type' => MessageType::CALL->value,
            'content' => 'Missed voice call',
        ]);

        Notification::assertSentTo($receiver, MissedCallNotification::class);
    }

    public function test_android_call_push_payload_contains_short_action_token(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat)->load(['chat', 'initiator', 'receiver']);
        $payload = (new IncomingCallNotification($call))->toPush($receiver);

        $this->assertSame('call.incoming', $payload['type']);
        $this->assertSame('zulors_calls', $payload['channel_id']);
        $this->assertSame($call->call_uuid, $payload['data']['call_id']);
        $this->assertSame($chat->chat_id, $payload['data']['chat_id']);
        $this->assertNotEmpty($payload['data']['action_token']);

        $verifiedToken = app(NotificationActionTokenService::class)->verify($payload['data']['action_token'], 'decline');

        $this->assertSame($receiver->id, $verifiedToken['user_id']);
        $this->assertSame($chat->chat_id, $verifiedToken['chat_uuid']);
        $this->assertSame($call->call_uuid, $verifiedToken['call_uuid']);
    }

    public function test_push_decline_action_ends_ringing_call(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat);
        $token = app(NotificationActionTokenService::class)->make(
            userId: $receiver->id,
            chatUuid: $chat->chat_id,
            actions: ['decline'],
            callUuid: $call->call_uuid
        );

        $this->postJson('/api/push-actions/decline-call', [
            'token' => $token,
        ])->assertOk()
            ->assertJsonPath('data.call.status', 'declined');

        $this->assertDatabaseHas('call_sessions', [
            'id' => $call->id,
            'status' => CallStatus::DECLINED->value,
        ]);
    }

    private function createDirectChat(): array
    {
        return $this->createDirectChatWith();
    }

    private function createDirectChatWith(?User $knownUser = null): array
    {
        $sender = $this->createUser('call-sender-' . Str::lower(Str::random(6)));
        $recipient = $knownUser ?: $this->createUser('call-recipient-' . Str::lower(Str::random(6)));
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

    private function createRingingCall(User $caller, User $receiver, Chat $chat, array $attributes = []): CallSession
    {
        $call = CallSession::query()->create(array_merge([
            'call_uuid' => (string) Str::uuid(),
            'chat_id' => $chat->id,
            'initiator_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'media_type' => CallMediaType::AUDIO,
            'status' => CallStatus::RINGING,
            'started_at' => now(),
            'expires_at' => now()->addSeconds(45),
        ], $attributes));

        $call->participants()->createMany([
            [
                'user_id' => $caller->id,
                'role' => 'caller',
                'status' => CallStatus::RINGING,
                'joined_at' => now(),
            ],
            [
                'user_id' => $receiver->id,
                'role' => 'receiver',
                'status' => CallStatus::RINGING,
            ],
        ]);

        return $call->load(['chat', 'initiator', 'receiver']);
    }

    private function createUser(string $username): User
    {
        return User::query()->create([
            'first_name' => 'Call',
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
