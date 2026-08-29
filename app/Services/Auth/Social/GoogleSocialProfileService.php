<?php

namespace App\Services\Auth\Social;

use Throwable;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Support\Arrayable;

class GoogleSocialProfileService
{
    private const MAX_AVATAR_BYTES = 2097152;

    public function syncUser(User $user, $socialiteUser, string $driverName = 'google'): void
    {
        $rawUser = $this->getRawUserData($socialiteUser);
        $profileNames = $this->resolveProfileNames($user, $socialiteUser, $rawUser);
        $username = $this->resolveAvailableUsername($socialiteUser, $user->id, $driverName);
        $userPicture = $socialiteUser->getAvatar() ?: data_get($rawUser, 'picture');
        $userAvatarFilePath = $this->resolveAvatarFilePath($user, $userPicture);

        $user->update([
            'username' => $username,
            'caption' => "@{$username}",
            'first_name' => $profileNames['first_name'],
            'last_name' => $profileNames['last_name'],
            'email' => $socialiteUser->getEmail() ?: data_get($rawUser, 'email', $user->email),
            'avatar' => $userAvatarFilePath,
            'email_verified_at' => $this->resolveEmailVerifiedAt($user, $rawUser),
        ]);
    }

    public function shouldRefreshIncompleteProfile(User $user, string $driverName = 'google'): bool
    {
        return Str::startsWith((string) $user->username, "{$driverName}_") && blank($user->first_name);
    }

    private function resolveAvailableUsername($socialiteUser, int $ignoreUserId, string $driverName): string
    {
        $rawUser = $this->getRawUserData($socialiteUser);
        $nickname = trim((string) ($socialiteUser->getNickname() ?: data_get($rawUser, 'nickname', '')));
        $email = (string) ($socialiteUser->getEmail() ?: data_get($rawUser, 'email', ''));
        $emailLocalPart = Str::before($email, '@');
        $displayName = trim((string) ($socialiteUser->getName() ?: data_get($rawUser, 'name', '')));

        $baseUsername = $nickname !== ''
            ? $nickname
            : ($emailLocalPart !== '' ? $emailLocalPart : $displayName);

        $baseUsername = preg_replace('/[^A-Za-z0-9_]+/', '_', Str::lower($baseUsername));
        $baseUsername = trim((string) $baseUsername, '_');

        if($baseUsername === '') {
            $baseUsername = "{$driverName}_user";
        }

        $candidate = Str::limit($baseUsername, 30, '');
        $suffix = 1;

        while(
            User::query()
                ->where('username', $candidate)
                ->where('id', '!=', $ignoreUserId)
                ->exists()
        ) {
            $suffixText = '_' . $suffix;
            $candidate = Str::limit($baseUsername, max(5, 30 - strlen($suffixText)), '') . $suffixText;
            $suffix += 1;
        }

        return $candidate;
    }

    private function getRawUserData($socialiteUser): array
    {
        $rawUser = $socialiteUser->user ?? [];

        if($rawUser instanceof Arrayable) {
            return $rawUser->toArray();
        }

        if(is_object($rawUser)) {
            return get_object_vars($rawUser);
        }

        return is_array($rawUser) ? $rawUser : [];
    }

    private function resolveAvatarFilePath(User $user, mixed $avatarUrl): string
    {
        if($user->hasCustomAvatar()) {
            return $user->avatar;
        }

        $storedAvatarPath = $this->storeGoogleAvatar($avatarUrl);

        return $storedAvatarPath ?: ($user->avatar ?: config('user.avatar'));
    }

    private function storeGoogleAvatar(mixed $avatarUrl): ?string
    {
        $avatarUrl = trim((string) $avatarUrl);

        if(! $this->isTrustedGoogleAvatarUrl($avatarUrl)) {
            return null;
        }

        try {
            $response = Http::timeout(5)
                ->accept('image/avif,image/webp,image/png,image/jpeg,image/*')
                ->get($avatarUrl);

            if(! $response->ok()) {
                return null;
            }

            $contentType = mb_strtolower(trim((string) Str::before($response->header('Content-Type', ''), ';')));
            $extension = $this->avatarExtensionForContentType($contentType);
            $body = $response->body();
            $bodySize = strlen($body);

            if($extension === null || $bodySize === 0 || $bodySize > self::MAX_AVATAR_BYTES) {
                return null;
            }

            $filepath = 'uploads/users/avatars/' . Str::random(40) . ".{$extension}";
            $fileUploaded = Storage::disk(static_storage_disk())->put($filepath, $body, [
                'visibility' => 'public',
                'ContentType' => $contentType,
            ]);

            return $fileUploaded ? $filepath : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function isTrustedGoogleAvatarUrl(string $avatarUrl): bool
    {
        if($avatarUrl === '') {
            return false;
        }

        $scheme = mb_strtolower((string) parse_url($avatarUrl, PHP_URL_SCHEME));
        $host = mb_strtolower((string) parse_url($avatarUrl, PHP_URL_HOST));

        return $scheme === 'https'
            && ($host === 'googleusercontent.com' || str_ends_with($host, '.googleusercontent.com'));
    }

    private function avatarExtensionForContentType(string $contentType): ?string
    {
        return match ($contentType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => null,
        };
    }

    private function resolveProfileNames(User $user, $socialiteUser, array $rawUser): array
    {
        $fullName = trim((string) ($socialiteUser->getName() ?: data_get($rawUser, 'name', '')));
        $firstName = trim((string) data_get($rawUser, 'given_name', ''));
        $lastName = trim((string) data_get($rawUser, 'family_name', ''));

        if($firstName === '' && $fullName !== '') {
            $nameParts = preg_split('/\s+/', $fullName, 2) ?: [];
            $firstName = trim((string) ($nameParts[0] ?? ''));

            if($lastName === '') {
                $lastName = trim((string) ($nameParts[1] ?? ''));
            }
        }

        if($firstName === '') {
            $firstName = trim((string) $user->first_name);
        }

        if($firstName === '') {
            $firstName = Str::headline(Str::before($user->email ?: $user->username, '@'));
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName !== '' ? $lastName : (string) $user->last_name,
        ];
    }

    private function resolveEmailVerifiedAt(User $user, array $rawUser)
    {
        if($user->email_verified_at) {
            return $user->email_verified_at;
        }

        return filter_var(data_get($rawUser, 'email_verified', false), FILTER_VALIDATE_BOOLEAN) ? now() : null;
    }
}
