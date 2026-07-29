<?php

namespace Database\Seeders;

use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use Database\Seeders\LocaleSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        (new CategorySeeder())->run();
        (new CurrencySeeder())->run();
        (new LocaleSeeder())->run();

        $this->seedLocalLoginUsers();
    }

    private function seedLocalLoginUsers(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $this->upsertLocalUser([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'role' => UserRole::ROOT->value,
            'caption' => 'Official admin account',
            'bio' => 'Official administrator profile for platform updates and support notices.',
        ]);

        $this->upsertLocalUser([
            'first_name' => 'Demo',
            'last_name' => 'User',
            'username' => 'user',
            'email' => 'user@example.com',
            'role' => UserRole::USER->value,
        ]);

        $this->configureLocalMail();
        $this->configureLocalFFMpeg();
    }

    private function upsertLocalUser(array $attributes): void
    {
        $user = User::updateOrCreate([
            'email' => $attributes['email'],
        ], array_merge([
            'password' => 'password',
            'status' => UserStatus::ACTIVE->value,
            'type' => UserType::AUTHOR->value,
            'category' => config('user.category'),
            'bio' => '',
            'gender' => config('user.gender', 'male'),
            'language' => 'en',
            'avatar' => null,
            'cover' => config('user.cover'),
            'verified' => true,
            'verified_at' => now(),
            'tips' => [],
            'theme' => 'light',
            'last_active' => now()->toDateTimeString(),
            'ip_address' => '127.0.0.1',
            'email_verified_at' => now(),
        ], $attributes));

        $user->wallet()->updateOrCreate([], [
            'wallet_number' => 'ZLR-LOCAL-' . strtoupper($user->username),
            'balance' => 0,
            'currency' => 'USD',
        ]);

        $user->privacySettings()->firstOrCreate([]);
        $user->permitSettings()->firstOrCreate([]);

        $user->emailNotificationSettings()->firstOrCreate([
            'type' => 'email',
        ]);

        $user->pushNotificationSettings()->firstOrCreate([
            'type' => 'push',
        ]);

        $user->securitySettings()->updateOrCreate([], [
            '2fa' => false,
            '2fa_type' => 'email',
            'login_notification' => false,
            'login_notification_type' => 'email',
        ]);

        $user->businessAccount()->updateOrCreate([], [
            'name' => trim("{$user->first_name} {$user->last_name}"),
            'billing_address' => [],
        ]);
    }

    private function configureLocalMail(): void
    {
        DB::table('settings')->updateOrInsert([
            'group' => 'mail',
            'name' => 'transport',
        ], [
            'payload' => json_encode('log'),
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function configureLocalFFMpeg(): void
    {
        $temporaryDirectory = storage_path('app/tmp/ffmpeg');

        File::ensureDirectoryExists($temporaryDirectory);

        $settings = [
            'ffmpeg_path' => storage_path('app/bin/ffmpeg-darwin-x64/ffmpeg'),
            'ffprobe_path' => storage_path('app/bin/ffmpeg-darwin-x64/ffprobe'),
            'temporary_directory' => $temporaryDirectory,
        ];

        foreach ($settings as $name => $payload) {
            DB::table('settings')->updateOrInsert([
                'group' => 'ffmpeg',
                'name' => $name,
            ], [
                'payload' => json_encode($payload),
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }
}
