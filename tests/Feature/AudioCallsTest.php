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
use App\Notifications\User\Call\CancelCallNotification;
use App\Notifications\User\Call\IncomingCallNotification;
use App\Notifications\User\Call\MissedCallNotification;
use App\Services\Calls\CallLifecycleService;
use App\Services\Notifications\PushNotificationPayloadFactory;
use App\Services\Notifications\NotificationActionTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
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

    public function test_started_call_uses_forty_second_ring_window(): void
    {
        Notification::fake();
        Queue::fake();

        [$caller, $receiver, $chat] = $this->createDirectChat();

        Sanctum::actingAs($caller);

        $this->postJson('/api/messenger/calls/start', [
            'chat_id' => $chat->chat_id,
            'media_type' => 'audio',
        ])->assertOk();

        $call = CallSession::query()->firstOrFail();
        $ringWindow = $call->started_at->diffInSeconds($call->expires_at);

        $this->assertGreaterThanOrEqual(39, $ringWindow);
        $this->assertLessThanOrEqual(40, $ringWindow);
    }

    public function test_video_calls_are_deferred_for_audio_v1(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();

        Sanctum::actingAs($caller);

        $this->postJson('/api/messenger/calls/start', [
            'chat_id' => $chat->chat_id,
            'media_type' => 'video',
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('call_sessions', [
            'chat_id' => $chat->id,
            'initiator_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'media_type' => CallMediaType::VIDEO->value,
        ]);
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

        $this->getJson("/api/messenger/calls/{$call->call_uuid}/media-token")
            ->assertNotFound();
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

    public function test_connection_lost_end_marks_call_failed(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat, [
            'status' => CallStatus::CONNECTED,
            'answered_at' => now()->subSeconds(20),
            'connected_at' => now()->subSeconds(18),
        ]);

        Sanctum::actingAs($caller);

        $this->postJson("/api/messenger/calls/{$call->call_uuid}/end", [
            'reason' => 'connection_lost',
        ])->assertOk()
            ->assertJsonPath('data.call.status', 'failed');

        $this->assertDatabaseHas('call_sessions', [
            'id' => $call->id,
            'status' => CallStatus::FAILED->value,
            'end_reason' => 'connection_lost',
        ]);

        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'type' => MessageType::CALL->value,
            'content' => 'Voice call failed',
        ]);
    }

    public function test_no_answer_end_marks_ringing_call_missed(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat);

        Sanctum::actingAs($receiver);

        $this->postJson("/api/messenger/calls/{$call->call_uuid}/end", [
            'reason' => 'no_answer',
        ])->assertOk()
            ->assertJsonPath('data.call.status', 'missed');

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

    public function test_receiver_can_mark_incoming_call_busy(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat);

        Sanctum::actingAs($receiver);

        $this->postJson("/api/messenger/calls/{$call->call_uuid}/busy")
            ->assertOk()
            ->assertJsonPath('data.call.status', 'busy');

        $this->assertDatabaseHas('call_sessions', [
            'id' => $call->id,
            'status' => CallStatus::BUSY->value,
            'end_reason' => 'busy',
        ]);

        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'user_id' => $receiver->id,
            'type' => MessageType::CALL->value,
            'content' => 'Voice call busy',
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
        $this->assertSame('high', $payload['android']['priority']);
        $this->assertSame('40s', $payload['android']['ttl']);
        $this->assertSame($call->call_uuid, $payload['data']['call_id']);
        $this->assertSame($chat->chat_id, $payload['data']['chat_id']);
        $this->assertSame('incoming_call', $payload['data']['ringtone']);
        $this->assertSame('call', $payload['data']['notification_category']);
        $this->assertSame('public', $payload['data']['notification_visibility']);
        $this->assertNotEmpty($payload['data']['action_token']);

        $fcmMessage = app(PushNotificationPayloadFactory::class)->make($receiver, new IncomingCallNotification($call));

        $this->assertSame('high', $fcmMessage['android']['priority']);
        $this->assertSame('40s', $fcmMessage['android']['ttl']);
        $this->assertSame('incoming_call', $fcmMessage['data']['ringtone']);

        $verifiedToken = app(NotificationActionTokenService::class)->verify($payload['data']['action_token'], 'decline');

        $this->assertSame($receiver->id, $verifiedToken['user_id']);
        $this->assertSame($chat->chat_id, $verifiedToken['chat_uuid']);
        $this->assertSame($call->call_uuid, $verifiedToken['call_uuid']);
    }

    public function test_android_call_cancel_push_payload_clears_incoming_notification(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat, [
            'status' => CallStatus::ENDED,
            'end_reason' => 'canceled',
            'ended_at' => now(),
        ])->load(['chat', 'initiator', 'receiver']);
        $payload = (new CancelCallNotification($call))->toPush($receiver);

        $this->assertSame('call.cancel', $payload['type']);
        $this->assertSame('zulors_calls', $payload['channel_id']);
        $this->assertSame('', $payload['title']);
        $this->assertSame('', $payload['body']);
        $this->assertSame($call->call_uuid, $payload['data']['call_id']);
        $this->assertSame($chat->chat_id, $payload['data']['chat_id']);
        $this->assertSame('true', $payload['data']['cancel_notification']);
    }

    public function test_answering_call_sends_cancel_push_for_incoming_notification(): void
    {
        Notification::fake();
        Config::set('notifications.push.enabled', true);

        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat);

        Sanctum::actingAs($receiver);

        $this->postJson("/api/messenger/calls/{$call->call_uuid}/answer")
            ->assertOk()
            ->assertJsonPath('data.call.status', 'accepted');

        Notification::assertSentTo($receiver, CancelCallNotification::class);
    }

    public function test_ending_call_sends_cancel_push_for_incoming_notification(): void
    {
        Notification::fake();
        Config::set('notifications.push.enabled', true);

        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat);

        Sanctum::actingAs($caller);

        $this->postJson("/api/messenger/calls/{$call->call_uuid}/end", [
            'reason' => 'canceled',
        ])->assertOk()
            ->assertJsonPath('data.call.status', 'ended');

        Notification::assertSentTo($receiver, CancelCallNotification::class);
    }

    public function test_authenticated_user_receives_short_lived_turn_ice_servers(): void
    {
        $user = $this->createUser('call-ice-' . Str::lower(Str::random(6)));

        Config::set('services.calls.stun_urls', ['stun:stun.example.com:19302']);
        Config::set('services.calls.turn_urls', ['turn:turn.example.com:3478?transport=udp']);
        Config::set('services.calls.turn_secret', 'test-turn-secret');
        Config::set('services.calls.turn_username', null);
        Config::set('services.calls.turn_credential', null);
        Config::set('services.calls.turn_ttl_seconds', 600);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/messenger/calls/ice-servers')
            ->assertOk()
            ->assertJsonPath('data.ice_servers.0.urls', 'stun:stun.example.com:19302')
            ->assertJsonPath('data.ice_servers.1.urls', 'turn:turn.example.com:3478?transport=udp');

        $turnServer = $response->json('data.ice_servers.1');
        $expectedCredential = base64_encode(hash_hmac('sha1', $turnServer['username'], 'test-turn-secret', true));

        $this->assertStringEndsWith(':' . $user->id, $turnServer['username']);
        $this->assertSame($expectedCredential, $turnServer['credential']);
        $this->assertNotEmpty($response->json('data.expires_at'));
    }

    public function test_call_media_token_is_blocked_until_call_is_answered(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat);

        Config::set('services.calls.agora.app_id', '970CA35de60c44645bbae8a215061b33');
        Config::set('services.calls.agora.app_certificate', '5CFd2fd1755d40ecb72977518be15d3b');

        Sanctum::actingAs($receiver);

        $this->getJson("/api/messenger/calls/{$call->call_uuid}/media-token")
            ->assertConflict();
    }

    public function test_answered_call_receives_agora_media_token_when_configured(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat, [
            'status' => CallStatus::ACCEPTED,
            'answered_at' => now(),
        ]);

        Config::set('services.calls.media_provider', 'auto');
        Config::set('services.calls.agora.app_id', '970CA35de60c44645bbae8a215061b33');
        Config::set('services.calls.agora.app_certificate', '5CFd2fd1755d40ecb72977518be15d3b');
        Config::set('services.calls.agora.token_ttl_seconds', 600);
        Config::set('services.calls.agora.area_code', 'ASIA');
        Config::set('services.calls.agora.audio_encoder_profile', 'speech_low_quality');
        Config::set('services.calls.agora.audio_bitrate_kbps', 20);
        Config::set('services.calls.agora.audio_bitrate_floor_kbps', 16);
        Config::set('services.calls.agora.audio_sample_rate', 16000);

        Sanctum::actingAs($caller);

        $response = $this->getJson("/api/messenger/calls/{$call->call_uuid}/media-token")
            ->assertOk()
            ->assertJsonPath('data.media.provider', 'agora')
            ->assertJsonPath('data.media.app_id', '970CA35de60c44645bbae8a215061b33')
            ->assertJsonPath('data.media.channel', 'zulors_call_' . str_replace('-', '', $call->call_uuid))
            ->assertJsonPath('data.media.uid', $caller->id)
            ->assertJsonPath('data.media.token_ttl_seconds', 600)
            ->assertJsonPath('data.media.area_code', 'ASIA')
            ->assertJsonPath('data.media.audio_encoder_profile', 'speech_low_quality')
            ->assertJsonPath('data.media.audio_bitrate_kbps', 20)
            ->assertJsonPath('data.media.audio_bitrate_floor_kbps', 16)
            ->assertJsonPath('data.media.audio_sample_rate', 16000);

        $this->assertStringStartsWith('007', $response->json('data.media.token'));
        $this->assertNotEmpty($response->json('data.media.expires_at'));
    }

    public function test_agora_ready_signals_keep_answered_call_in_connecting_until_media_connects(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat, [
            'status' => CallStatus::ACCEPTED,
            'answered_at' => now(),
        ]);

        Sanctum::actingAs($receiver);

        $this->postJson("/api/messenger/calls/{$call->call_uuid}/signal", [
            'signal_type' => 'ready',
            'signal' => [
                'provider' => 'agora',
            ],
        ])->assertOk()
            ->assertJsonPath('data.call.status', 'connecting');

        Sanctum::actingAs($caller);

        $this->postJson("/api/messenger/calls/{$call->call_uuid}/signal", [
            'signal_type' => 'ready',
            'signal' => [
                'provider' => 'agora',
            ],
        ])->assertOk()
            ->assertJsonPath('data.call.status', 'connecting');

        $this->assertDatabaseHas('call_sessions', [
            'id' => $call->id,
            'status' => CallStatus::CONNECTING->value,
        ]);

        $this->assertNull($call->fresh()->connected_at);

        $participants = $call->fresh()->participants()->get();

        $this->assertCount(2, $participants);

        foreach($participants as $participant) {
            $this->assertSame('agora', $participant->metadata['media_provider'] ?? null);
            $this->assertNotEmpty($participant->metadata['media_ready_at'] ?? null);
        }
    }

    public function test_explicit_connected_signal_promotes_agora_call_to_connected(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat, [
            'status' => CallStatus::CONNECTING,
            'answered_at' => now()->subSeconds(5),
        ]);

        Sanctum::actingAs($caller);

        $this->postJson("/api/messenger/calls/{$call->call_uuid}/signal", [
            'signal_type' => 'connected',
            'signal' => [
                'provider' => 'agora',
            ],
        ])->assertOk()
            ->assertJsonPath('data.call.status', 'connected');

        $this->assertDatabaseHas('call_sessions', [
            'id' => $call->id,
            'status' => CallStatus::CONNECTED->value,
        ]);

        $participant = $call->fresh()->participants()->where('user_id', $caller->id)->firstOrFail();

        $this->assertSame(CallStatus::CONNECTED, $participant->status);
        $this->assertNotEmpty(data_get($participant->metadata, 'media_connected_at'));
    }

    public function test_answered_call_uses_webrtc_fallback_when_agora_is_not_configured(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat, [
            'status' => CallStatus::ACCEPTED,
            'answered_at' => now(),
        ]);

        Config::set('services.calls.media_provider', 'auto');
        Config::set('services.calls.agora.app_id', null);
        Config::set('services.calls.agora.app_certificate', null);

        Sanctum::actingAs($receiver);

        $this->getJson("/api/messenger/calls/{$call->call_uuid}/media-token")
            ->assertOk()
            ->assertJsonPath('data.media.provider', 'webrtc')
            ->assertJsonPath('data.media.reason', 'agora_not_configured');
    }

    public function test_stale_connecting_call_is_finalized_before_start_busy_check(): void
    {
        Notification::fake();
        Queue::fake();

        [$caller, $receiver, $chat] = $this->createDirectChat();
        $staleCall = $this->createRingingCall($caller, $receiver, $chat, [
            'status' => CallStatus::CONNECTING,
            'started_at' => now()->subSeconds(150),
            'answered_at' => now()->subSeconds(120),
            'expires_at' => now()->subSeconds(105),
        ]);

        Sanctum::actingAs($caller);

        $this->postJson('/api/messenger/calls/start', [
            'chat_id' => $chat->chat_id,
            'media_type' => 'audio',
        ])->assertOk()
            ->assertJsonPath('data.call.status', 'ringing');

        $this->assertDatabaseHas('call_sessions', [
            'id' => $staleCall->id,
            'status' => CallStatus::FAILED->value,
            'end_reason' => 'connection_timeout',
        ]);

        $this->assertSame(2, CallSession::query()->where('chat_id', $chat->id)->count());
    }

    public function test_stale_accepted_call_is_finalized_before_start_busy_check(): void
    {
        Notification::fake();
        Queue::fake();

        [$caller, $receiver, $chat] = $this->createDirectChat();
        $staleCall = $this->createRingingCall($caller, $receiver, $chat, [
            'status' => CallStatus::ACCEPTED,
            'started_at' => now()->subSeconds(80),
            'answered_at' => now()->subSeconds(70),
            'expires_at' => now()->subSeconds(40),
        ]);

        Sanctum::actingAs($caller);

        $this->postJson('/api/messenger/calls/start', [
            'chat_id' => $chat->chat_id,
            'media_type' => 'audio',
        ])->assertOk()
            ->assertJsonPath('data.call.status', 'ringing');

        $this->assertDatabaseHas('call_sessions', [
            'id' => $staleCall->id,
            'status' => CallStatus::FAILED->value,
            'end_reason' => 'connection_timeout',
        ]);

        $this->assertSame(2, CallSession::query()->where('chat_id', $chat->id)->count());
    }

    public function test_expired_ringing_call_is_finalized_before_start_busy_check(): void
    {
        Notification::fake();
        Queue::fake();

        [$caller, $receiver, $chat] = $this->createDirectChat();
        $staleCall = $this->createRingingCall($caller, $receiver, $chat, [
            'started_at' => now()->subSeconds(60),
            'expires_at' => now()->subSecond(),
        ]);

        Sanctum::actingAs($caller);

        $this->postJson('/api/messenger/calls/start', [
            'chat_id' => $chat->chat_id,
            'media_type' => 'audio',
        ])->assertOk()
            ->assertJsonPath('data.call.status', 'ringing');

        $this->assertDatabaseHas('call_sessions', [
            'id' => $staleCall->id,
            'status' => CallStatus::MISSED->value,
            'end_reason' => 'no_answer',
        ]);

        $this->assertSame(2, CallSession::query()->where('chat_id', $chat->id)->count());
    }

    public function test_stale_connected_call_is_finalized_before_start_busy_check(): void
    {
        Notification::fake();
        Queue::fake();

        [$caller, $receiver, $chat] = $this->createDirectChat();
        $staleCall = $this->createRingingCall($caller, $receiver, $chat, [
            'status' => CallStatus::CONNECTED,
            'started_at' => now()->subSeconds(180),
            'answered_at' => now()->subSeconds(150),
            'connected_at' => now()->subSeconds(130),
            'expires_at' => now()->subSeconds(120),
        ]);
        $staleCall->timestamps = false;
        $staleCall->forceFill([
            'updated_at' => now()->subSeconds(120),
        ])->save();

        Sanctum::actingAs($caller);

        $this->postJson('/api/messenger/calls/start', [
            'chat_id' => $chat->chat_id,
            'media_type' => 'audio',
        ])->assertOk()
            ->assertJsonPath('data.call.status', 'ringing');

        $this->assertDatabaseHas('call_sessions', [
            'id' => $staleCall->id,
            'status' => CallStatus::FAILED->value,
            'end_reason' => 'connection_timeout',
        ]);

        $this->assertSame(2, CallSession::query()->where('chat_id', $chat->id)->count());
    }

    public function test_connected_call_with_stale_remote_heartbeat_is_finalized_before_start_busy_check(): void
    {
        Notification::fake();
        Queue::fake();

        [$caller, $receiver, $chat] = $this->createDirectChat();
        $staleCall = $this->createRingingCall($caller, $receiver, $chat, [
            'status' => CallStatus::CONNECTED,
            'started_at' => now()->subSeconds(120),
            'answered_at' => now()->subSeconds(100),
            'connected_at' => now()->subSeconds(80),
            'expires_at' => now()->subSeconds(70),
        ]);

        $staleCall->participants()->where('user_id', $caller->id)->update([
            'status' => CallStatus::CONNECTED,
            'metadata' => [
                'heartbeat_at' => now()->subSeconds(5)->toIso8601String(),
                'media_connected_at' => now()->subSeconds(75)->toIso8601String(),
            ],
        ]);
        $staleCall->participants()->where('user_id', $receiver->id)->update([
            'status' => CallStatus::CONNECTED,
            'metadata' => [
                'heartbeat_at' => now()->subSeconds(80)->toIso8601String(),
                'media_connected_at' => now()->subSeconds(75)->toIso8601String(),
            ],
        ]);
        $staleCall->timestamps = false;
        $staleCall->forceFill([
            'updated_at' => now(),
        ])->save();

        Sanctum::actingAs($caller);

        $this->postJson('/api/messenger/calls/start', [
            'chat_id' => $chat->chat_id,
            'media_type' => 'audio',
        ])->assertOk()
            ->assertJsonPath('data.call.status', 'ringing');

        $this->assertDatabaseHas('call_sessions', [
            'id' => $staleCall->id,
            'status' => CallStatus::FAILED->value,
            'end_reason' => 'connection_timeout',
        ]);
    }

    public function test_call_heartbeat_refreshes_connected_call_state(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat, [
            'status' => CallStatus::CONNECTED,
            'answered_at' => now()->subSeconds(20),
            'connected_at' => now()->subSeconds(18),
        ]);

        Sanctum::actingAs($caller);

        $this->postJson("/api/messenger/calls/{$call->call_uuid}/heartbeat", [
            'status' => 'connected',
            'media_provider' => 'agora',
            'network_state' => 'stable',
        ])->assertOk()
            ->assertJsonPath('data.call.status', 'connected');

        $freshCall = $call->fresh();
        $participant = $freshCall->participants()->where('user_id', $caller->id)->firstOrFail();

        $this->assertSame(CallStatus::CONNECTED, $freshCall->status);
        $this->assertSame(CallStatus::CONNECTED, $participant->status);
        $this->assertNotEmpty(data_get($freshCall->metadata, "heartbeat.latest.{$caller->id}"));
        $this->assertNotEmpty(data_get($participant->metadata, 'heartbeat_at'));
    }

    public function test_call_quality_report_updates_call_metadata(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat, [
            'status' => CallStatus::CONNECTED,
            'answered_at' => now()->subSeconds(10),
            'connected_at' => now()->subSeconds(8),
        ]);

        Sanctum::actingAs($caller);

        $this->postJson("/api/messenger/calls/{$call->call_uuid}/quality", [
            'network_quality' => 'weak',
            'issue' => 'media_quality_weak',
            'connection_state' => 'connected',
            'ice_connection_state' => 'connected',
            'round_trip_time_ms' => 430.4,
            'jitter_ms' => 52.2,
            'packet_loss_percent' => 4.5,
            'packets_lost' => 9,
            'packets_received' => 191,
            'bytes_sent' => 1000,
            'bytes_received' => 1200,
        ])->assertOk()
            ->assertJsonPath('data.quality.accepted', true)
            ->assertJsonPath('data.quality.summary.weak_reports', 1);

        $metadata = $call->fresh()->metadata;
        $summary = data_get($metadata, "quality.summary.{$caller->id}");

        $this->assertSame(1, $summary['reports_count']);
        $this->assertSame(1, $summary['weak_reports']);
        $this->assertSame('weak', $summary['last_network_quality']);
        $this->assertSame('media_quality_weak', $summary['last_issue']);
    }

    public function test_oversized_call_signal_payload_is_rejected(): void
    {
        [$caller, $receiver, $chat] = $this->createDirectChat();
        $call = $this->createRingingCall($caller, $receiver, $chat);

        Sanctum::actingAs($caller);

        $this->postJson("/api/messenger/calls/{$call->call_uuid}/signal", [
            'signal_type' => 'offer',
            'signal' => [
                'type' => 'offer',
                'sdp' => str_repeat('a', 170000),
            ],
        ])->assertUnprocessable();
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
            'expires_at' => now()->addSeconds(40),
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
