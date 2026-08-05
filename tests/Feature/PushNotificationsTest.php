<?php

namespace Tests\Feature;

use App\Actions\User\CreateUserAction;
use App\Enums\Chat\ChatType;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use App\Models\UserNotificationSettings;
use App\Models\UserPushToken;
use App\Notifications\User\Chat\MessageReceivedNotification;
use App\Services\Notifications\FirebaseCloudMessagingService;
use App\Services\Notifications\NotificationActionTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_start_with_push_notifications_enabled_by_default(): void
    {
        config([
            'wallet.default_balance' => 0,
            'app.default_currency' => 'USD',
        ]);

        $suffix = Str::lower(Str::random(8));
        $user = (new CreateUserAction([
            'first_name' => 'Push',
            'last_name' => 'Default',
            'username' => "push-default-{$suffix}",
            'caption' => '@push-default',
            'email' => "push-default-{$suffix}@example.com",
            'password' => Hash::make('password'),
            'role' => UserRole::USER,
            'status' => UserStatus::ACTIVE,
            'theme' => 'light',
        ]))->execute();

        $this->assertDatabaseHas('user_notification_settings', [
            'user_id' => $user->id,
            'type' => 'push',
            'direct_messages' => true,
            'show_message_preview' => true,
            'reactions' => true,
            'comments' => true,
            'shared_posts' => true,
            'followers' => true,
            'follow_request' => true,
            'mentions' => true,
        ]);
    }

    public function test_get_push_settings_backfills_enabled_defaults_for_users_missing_a_push_settings_row(): void
    {
        $user = $this->createUser('push-settings-missing-' . Str::lower(Str::random(6)));

        Sanctum::actingAs($user);

        $this->getJson('/api/settings/notifications/push/settings')
            ->assertOk()
            ->assertJsonPath('data.direct_messages', true)
            ->assertJsonPath('data.show_message_preview', true)
            ->assertJsonPath('data.reactions', true)
            ->assertJsonPath('data.comments', true)
            ->assertJsonPath('data.shared_posts', true)
            ->assertJsonPath('data.followers', true)
            ->assertJsonPath('data.follow_request', true)
            ->assertJsonPath('data.mentions', true);

        $this->assertDatabaseHas('user_notification_settings', [
            'user_id' => $user->id,
            'type' => 'push',
            'direct_messages' => true,
            'show_message_preview' => true,
            'reactions' => true,
            'comments' => true,
            'shared_posts' => true,
            'followers' => true,
            'follow_request' => true,
            'mentions' => true,
        ]);
    }

    public function test_updating_push_settings_creates_the_missing_row_before_saving_preferences(): void
    {
        $user = $this->createUser('push-update-missing-' . Str::lower(Str::random(6)));

        Sanctum::actingAs($user);

        $this->putJson('/api/settings/notification/push/update', [
            'direct_messages' => false,
            'show_message_preview' => false,
            'reactions' => false,
            'comments' => false,
            'shared_posts' => true,
            'followers' => false,
            'follow_request' => true,
            'mentions' => false,
        ])->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('user_notification_settings', [
            'user_id' => $user->id,
            'type' => 'push',
            'direct_messages' => false,
            'show_message_preview' => false,
            'reactions' => false,
            'comments' => false,
            'shared_posts' => true,
            'followers' => false,
            'follow_request' => true,
            'mentions' => false,
        ]);
    }

    public function test_authenticated_user_can_register_and_remove_an_fcm_push_token(): void
    {
        $user = $this->createUser('push-token-user');
        $plainToken = 'fcm-token-from-xiaomi-device';

        Sanctum::actingAs($user);

        $this->postJson('/api/settings/devices/push-token', [
            'token' => $plainToken,
            'platform' => 'android',
            'device_id' => 'xiaomi-device-id',
            'device_name' => 'Xiaomi M2010J19CI',
            'app_version' => '0.4.0-production',
        ])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.provider', 'fcm');

        $this->assertDatabaseHas('user_push_tokens', [
            'user_id' => $user->id,
            'provider' => 'fcm',
            'platform' => 'android',
            'token_hash' => hash('sha256', $plainToken),
            'device_id' => 'xiaomi-device-id',
        ]);

        $storedToken = UserPushToken::query()->firstOrFail();

        $this->assertSame($plainToken, $storedToken->token);
        $this->assertNotSame($plainToken, $storedToken->getRawOriginal('token'));

        $this->deleteJson('/api/settings/devices/push-token', [
            'token' => $plainToken,
        ])->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('user_push_tokens', [
            'token_hash' => hash('sha256', $plainToken),
        ]);
    }

    public function test_firebase_service_sends_push_to_registered_fcm_token(): void
    {
        Cache::flush();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'firebase-access-token',
                'expires_in' => 3600,
            ]),
            'fcm.googleapis.com/*' => Http::response([
                'name' => 'projects/zulors/messages/test-message',
            ]),
        ]);

        $user = $this->createUser('fcm-send-user');
        $pushToken = $this->createPushToken($user, 'registered-fcm-token');

        $this->configureFirebaseCredentials();

        $sent = app(FirebaseCloudMessagingService::class)->sendToToken($pushToken, [
            'notification' => [
                'title' => 'New message',
                'body' => 'A new Zulors notification is ready.',
            ],
            'data' => [
                'url' => 'https://zulors.com/notifications',
                'type' => 'test.notification',
            ],
        ]);

        $this->assertTrue($sent);
        $this->assertNotNull($pushToken->fresh()->last_used_at);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://oauth2.googleapis.com/token'
                && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && filled($request['assertion']);
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://fcm.googleapis.com/v1/projects/zulors/messages:send'
                && $request->hasHeader('Authorization', 'Bearer firebase-access-token')
                && $request['message']['token'] === 'registered-fcm-token'
                && $request['message']['notification']['title'] === 'New message'
                && $request['message']['data']['url'] === 'https://zulors.com/notifications';
        });
    }

    public function test_firebase_service_revokes_invalid_fcm_tokens(): void
    {
        Cache::flush();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'firebase-access-token',
                'expires_in' => 3600,
            ]),
            'fcm.googleapis.com/*' => Http::response([
                'error' => [
                    'status' => 'NOT_FOUND',
                    'message' => 'Requested entity was not found.',
                ],
            ], 404),
        ]);

        $user = $this->createUser('fcm-invalid-user');
        $pushToken = $this->createPushToken($user, 'invalid-fcm-token');

        $this->configureFirebaseCredentials();

        $sent = app(FirebaseCloudMessagingService::class)->sendToToken($pushToken, [
            'notification' => [
                'title' => 'Test',
                'body' => 'Test notification',
            ],
        ]);

        $this->assertFalse($sent);
        $this->assertNotNull($pushToken->fresh()->revoked_at);
    }

    public function test_signed_push_reply_action_creates_a_chat_message(): void
    {
        [$sender, $recipient, $chat] = $this->createDirectChat();
        $token = app(NotificationActionTokenService::class)->make($recipient->id, $chat->chat_id, ['reply']);

        $this->postJson('/api/push-actions/reply', [
            'token' => $token,
            'content' => 'Reply from shade',
        ])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.message.content', 'Reply from shade');

        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'chat_uuid' => $chat->chat_id,
            'user_id' => $recipient->id,
            'content' => 'Reply from shade',
        ]);

        $this->assertSame(
            Message::query()->latest('id')->value('id'),
            $chat->participants()->where('user_id', $recipient->id)->value('last_read_message_id')
        );
    }

    public function test_signed_push_read_and_mute_actions_update_chat_state(): void
    {
        [$sender, $recipient, $chat] = $this->createDirectChat();
        $message = $chat->messages()->create([
            'chat_uuid' => $chat->chat_id,
            'user_id' => $sender->id,
            'participant_id' => $chat->participants()->where('user_id', $sender->id)->value('id'),
            'content' => 'Unread message',
            'text_language' => 'en',
        ]);

        $readToken = app(NotificationActionTokenService::class)->make($recipient->id, $chat->chat_id, ['read'], $message->id);

        $this->postJson('/api/push-actions/read', [
            'token' => $readToken,
        ])->assertOk()
            ->assertJsonPath('data.status_updated', true);

        $this->assertSame(
            $message->id,
            $chat->participants()->where('user_id', $recipient->id)->value('last_read_message_id')
        );

        $muteToken = app(NotificationActionTokenService::class)->make($recipient->id, $chat->chat_id, ['mute'], $message->id);

        $this->postJson('/api/push-actions/mute-chat', [
            'token' => $muteToken,
            'duration_minutes' => 60,
        ])->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertNotNull(
            $chat->participants()->where('user_id', $recipient->id)->value('notifications_muted_until')
        );
    }

    public function test_message_push_payload_honors_message_preview_privacy(): void
    {
        [$sender, $recipient, $chat] = $this->createDirectChat();
        $this->createPushSettings($recipient, [
            'direct_messages' => true,
            'show_message_preview' => false,
        ]);

        $message = $chat->messages()->create([
            'chat_uuid' => $chat->chat_id,
            'user_id' => $sender->id,
            'participant_id' => $chat->participants()->where('user_id', $sender->id)->value('id'),
            'content' => 'Private preview text',
            'text_language' => 'en',
        ])->load(['chat', 'user']);

        $payload = (new MessageReceivedNotification($message))->toPush($recipient);

        $this->assertSame('zulors_messages', $payload['channel_id']);
        $this->assertSame($chat->chat_id, $payload['data']['chat_id']);
        $this->assertNotSame('Private preview text', $payload['body']);
        $this->assertNotEmpty($payload['data']['action_token']);
    }

    private function configureFirebaseCredentials(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($key, $privateKey);

        $path = tempnam(sys_get_temp_dir(), 'zulors-firebase-test-');

        file_put_contents($path, json_encode([
            'type' => 'service_account',
            'project_id' => 'zulors',
            'private_key_id' => 'test-key',
            'private_key' => $privateKey,
            'client_email' => 'firebase-adminsdk-test@zulors.iam.gserviceaccount.com',
            'client_id' => '1234567890',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));

        config([
            'notifications.push.enabled' => true,
            'notifications.push.firebase.project_id' => 'zulors',
            'notifications.push.firebase.credentials' => $path,
            'notifications.push.firebase.timeout' => 3,
        ]);
    }

    private function createPushToken(User $user, string $plainToken): UserPushToken
    {
        return UserPushToken::query()->create([
            'user_id' => $user->id,
            'provider' => 'fcm',
            'platform' => 'android',
            'token' => $plainToken,
            'token_hash' => hash('sha256', $plainToken),
            'device_id' => 'test-device',
            'device_name' => 'Xiaomi test',
            'app_version' => '0.4.0-production',
        ]);
    }

    private function createPushSettings(User $user, array $attributes = []): UserNotificationSettings
    {
        return UserNotificationSettings::query()->create(array_merge([
            'user_id' => $user->id,
            'type' => 'push',
            'direct_messages' => true,
            'show_message_preview' => true,
            'reactions' => true,
            'comments' => true,
            'shared_posts' => true,
            'followers' => true,
            'follow_request' => true,
            'mentions' => true,
        ], $attributes));
    }

    private function createDirectChat(): array
    {
        $sender = $this->createUser('chat-sender-' . Str::random(6));
        $recipient = $this->createUser('chat-recipient-' . Str::random(6));
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
            'first_name' => 'Test',
            'last_name' => 'User',
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
