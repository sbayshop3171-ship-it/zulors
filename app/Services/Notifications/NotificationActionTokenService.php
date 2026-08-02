<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NotificationActionTokenService
{
    public function make(int $userId, string $chatUuid, array $actions, ?int $messageId = null, int $ttlMinutes = 20): string
    {
        return Crypt::encryptString(json_encode([
            'user_id' => $userId,
            'chat_uuid' => $chatUuid,
            'message_id' => $messageId,
            'actions' => array_values($actions),
            'expires_at' => now()->addMinutes($ttlMinutes)->timestamp,
            'nonce' => (string) Str::uuid(),
        ]));
    }

    public function verify(string $token, string $action): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        }
        catch(\Throwable $exception) {
            $this->throwInvalidToken();
        }

        if(! is_array($payload)
            || blank($payload['user_id'] ?? null)
            || blank($payload['chat_uuid'] ?? null)
            || empty($payload['expires_at'])
            || now()->timestamp > (int) $payload['expires_at']
            || ! in_array($action, $payload['actions'] ?? [], true)) {
            $this->throwInvalidToken();
        }

        return $payload;
    }

    private function throwInvalidToken(): void
    {
        throw ValidationException::withMessages([
            'token' => [__('Invalid or expired notification action token.')],
        ]);
    }
}
