<?php

namespace Tests\Feature;

use App\Enums\User\FollowStatus;
use App\Enums\User\UserStatus;
use App\Models\Follow;
use App\Models\User;
use App\Services\TestContent\BulkTestAccountFollowPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BulkTestAccountFollowPublisherTest extends TestCase
{
	use RefreshDatabase;

	public function test_it_assigns_varied_follower_targets_using_only_test_accounts(): void
	{
		$testUsers = [];

		foreach (range(1, 6) as $number) {
			$testUsers[] = $this->createUser("follow-test-{$number}", "follow-test-{$number}@gmail.test");
		}
		$realUser = $this->createUser('real-user', 'real-user@example.com');
		$publisher = app(BulkTestAccountFollowPublisher::class);
		$preview = $publisher->preview('follow-quota-test', 0, 6, [2, 4]);
		$summary = $publisher->publish('follow-quota-test', $preview['users'], $preview['targets'], $preview['quotas']);
		$publisher->synchronizeCounts($preview['users']);

		$this->assertSame(6, $summary['targets']);
		$this->assertSame(array_sum($preview['quotas']), $summary['added']);
		$this->assertSame(0, $summary['failed']);
		$this->assertSame($summary['added'], Follow::query()->count());
		$this->assertSame(0, Follow::query()->where('follower_id', $realUser->id)->orWhere('following_id', $realUser->id)->count());

		foreach ($testUsers as $user) {
			$this->assertSame(
				Follow::query()->where('following_id', $user->id)->where('status', FollowStatus::FOLLOWING)->count(),
				(int) $user->fresh()->followers_count,
			);
		}
	}

	public function test_it_can_be_rerun_without_duplicate_follows_or_count_inflation(): void
	{
		foreach (range(1, 5) as $number) {
			$this->createUser("follow-repeat-{$number}", "follow-repeat-{$number}@gmail.test");
		}
		$publisher = app(BulkTestAccountFollowPublisher::class);
		$preview = $publisher->preview('follow-repeat-test', 0, 5, [2, 3]);
		$publisher->publish('follow-repeat-test', $preview['users'], $preview['targets'], $preview['quotas']);
		$publisher->synchronizeCounts($preview['users']);
		$initialCount = Follow::query()->count();
		$summary = $publisher->publish('follow-repeat-test', $preview['users'], $preview['targets'], $preview['quotas']);
		$publisher->synchronizeCounts($preview['users']);

		$this->assertSame(0, $summary['added']);
		$this->assertSame($initialCount, $summary['existing']);
		$this->assertSame($initialCount, Follow::query()->count());
	}

	public function test_command_supports_a_dry_run_and_requires_confirmation(): void
	{
		foreach (range(1, 4) as $number) {
			$this->createUser("follow-command-{$number}", "follow-command-{$number}@gmail.test");
		}

		$this->artisan('test-content:bulk-follow --campaign=follow-command-test --accounts=4 --targets=1,2 --dry-run')
			->expectsOutput('Test profiles in this batch: 4')
			->expectsOutput('Follower target range: 1-2')
			->expectsOutput('Dry run complete. No data was written.')
			->assertExitCode(0);

		$this->artisan('test-content:bulk-follow --campaign=follow-command-test --accounts=4 --targets=1,2')
			->expectsOutput('Refusing to write. Re-run with --confirm=FULL_TEST_FOLLOWS.')
			->assertExitCode(1);

		$this->artisan('test-content:bulk-follow --campaign=follow-command-test --accounts=4 --targets=1,2 --confirm=FULL_TEST_FOLLOWS')
			->assertExitCode(0);

		$this->assertGreaterThan(0, Follow::query()->count());
	}

	public function test_command_can_synchronize_existing_test_follower_counters_without_creating_follows(): void
	{
		$first = $this->createUser('follow-sync-one', 'follow-sync-one@gmail.test');
		$second = $this->createUser('follow-sync-two', 'follow-sync-two@gmail.test');

		Follow::query()->create([
			'follower_id' => $first->id,
			'following_id' => $second->id,
			'status' => FollowStatus::FOLLOWING,
		]);

		$this->artisan('test-content:bulk-follow --sync-only --confirm=FULL_TEST_FOLLOWS')
			->expectsOutput('Follower and following counters synchronized for active .test accounts.')
			->assertExitCode(0);

		$this->assertSame(1, (int) $first->fresh()->following_count);
		$this->assertSame(1, (int) $second->fresh()->followers_count);
		$this->assertSame(1, Follow::query()->count());
	}

	private function createUser(string $username, string $email): User
	{
		return User::query()->create([
			'first_name' => 'Test',
			'last_name' => 'Account',
			'username' => $username,
			'caption' => '@'.$username,
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
			'status' => UserStatus::ACTIVE,
			'type' => 'author',
		]);
	}
}
