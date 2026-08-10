<?php

namespace Tests\Feature;

use App\Database\Configs\Table;
use App\Enums\NotificationType;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Models\User;
use App\Models\UserNotificationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleSocialLoginFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('social-login.providers.google.enabled', true);
        config()->set('social-login.providers.google.credentials', [
            'client_id' => 'test-google-client',
            'client_secret' => 'test-google-secret',
            'redirect' => url('social-login/callback/google'),
        ]);
    }

    public function test_google_callback_links_existing_email_and_reuses_same_account_without_server_error(): void
    {
        $user = $this->createUser('google_existing_user', 'google-existing@example.com');

        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->twice()->andReturnSelf();
        $provider->shouldReceive('user')->twice()->andReturn($this->makeGoogleUser());

        Socialite::shouldReceive('buildProvider')->twice()->andReturn($provider);

        $firstResponse = $this->get(route('social-login.google.callback'));

        $firstResponse->assertRedirect(route('user.desktop.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas(Table::SOCIAL_ACCOUNTS, [
            'user_id' => $user->id,
            'provider_name' => 'google',
            'provider_id' => 'google-user-123',
        ]);

        $secondResponse = $this->get(route('social-login.google.callback'));

        $secondResponse->assertRedirect(route('user.desktop.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, DB::table(Table::SOCIAL_ACCOUNTS)->count());
    }

    public function test_google_callback_creates_user_when_family_name_is_missing(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($this->makeGoogleUserWithoutFamilyName());

        Socialite::shouldReceive('buildProvider')->once()->andReturn($provider);

        $response = $this->get(route('social-login.google.callback'));

        $response->assertRedirect(route('user.desktop.index'));

        $user = User::query()->where('email', 'sierracode0@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('sierracode0', $user->username);
        $this->assertSame('Sierracode', $user->first_name);
        $this->assertSame('', $user->last_name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas(Table::SOCIAL_ACCOUNTS, [
            'user_id' => $user->id,
            'provider_name' => 'google',
            'provider_id' => 'google-user-no-family-name',
        ]);
    }

    private function makeGoogleUser(): SocialiteUser
    {
        return (new SocialiteUser())
            ->map([
                'id' => 'google-user-123',
                'nickname' => 'google_existing_user',
                'name' => 'Google Existing User',
                'email' => 'google-existing@example.com',
                'avatar' => '',
            ])
            ->setRaw([
                'email' => 'google-existing@example.com',
                'given_name' => 'Google',
                'family_name' => 'Existing',
                'picture' => null,
            ]);
    }

    private function makeGoogleUserWithoutFamilyName(): SocialiteUser
    {
        return (new SocialiteUser())
            ->map([
                'id' => 'google-user-no-family-name',
                'nickname' => null,
                'name' => 'Sierracode',
                'email' => 'sierracode0@example.com',
                'avatar' => null,
            ])
            ->setRaw([
                'email' => 'sierracode0@example.com',
                'email_verified' => true,
                'given_name' => 'Sierracode',
                'name' => 'Sierracode',
                'picture' => null,
            ]);
    }

    private function createUser(string $username, string $email): User
    {
        $user = User::query()->create([
            'first_name' => 'Google',
            'last_name' => 'Existing',
            'username' => $username,
            'caption' => '@' . $username,
            'email' => $email,
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

        UserNotificationSettings::query()->create([
            'user_id' => $user->id,
            'type' => NotificationType::EMAIL,
        ]);

        UserNotificationSettings::query()->create([
            'user_id' => $user->id,
            'type' => NotificationType::PUSH,
        ]);

        return $user;
    }
}
