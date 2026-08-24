<?php

namespace App\Services\Auth\Social;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GoogleIdTokenVerifier
{
    private const TOKENINFO_URL = 'https://oauth2.googleapis.com/tokeninfo';

    public function verify(string $idToken, string $expectedAudience): array
    {
        $response = Http::timeout(8)
            ->acceptJson()
            ->get(self::TOKENINFO_URL, [
                'id_token' => $idToken,
            ]);

        if(! $response->ok()) {
            throw ValidationException::withMessages([
                'google' => __('We could not verify this Google sign in. Please try again.'),
            ]);
        }

        $payload = $response->json();

        if(! is_array($payload)) {
            throw ValidationException::withMessages([
                'google' => __('We could not verify this Google sign in. Please try again.'),
            ]);
        }

        if(! hash_equals($expectedAudience, (string) data_get($payload, 'aud', ''))) {
            throw ValidationException::withMessages([
                'google' => __('This Google sign in is not configured for Zulors.'),
            ]);
        }

        if(! in_array((string) data_get($payload, 'iss', ''), ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw ValidationException::withMessages([
                'google' => __('This Google sign in is not trusted.'),
            ]);
        }

        if(blank(data_get($payload, 'sub')) || blank(data_get($payload, 'email'))) {
            throw ValidationException::withMessages([
                'google' => __('Google did not return a usable account email.'),
            ]);
        }

        if(! filter_var(data_get($payload, 'email_verified', false), FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages([
                'google' => __('Please use a verified Google account email.'),
            ]);
        }

        $expiresAt = (int) data_get($payload, 'exp', 0);

        if($expiresAt > 0 && $expiresAt <= now()->timestamp) {
            throw ValidationException::withMessages([
                'google' => __('This Google sign in has expired. Please try again.'),
            ]);
        }

        return $payload;
    }
}
