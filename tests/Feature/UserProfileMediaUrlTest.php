<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfileMediaUrlTest extends TestCase
{
    public function test_custom_profile_media_urls_do_not_depend_on_storage_exists_checks(): void
    {
        Storage::fake(static_storage_disk());

        $avatarPath = 'uploads/users/avatars/missing-avatar.webp';
        $coverPath = 'uploads/users/covers/missing-cover.webp';
        $user = new User([
            'avatar' => $avatarPath,
            'cover' => $coverPath,
        ]);

        $this->assertFalse(Storage::disk(static_storage_disk())->exists($avatarPath));
        $this->assertFalse(Storage::disk(static_storage_disk())->exists($coverPath));
        $this->assertSame(storage_url($avatarPath, static_storage_disk()), $user->avatar_url);
        $this->assertSame(storage_url($coverPath, static_storage_disk()), $user->cover_url);
    }

    public function test_empty_and_default_profile_media_use_default_assets(): void
    {
        $emptyUser = new User([
            'avatar' => null,
            'cover' => null,
        ]);

        $this->assertSame(asset(config('user.avatar')), $emptyUser->avatar_url);
        $this->assertSame(asset(config('user.cover')), $emptyUser->cover_url);

        $defaultUser = new User([
            'avatar' => config('user.avatar'),
            'cover' => config('user.cover'),
        ]);

        $this->assertSame(asset(config('user.avatar')), $defaultUser->avatar_url);
        $this->assertSame(asset(config('user.cover')), $defaultUser->cover_url);
    }
}
