<?php

namespace App\Console\Commands\TestContent;

use App\Services\TestContent\TestAccountEngagementPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class EngageTestAccountContent extends Command
{
	protected $signature = 'test-content:engage
		{--campaign=pilot-test-engagement-v1 : Stable campaign key used to prevent duplicate engagements}
		{--users=25 : Number of active .test accounts to use}
		{--posts=10 : Number of active posts to engage with}
		{--dry-run : Show the selected accounts and posts without writing data}
		{--confirm= : Must equal TEST_ENGAGEMENTS before data is written}';

	protected $description = 'Add one deterministic reaction and one original test comment per selected .test account and post.';

	public function handle(TestAccountEngagementPublisher $publisher): int
	{
		$campaign = (string) $this->option('campaign');
		$userLimit = max(0, (int) $this->option('users'));
		$postLimit = max(0, (int) $this->option('posts'));
		$preview = $publisher->preview($userLimit, $postLimit);
		$users = $preview['users'];
		$posts = $preview['posts'];

		$this->info("Campaign: {$campaign}");
		$this->line("Selected active .test accounts: {$users->count()}");
		$this->line("Selected active posts: {$posts->count()}");
		$this->line('Planned reactions and comments: '.($users->count() * $posts->count()).' each');

		if ($users->isEmpty() || $posts->isEmpty()) {
			$this->error('No eligible account or post was found. Nothing was written.');

			return self::FAILURE;
		}

		if ($this->option('dry-run')) {
			$this->comment('Dry run complete. No data was written.');

			return self::SUCCESS;
		}

		if ($this->option('confirm') !== 'TEST_ENGAGEMENTS') {
			$this->error('Refusing to write. Re-run with --confirm=TEST_ENGAGEMENTS.');

			return self::FAILURE;
		}

		$lock = Cache::lock('test-content-engagement:'.sha1($campaign), 7200);

		if (! $lock->get()) {
			$this->error('This campaign is already running.');

			return self::FAILURE;
		}

		try {
			$summary = $publisher->publish(
				$campaign,
				$users,
				$posts,
				function (int $current, int $total, $user, $post, string $result): void {
					if ($result === 'failed') {
						$this->warn("[{$current}/{$total}] Failed user {$user->id}, post {$post->id}");
					}
				},
			);
		} finally {
			$lock->release();
		}

		$this->newLine();
		$this->info("Published: {$summary['published']}");
		$this->line("Skipped: {$summary['skipped']}");
		$this->line("Failed: {$summary['failed']}");

		return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
	}
}
