<?php

namespace Tests\Feature;

use App\Database\Configs\Table;
use App\Enums\NotificationType;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Models\Onboard;
use App\Models\User;
use App\Models\UserNotificationSettings;
use App\Services\Auth\Social\GoogleIdTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
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
        config()->set('services.google.native_client_ids', [
            'test-native-google-client',
        ]);
    }

    public function test_google_callback_links_existing_email_and_reuses_same_account_without_server_error(): void
    {
        $user = $this->createUser('google_existing_user', 'google-existing@example.com');

        $provider = Mockery::mock();
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
        $provider->shouldReceive('user')->once()->andReturn($this->makeGoogleUserWithoutFamilyName());

        Socialite::shouldReceive('buildProvider')->once()->andReturn($provider);

        $response = $this->get(route('social-login.google.callback'));

        $response->assertRedirect(route('user.onboarding.index', 'profile'));

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

    public function test_google_callback_redirects_to_login_when_google_rejects_callback(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andThrow(new \RuntimeException('Invalid OAuth callback.'));

        Socialite::shouldReceive('buildProvider')->once()->andReturn($provider);

        $response = $this->get(route('social-login.google.callback'));

        $response->assertRedirect(route('user.auth.index'));
        $response->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_native_google_sign_in_issues_handoff_and_consumes_it_once(): void
    {
        $this->mockGoogleToken([
                'iss' => 'https://accounts.google.com',
                'aud' => 'test-google-client',
                'sub' => 'native-google-user-123',
                'email' => 'native-google@example.com',
                'email_verified' => 'true',
                'exp' => (string) now()->addMinutes(10)->timestamp,
                'name' => 'Native Google',
                'given_name' => 'Native',
                'picture' => null,
        ]);

        $response = $this->postJson('/api/auth/google', [
            'id_token' => 'valid-native-google-id-token',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['status', 'redirect_url', 'next_url', 'is_existing_user'])
            ->assertJson([
                'status' => 'onboarding',
                'is_existing_user' => false,
                'next_url' => route('user.onboarding.index', 'profile'),
            ]);

        $user = User::query()->where('email', 'native-google@example.com')->firstOrFail();

        $this->assertSame('native_google', $user->username);
        $this->assertSame('Native', $user->first_name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas(Table::SOCIAL_ACCOUNTS, [
            'user_id' => $user->id,
            'provider_name' => 'google',
            'provider_id' => 'native-google-user-123',
        ]);

        $handoffPath = parse_url($response->json('redirect_url'), PHP_URL_PATH);

        $consumeResponse = $this->get($handoffPath);

        $consumeResponse->assertRedirect(route('user.onboarding.index', 'profile'));
        $this->assertAuthenticatedAs($user);

        Auth::logout();

        $secondConsumeResponse = $this->get($handoffPath);

        $secondConsumeResponse->assertRedirect(route('user.auth.index'));
        $secondConsumeResponse->assertSessionHas('flashMessage');
    }

    public function test_native_google_sign_in_rejects_wrong_audience(): void
    {
        $this->mockGoogleTokenFailure('This Google sign in is not configured for Zulors.');

        $response = $this->postJson('/api/mobile-auth/google', [
            'id_token' => 'valid-native-google-id-token',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseMissing(Table::SOCIAL_ACCOUNTS, [
            'provider_name' => 'google',
            'provider_id' => 'native-google-user-123',
        ]);
    }

    public function test_native_google_sign_in_accepts_configured_native_audience(): void
    {
        $this->mockGoogleToken([
                'iss' => 'https://accounts.google.com',
                'aud' => 'test-native-google-client',
                'sub' => 'native-google-client-user-123',
                'email' => 'native-google-client@example.com',
                'email_verified' => 'true',
                'exp' => (string) now()->addMinutes(10)->timestamp,
                'name' => 'Native Firebase Client',
                'given_name' => 'Native',
                'picture' => null,
        ]);

        $response = $this->postJson('/api/mobile-auth/google', [
            'id_token' => 'valid-native-firebase-google-id-token',
        ]);

        $response->assertOk()
            ->assertJson([
                'is_existing_user' => false,
                'next_url' => route('user.onboarding.index', 'profile'),
            ]);

        $this->assertDatabaseHas(Table::SOCIAL_ACCOUNTS, [
            'provider_name' => 'google',
            'provider_id' => 'native-google-client-user-123',
        ]);
    }

    public function test_native_google_sign_in_logs_in_existing_user_without_leaving_native_flow(): void
    {
        $user = $this->createUser('existing_native_google', 'existing-native-google@example.com');

        $this->mockGoogleToken([
                'iss' => 'https://accounts.google.com',
                'aud' => 'test-google-client',
                'sub' => 'native-google-existing-123',
                'email' => 'existing-native-google@example.com',
                'email_verified' => 'true',
                'exp' => (string) now()->addMinutes(10)->timestamp,
                'name' => 'Existing Native Google',
                'given_name' => 'Existing',
                'family_name' => 'Native',
                'picture' => null,
        ]);

        $response = $this->postJson('/api/mobile-auth/google', [
            'id_token' => 'valid-existing-native-google-id-token',
        ]);

        $response->assertOk()->assertJson([
            'status' => 'authenticated',
            'is_existing_user' => true,
            'next_url' => route('user.desktop.index'),
        ]);

        $this->assertDatabaseHas(Table::SOCIAL_ACCOUNTS, [
            'user_id' => $user->id,
            'provider_name' => 'google',
            'provider_id' => 'native-google-existing-123',
        ]);

        $handoffPath = parse_url($response->json('redirect_url'), PHP_URL_PATH);

        $consumeResponse = $this->get($handoffPath);

        $consumeResponse->assertRedirect(route('user.desktop.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_native_google_sign_in_sends_active_user_home_even_with_stale_onboarding_row(): void
    {
        $user = $this->createUser('stale_native_google', 'stale-native-google@example.com');

        Onboard::query()->create([
            'user_id' => $user->id,
            'step' => 'profile',
        ]);

        $this->mockGoogleToken([
                'iss' => 'https://accounts.google.com',
                'aud' => 'test-google-client',
                'sub' => 'native-google-stale-123',
                'email' => 'stale-native-google@example.com',
                'email_verified' => 'true',
                'exp' => (string) now()->addMinutes(10)->timestamp,
                'name' => 'Stale Native Google',
                'given_name' => 'Stale',
                'family_name' => 'Native',
                'picture' => null,
        ]);

        $response = $this->postJson('/api/mobile-auth/google', [
            'id_token' => 'valid-stale-native-google-id-token',
        ]);

        $response->assertOk()->assertJson([
            'is_existing_user' => true,
            'next_url' => route('user.desktop.index'),
        ]);

        $handoffPath = parse_url($response->json('redirect_url'), PHP_URL_PATH);

        $consumeResponse = $this->get($handoffPath);

        $consumeResponse->assertRedirect(route('user.desktop.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_native_google_sign_in_keeps_existing_incomplete_user_in_onboarding_flow(): void
    {
        $user = $this->createUser(
            'pending_native_google',
            'pending-native-google@example.com',
            UserStatus::ONBOARDING
        );

        Onboard::query()->create([
            'user_id' => $user->id,
            'step' => 'profile',
        ]);

        $this->mockGoogleToken([
                'iss' => 'https://accounts.google.com',
                'aud' => 'test-google-client',
                'sub' => 'native-google-pending-123',
                'email' => 'pending-native-google@example.com',
                'email_verified' => 'true',
                'exp' => (string) now()->addMinutes(10)->timestamp,
                'name' => 'Pending Native Google',
                'given_name' => 'Pending',
                'family_name' => 'Native',
                'picture' => null,
        ]);

        $response = $this->postJson('/api/mobile-auth/google', [
            'id_token' => 'valid-pending-native-google-id-token',
        ]);

        $response->assertOk()->assertJson([
            'is_existing_user' => true,
            'next_url' => route('user.onboarding.index', 'profile'),
        ]);

        $this->assertDatabaseHas(Table::SOCIAL_ACCOUNTS, [
            'user_id' => $user->id,
            'provider_name' => 'google',
            'provider_id' => 'native-google-pending-123',
        ]);

        $handoffPath = parse_url($response->json('redirect_url'), PHP_URL_PATH);

        $consumeResponse = $this->get($handoffPath);

        $consumeResponse->assertRedirect(route('user.onboarding.index', 'profile'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_native_google_sign_in_rejects_expired_token(): void
    {
        $this->mockGoogleTokenFailure('This Google sign in has expired. Please try again.');

        $response = $this->postJson('/api/mobile-auth/google', [
            'id_token' => 'expired-native-google-id-token',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['google']);
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

    private function mockGoogleToken(array $payload): void
    {
        $verifier = Mockery::mock(GoogleIdTokenVerifier::class);
        $verifier->shouldReceive('verify')->once()->andReturn($payload);

        $this->app->instance(GoogleIdTokenVerifier::class, $verifier);
    }

    private function mockGoogleTokenFailure(string $message): void
    {
        $verifier = Mockery::mock(GoogleIdTokenVerifier::class);
        $verifier->shouldReceive('verify')->once()->andThrow(
            ValidationException::withMessages(['google' => $message])
        );

        $this->app->instance(GoogleIdTokenVerifier::class, $verifier);
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

    private function createUser(string $username, string $email, UserStatus $status = UserStatus::ACTIVE): User
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
            'status' => $status,
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
