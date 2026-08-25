<?php

namespace Tests\Feature;

use App\Database\Configs\Table;
use App\Enums\NotificationType;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Livewire\User\Onboarding\Username;
use App\Models\Onboard;
use App\Models\User;
use App\Models\UserNotificationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class OnboardingCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_username_step_marks_signup_complete_and_clears_onboarding_state(): void
    {
        $user = $this->createUser('pending_signup', 'pending-signup@example.com');

        Onboard::query()->create([
            'user_id' => $user->id,
            'step' => 'username',
        ]);

        $this->actingAs($user);

        $usernameStepIndex = array_search('username', array_keys(config('user.onboarding_steps')), true);

        Livewire::test(Username::class, [
            'stepIndex' => $usernameStepIndex,
            'stepData' => config('user.onboarding_steps.username'),
        ])
            ->set('username', 'completed_signup')
            ->set('password', 'securepass123')
            ->call('submitForm')
            ->assertRedirect(route('user.desktop.index'));

        $user->refresh();

        $this->assertSame(UserStatus::ACTIVE, $user->status);
        $this->assertTrue(Hash::check('securepass123', $user->password));
        $this->assertDatabaseMissing(Table::ONBOARDINGS, [
            'user_id' => $user->id,
        ]);
    }

    private function createUser(string $username, string $email): User
    {
        $user = User::query()->create([
            'first_name' => 'Pending',
            'last_name' => 'Signup',
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
            'status' => UserStatus::ONBOARDING,
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
