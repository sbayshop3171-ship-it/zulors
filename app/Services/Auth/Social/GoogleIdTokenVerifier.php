<?php

namespace App\Services\Auth\Social;

use Google\Client as GoogleClient;
use Throwable;
use Illuminate\Validation\ValidationException;

class GoogleIdTokenVerifier
{
    /**
     * @param  string|array<int, string>  $expectedAudience
     */
    public function verify(string $idToken, string|array $expectedAudience): array
    {
        $allowedAudiences = $this->normalizeAudiences($expectedAudience);

        if(empty($allowedAudiences)) {
            throw ValidationException::withMessages([
                'google' => __('This Google sign in is not configured for Zulors.'),
            ]);
        }

        $payload = null;

        foreach($allowedAudiences as $audience) {
            try {
                $client = new GoogleClient();
                $client->setClientId($audience);
                $verifiedPayload = $client->verifyIdToken($idToken);

                if(is_array($verifiedPayload)) {
                    $payload = $verifiedPayload;
                    break;
                }
            } catch (Throwable) {
                // Try the next configured audience. The token is accepted only
                // when Google's official client verifies its signature and claims.
            }
        }

        if(! is_array($payload)) {
            throw ValidationException::withMessages([
                'google' => __('We could not verify this Google sign in. Please try again.'),
            ]);
        }

        $audience = (string) data_get($payload, 'aud', '');

        if(! $this->audienceMatches($audience, $allowedAudiences)) {
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

    /**
     * @param  string|array<int, string>  $expectedAudience
     * @return array<int, string>
     */
    private function normalizeAudiences(string|array $expectedAudience): array
    {
        $audiences = is_array($expectedAudience) ? $expectedAudience : [$expectedAudience];

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $audience) => is_string($audience) ? trim($audience) : '',
            $audiences
        ))));
    }

    /**
     * @param  string|array<int, string>  $expectedAudience
     */
    private function audienceMatches(string $audience, string|array $expectedAudience): bool
    {
        $allowedAudiences = is_array($expectedAudience) ? $expectedAudience : [$expectedAudience];
        $allowedAudiences = array_values(array_filter(array_map(
            fn (string $clientId) => trim($clientId),
            $allowedAudiences
        )));

        foreach($allowedAudiences as $allowedAudience) {
            if(hash_equals($allowedAudience, $audience)) {
                return true;
            }
        }

        return false;
    }
}
