<?php

namespace Tests\Feature;

use App\Enums\Post\PostStatus;
use App\Enums\Post\PostType;
use App\Enums\User\UserStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\TestContentEngagement;
use App\Models\User;
use App\Services\TestContent\BulkTestAccountEngagementPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BulkTestAccountEngagementPublisherTest extends TestCase
{
	use RefreshDatabase;

	public function test_it_adds_the_requested_reaction_and_comment_quotas_from_test_accounts(): void
	{
		foreach (range(1, 6) as $number) {
			$this->createUser("bulk-test-{$number}", "bulk-test-{$number}@gmail.test");
		}

		$post = $this->createPost($this->createUser('bulk-author', 'bulk-author@gmail.test'), 'A practical technology update');
		$publisher = app(BulkTestAccountEngagementPublisher::class);
		$preview = $publisher->preview('bulk-quota-test', 0, 0, 1, 4, 4, 3, 3);
		$summary = $publisher->publish('bulk-quota-test', $preview['users'], $preview['posts'], $preview['targets']);

		$this->assertSame(1, $summary['posts']);
		$this->assertSame(4, $summary['reactions_added']);
		$this->assertSame(3, $summary['comments_added']);
		$this->assertSame(0, $summary['failed']);
		$this->assertSame(4, (int) Reaction::query()->sum('reactions_count'));
		$this->assertSame(3, Comment::query()->where('post_id', $post->id)->count());
		$this->assertSame(3, TestContentEngagement::query()->where('campaign_key', 'bulk-quota-test')->count());
		$this->assertDatabaseHas('posts', ['id' => $post->id, 'comments_count' => 3]);

		$comments = Comment::query()->where('post_id', $post->id)->pluck('content')->all();
		$this->assertCount(3, array_unique($comments));
	}

	public function test_it_can_be_rerun_without_duplicate_comments_or_reactions(): void
	{
		foreach (range(1, 5) as $number) {
			$this->createUser("bulk-repeat-{$number}", "bulk-repeat-{$number}@gmail.test");
		}

		$this->createPost($this->createUser('repeat-author', 'repeat-author@gmail.test'), 'A repeatable business update');
		$publisher = app(BulkTestAccountEngagementPublisher::class);
		$preview = $publisher->preview('bulk-repeat-test', 0, 0, 1, 4, 4, 3, 3);
		$publisher->publish('bulk-repeat-test', $preview['users'], $preview['posts'], $preview['targets']);
		$summary = $publisher->publish('bulk-repeat-test', $preview['users'], $preview['posts'], $preview['targets']);

		$this->assertSame(0, $summary['reactions_added']);
		$this->assertSame(0, $summary['comments_added']);
		$this->assertSame(4, (int) Reaction::query()->sum('reactions_count'));
		$this->assertSame(3, Comment::query()->count());
		$this->assertSame(3, TestContentEngagement::query()->count());
	}

	public function test_command_supports_a_quota_dry_run_and_requires_confirmation(): void
	{
		foreach (range(1, 4) as $number) {
			$this->createUser("bulk-command-{$number}", "bulk-command-{$number}@gmail.test");
		}
		$this->createPost($this->createUser('command-author', 'command-author@gmail.test'), 'A command engagement update');

		$this->artisan('test-content:bulk-engage --campaign=bulk-command-test --users=3 --posts=1 --reaction-min=3 --reaction-max=3 --comment-min=2 --comment-max=2 --dry-run')
			->expectsOutput('Eligible active .test accounts: 3')
			->expectsOutput('Active posts in this batch: 1')
			->expectsOutput('Planned unique reactions: 3')
			->expectsOutput('Planned unique comments: 2')
			->expectsOutput('Dry run complete. No data was written.')
			->assertExitCode(0);

		$this->artisan('test-content:bulk-engage --campaign=bulk-command-test --users=3 --posts=1 --reaction-min=3 --reaction-max=3 --comment-min=2 --comment-max=2')
			->expectsOutput('Refusing to write. Re-run with --confirm=FULL_TEST_ENGAGEMENTS.')
			->assertExitCode(1);

		$this->artisan('test-content:bulk-engage --campaign=bulk-command-test --users=3 --posts=1 --reaction-min=3 --reaction-max=3 --comment-min=2 --comment-max=2 --confirm=FULL_TEST_ENGAGEMENTS')
			->assertExitCode(0);

		$this->assertDatabaseCount('comments', 2);
		$this->assertSame(3, (int) Reaction::query()->sum('reactions_count'));
	}

	public function test_it_scopes_posts_to_test_authors_by_default(): void
	{
		foreach (range(1, 5) as $number) {
			$this->createUser("bulk-scope-{$number}", "bulk-scope-{$number}@gmail.test");
		}

		$testAuthor = $this->createUser('test-author', 'test-author@gmail.test');
		$realAuthor = $this->createUser('real-author', 'real-author@example.com');
		$testPost = $this->createPost($testAuthor, 'A marketplace-ready update');
		$this->createPost($realAuthor, 'A real user post that should be skipped');

		$publisher = app(BulkTestAccountEngagementPublisher::class);
		$preview = $publisher->preview('bulk-scope-test', 0, 0, 10, 3, 3, 2, 2);

		$this->assertSame([$testPost->id], $preview['posts']->modelKeys());
	}

	public function test_it_can_scope_preview_to_image_posts_only(): void
	{
		foreach (range(1, 5) as $number) {
			$this->createUser("bulk-image-{$number}", "bulk-image-{$number}@gmail.test");
		}

		$author = $this->createUser('image-author', 'image-author@gmail.test');
		$imagePost = $this->createPost($author, 'An image gallery update', PostType::IMAGE);
		$this->createPost($author, 'A text update that should be skipped', PostType::TEXT);

		$publisher = app(BulkTestAccountEngagementPublisher::class);
		$preview = $publisher->preview('bulk-image-test', 0, 0, 10, 3, 3, 2, 2, true, PostType::IMAGE);

		$this->assertSame([$imagePost->id], $preview['posts']->modelKeys());
	}

	public function test_preview_can_limit_the_number_of_active_test_accounts(): void
	{
		foreach (range(1, 6) as $number) {
			$this->createUser("bulk-limit-{$number}", "bulk-limit-{$number}@gmail.test");
		}

		$this->createPost($this->createUser('limit-author', 'limit-author@gmail.test'), 'A limited pilot update');
		$publisher = app(BulkTestAccountEngagementPublisher::class);
		$preview = $publisher->preview('bulk-limit-test', 3, 0, 1, 2, 2, 1, 1);

		$this->assertCount(3, $preview['users']);
	}

	private function createPost(User $user, string $title, PostType $type = PostType::TEXT): Post
	{
		return Post::query()->create([
			'user_id' => $user->id,
			'title' => $title,
			'content' => 'Original test post content.',
			'status' => PostStatus::ACTIVE,
			'type' => $type,
			'text_language' => 'en',
		]);
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
