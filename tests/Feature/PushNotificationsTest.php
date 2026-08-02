<?php

namespace Tests\Feature;

use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Models\User;
use App\Models\UserPushToken;
use App\Services\Notifications\FirebaseCloudMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushNotificationsTest extends TestCase
{
    use RefreshDatabase;

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
