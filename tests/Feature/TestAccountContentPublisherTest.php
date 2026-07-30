<?php

namespace Tests\Feature;

use App\Enums\Post\PostStatus;
use App\Enums\Post\PostType;
use App\Enums\User\UserStatus;
use App\Models\Post;
use App\Models\TestContentPublication;
use App\Models\User;
use App\Services\TestContent\TestAccountContentPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TestAccountContentPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_publishes_one_original_article_for_each_active_test_account_only(): void
    {
        $firstTestUser = $this->createUser('first-test-user', 'first@gmail.test');
        $secondTestUser = $this->createUser('second-test-user', 'second@gmail.test');
        $realUser = $this->createUser('real-user', 'real@example.com');
        $inactiveTestUser = $this->createUser('inactive-test-user', 'inactive@gmail.test', UserStatus::SUSPENDED);

        $summary = app(TestAccountContentPublisher::class)->publish('pilot-test-content');

        $this->assertSame([
            'published' => 2,
            'skipped' => 0,
            'failed' => 0,
        ], $summary);

        $this->assertDatabaseCount('posts', 2);
        $this->assertDatabaseHas('posts', [
            'user_id' => $firstTestUser->id,
            'status' => PostStatus::ACTIVE->value,
            'type' => PostType::TEXT->value,
            'is_ai_generated' => true,
        ]);
        $this->assertDatabaseHas('posts', [
            'user_id' => $secondTestUser->id,
            'status' => PostStatus::ACTIVE->value,
            'type' => PostType::TEXT->value,
            'is_ai_generated' => true,
        ]);
        $this->assertDatabaseMissing('posts', ['user_id' => $realUser->id]);
        $this->assertDatabaseMissing('posts', ['user_id' => $inactiveTestUser->id]);
        $this->assertDatabaseHas('users', ['id' => $firstTestUser->id, 'publications_count' => 1]);
        $this->assertDatabaseHas('users', ['id' => $secondTestUser->id, 'publications_count' => 1]);
        $this->assertDatabaseCount('test_content_publications', 2);
    }

    public function test_same_campaign_can_be_rerun_without_duplicate_posts(): void
    {
        $testUser = $this->createUser('repeat-test-user', 'repeat@gmail.test');
        $publisher = app(TestAccountContentPublisher::class);

        $publisher->publish('repeatable-campaign');
        $summary = $publisher->publish('repeatable-campaign');

        $this->assertSame([
            'published' => 0,
            'skipped' => 1,
            'failed' => 0,
        ], $summary);
        $this->assertSame(1, Post::query()->where('user_id', $testUser->id)->count());
        $this->assertSame(1, TestContentPublication::query()
            ->where('campaign_key', 'repeatable-campaign')
            ->where('user_id', $testUser->id)
            ->count());
    }

    public function test_command_requires_explicit_confirmation_and_supports_dry_run(): void
    {
        $this->createUser('command-test-user', 'command@gmail.test');

        $this->artisan('test-content:publish --campaign=command-test --dry-run')
            ->expectsOutput('Eligible active .test accounts: 1')
            ->expectsOutput('Dry run complete. No posts were created.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('posts', 0);

        $this->artisan('test-content:publish --campaign=command-test')
            ->expectsOutput('Nothing was published. Re-run with --confirm=ALL_TEST_ACCOUNTS after checking the target count.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('posts', 0);

        $this->artisan('test-content:publish --campaign=command-test --confirm=ALL_TEST_ACCOUNTS')
            ->assertExitCode(0);

        $this->assertDatabaseCount('posts', 1);
        $this->assertDatabaseHas('test_content_publications', [
            'campaign_key' => 'command-test',
            'status' => 'published',
        ]);
    }

    private function createUser(string $username, string $email, UserStatus $status = UserStatus::ACTIVE): User
    {
        return User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'Author',
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
            'role' => 'user',
            'theme' => 'light',
            'publications_count' => 0,
            'followers_count' => 0,
            'following_count' => 0,
            'status' => $status,
            'type' => 'author',
        ]);
    }
}
