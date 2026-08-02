<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\UserPushToken;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class FirebaseCloudMessagingService
{
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function sendToUser(User $user, array $message): void
    {
        if(! config('notifications.push.enabled')) {
            return;
        }

        $user->pushTokens()
            ->active()
            ->where('provider', 'fcm')
            ->each(function (UserPushToken $pushToken) use ($message) {
                $this->sendToToken($pushToken, $message);
            });
    }

    public function sendToToken(UserPushToken $pushToken, array $message): bool
    {
        try {
            $projectId = $this->projectId();

            $response = Http::withToken($this->accessToken())
                ->timeout($this->timeout())
                ->acceptJson()
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => array_merge($message, [
                        'token' => $pushToken->token,
                    ]),
                ]);

            if($response->successful()) {
                $pushToken->forceFill([
                    'last_used_at' => now(),
                ])->save();

                return true;
            }

            if($this->isInvalidTokenResponse($response->json())) {
                $pushToken->forceFill([
                    'revoked_at' => now(),
                ])->save();
            }

            Log::warning('Firebase push notification failed.', [
                'status' => $response->status(),
                'token_id' => $pushToken->id,
                'firebase_status' => $response->json('error.status'),
            ]);
        }
        catch(\Throwable $exception) {
            Log::error('Firebase push notification error: ' . $exception->getMessage(), [
                'token_id' => $pushToken->id,
            ]);
        }

        return false;
    }

    public function accessToken(): string
    {
        $credentials = $this->credentials();

        return Cache::remember($this->cacheKey($credentials), now()->addMinutes(55), function () use ($credentials) {
            $now = time();

            $assertion = JWT::encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URI,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key'], 'RS256');

            $response = Http::asForm()
                ->timeout($this->timeout())
                ->post(self::TOKEN_URI, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);

            if(! $response->successful() || blank($response->json('access_token'))) {
                throw new RuntimeException('Unable to authenticate with Firebase Cloud Messaging.');
            }

            return $response->json('access_token');
        });
    }

    private function projectId(): string
    {
        $projectId = (string) config('notifications.push.firebase.project_id');

        if(blank($projectId)) {
            throw new RuntimeException('Firebase project id is not configured.');
        }

        return $projectId;
    }

    private function credentials(): array
    {
        $path = (string) config('notifications.push.firebase.credentials');
        $path = Str::startsWith($path, '/') ? $path : base_path($path);

        if(! is_file($path)) {
            throw new RuntimeException('Firebase service account file is not available.');
        }

        $credentials = json_decode((string) file_get_contents($path), true);

        if(! is_array($credentials) || blank($credentials['client_email'] ?? null) || blank($credentials['private_key'] ?? null)) {
            throw new RuntimeException('Firebase service account file is invalid.');
        }

        return $credentials;
    }

    private function timeout(): int
    {
        return max(3, (int) config('notifications.push.firebase.timeout', 10));
    }

    private function cacheKey(array $credentials): string
    {
        return 'firebase:fcm:access-token:' . sha1(($credentials['project_id'] ?? '') . '|' . $credentials['client_email']);
    }

    private function isInvalidTokenResponse(?array $payload): bool
    {
        $status = data_get($payload, 'error.status');
        $message = data_get($payload, 'error.message', '');

        return in_array($status, ['INVALID_ARGUMENT', 'NOT_FOUND', 'UNREGISTERED'], true)
            || Str::contains($message, ['registration token is not a valid', 'Requested entity was not found']);
    }
}
