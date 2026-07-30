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
use App\Services\TestContent\TestAccountEngagementPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TestAccountEngagementPublisherTest extends TestCase
{
	use RefreshDatabase;

	public function test_it_adds_one_reaction_and_one_original_comment_per_test_user_and_post(): void
	{
		$firstTestUser = $this->createUser('first-engagement-test', 'first-engagement@gmail.test');
		$secondTestUser = $this->createUser('second-engagement-test', 'second-engagement@gmail.test');
		$postAuthor = $this->createUser('post-author', 'post-author@example.com');
		$selfPost = $this->createPost($firstTestUser, 'A self-authored post should not be selected');
		$firstPost = $this->createPost($postAuthor, 'A practical technology update');
		$secondPost = $this->createPost($postAuthor, 'A thoughtful business perspective');

		$publisher = app(TestAccountEngagementPublisher::class);
		$preview = $publisher->preview(2, 2);
		$summary = $publisher->publish('engagement-pilot', $preview['users'], $preview['posts']);

		$this->assertSame([
			'published' => 4,
			'skipped' => 0,
			'failed' => 0,
		], $summary);
		$this->assertEqualsCanonicalizing([$firstPost->id, $secondPost->id], $preview['posts']->modelKeys());
		$this->assertNotContains($selfPost->id, $preview['posts']->modelKeys());
		$this->assertSame(4, Comment::query()->count());
		$this->assertSame(4, TestContentEngagement::query()->where('status', 'published')->count());
		$this->assertSame(2, Comment::query()->where('post_id', $firstPost->id)->count());
		$this->assertSame(2, Comment::query()->where('post_id', $secondPost->id)->count());
		$this->assertSame(2, (int) Reaction::query()
			->where('reactable_type', Post::class)
			->where('reactable_id', $firstPost->id)
			->sum('reactions_count'));
		$this->assertSame(2, (int) Reaction::query()
			->where('reactable_type', Post::class)
			->where('reactable_id', $secondPost->id)
			->sum('reactions_count'));
		$this->assertDatabaseHas('posts', ['id' => $firstPost->id, 'comments_count' => 2]);
		$this->assertDatabaseHas('posts', ['id' => $secondPost->id, 'comments_count' => 2]);
	}

	public function test_same_campaign_can_be_rerun_without_duplicate_reactions_or_comments(): void
	{
		$this->createUser('repeat-engagement-test', 'repeat-engagement@gmail.test');
		$postAuthor = $this->createUser('repeat-post-author', 'repeat-post-author@example.com');
		$this->createPost($postAuthor, 'A reliable post for repeatable engagement testing');

		$publisher = app(TestAccountEngagementPublisher::class);
		$preview = $publisher->preview(1, 1);
		$publisher->publish('repeatable-engagement-campaign', $preview['users'], $preview['posts']);
		$summary = $publisher->publish('repeatable-engagement-campaign', $preview['users'], $preview['posts']);

		$this->assertSame([
			'published' => 0,
			'skipped' => 1,
			'failed' => 0,
		], $summary);
		$this->assertSame(1, Comment::query()->count());
		$this->assertSame(1, TestContentEngagement::query()->count());
		$this->assertSame(1, (int) Reaction::query()->sum('reactions_count'));
	}

	public function test_command_requires_confirmation_and_supports_a_dry_run(): void
	{
		$this->createUser('command-engagement-test', 'command-engagement@gmail.test');
		$postAuthor = $this->createUser('command-post-author', 'command-post-author@example.com');
		$this->createPost($postAuthor, 'A command test post');

		$this->artisan('test-content:engage --campaign=command-engagement --users=1 --posts=1 --dry-run')
			->expectsOutput('Selected active .test accounts: 1')
			->expectsOutput('Selected active posts: 1')
			->expectsOutput('Dry run complete. No data was written.')
			->assertExitCode(0);

		$this->assertDatabaseCount('comments', 0);

		$this->artisan('test-content:engage --campaign=command-engagement --users=1 --posts=1')
			->expectsOutput('Refusing to write. Re-run with --confirm=TEST_ENGAGEMENTS.')
			->assertExitCode(1);

		$this->artisan('test-content:engage --campaign=command-engagement --users=1 --posts=1 --confirm=TEST_ENGAGEMENTS')
			->assertExitCode(0);

		$this->assertDatabaseCount('comments', 1);
		$this->assertDatabaseHas('test_content_engagements', [
			'campaign_key' => 'command-engagement',
			'status' => 'published',
		]);
	}

	private function createPost(User $user, string $title): Post
	{
		return Post::query()->create([
			'user_id' => $user->id,
			'title' => $title,
			'content' => 'Original test content for engagement coverage.',
			'status' => PostStatus::ACTIVE,
			'type' => PostType::TEXT,
			'text_language' => 'en',
		]);
	}

	private function createUser(string $username, string $email, UserStatus $status = UserStatus::ACTIVE): User
	{
		return User::query()->create([
			'first_name' => 'Test',
			'last_name' => 'Author',
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
			'status' => $status,
			'type' => 'author',
		]);
	}
}
