<?php

namespace App\Console\Commands\TestContent;

use App\Services\TestContent\BulkTestAccountFollowPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class BulkFollowTestAccounts extends Command
{
	protected $signature = 'test-content:bulk-follow
		{--campaign=full-test-follow-v1 : Stable campaign key used to resume safely}
		{--after-id=0 : Only process .test profiles with an id greater than this value}
		{--accounts=25 : Number of test profiles to process, unless --all is supplied}
		{--all : Process every remaining active .test profile in controlled batches}
		{--targets=500,1000,2000,3000,4000 : Comma-separated follower targets assigned deterministically}
		{--sync-only : Synchronize follower and following counters without creating relationships}
		{--dry-run : Show the calculated workload without writing data}
		{--confirm= : Must equal FULL_TEST_FOLLOWS before data is written}';

	protected $description = 'Create varied, deterministic follower counts between active .test accounts without touching real accounts or sending notifications.';

	public function handle(BulkTestAccountFollowPublisher $publisher): int
	{
		$campaign = trim((string) $this->option('campaign'));
		$afterId = max(0, (int) $this->option('after-id'));
		$accountLimit = max(1, (int) $this->option('accounts'));
		$targets = $this->parseTargets((string) $this->option('targets'));

		if ($campaign === '' || $targets === []) {
			$this->error('Campaign and follower targets must be valid.');

			return self::FAILURE;
		}

		$preview = $publisher->preview($campaign, $afterId, $accountLimit, $targets);
		$users = $preview['users'];
		$plannedFollows = array_sum($preview['quotas']);

		$this->info("Campaign: {$campaign}");
		$this->line('Eligible active .test accounts: '.count($users));
		$this->line('Test profiles in this batch: '.$preview['targets']->count());
		$this->line("Planned follows: {$plannedFollows}");
		$this->line('Follower target range: '.min($targets).'-'.max($targets));

		if ($this->option('sync-only')) {
			if ($this->option('dry-run')) {
				$this->comment('Dry run complete. No follower counters were synchronized.');

				return self::SUCCESS;
			}

			if ($this->option('confirm') !== 'FULL_TEST_FOLLOWS') {
				$this->error('Refusing to synchronize. Re-run with --confirm=FULL_TEST_FOLLOWS.');

				return self::FAILURE;
			}

			$publisher->synchronizeCampaignCounts($campaign, $users, $targets);
			$this->info('Follower and following counters synchronized for active .test accounts.');

			return self::SUCCESS;
		}

		if (count($users) - 1 < max($targets)) {
			$this->error('There are not enough active .test accounts for the requested follower target.');

			return self::FAILURE;
		}

		if ($preview['targets']->isEmpty()) {
			$this->comment('No remaining active .test profiles were found.');

			return self::SUCCESS;
		}

		if ($this->option('dry-run')) {
			if ($this->option('all')) {
				$this->comment('The full run will continue in batches of '.$accountLimit.' test profiles.');
			}
			$this->comment('Dry run complete. No data was written.');

			return self::SUCCESS;
		}

		if ($this->option('confirm') !== 'FULL_TEST_FOLLOWS') {
			$this->error('Refusing to write. Re-run with --confirm=FULL_TEST_FOLLOWS.');

			return self::FAILURE;
		}

		$lock = Cache::lock('test-content-bulk-follow:'.sha1($campaign), 21600);

		if (! $lock->get()) {
			$this->error('This campaign is already running.');

			return self::FAILURE;
		}

		try {
			$summary = $this->publishBatches($publisher, $campaign, $afterId, $accountLimit, $targets, $preview, (bool) $this->option('all'));
			$publisher->synchronizeCampaignCounts($campaign, $users, $targets);
		} finally {
			$lock->release();
		}

		$this->newLine();
		$this->info("Profiles completed: {$summary['targets']}");
		$this->line("Follows added: {$summary['added']}");
		$this->line("Existing follows: {$summary['existing']}");
		$this->line("Promoted existing requests: {$summary['promoted']}");
		$this->line("Failed profiles: {$summary['failed']}");

		return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
	}

	/** @return array<int, int> */
	private function parseTargets(string $targets): array
	{
		$parsed = [];

		foreach (explode(',', $targets) as $target) {
			$target = trim($target);

			if ($target === '' || ! ctype_digit($target) || (int) $target < 1) {
				return [];
			}

			$parsed[] = (int) $target;
		}

		return array_values(array_unique($parsed));
	}

	/**
	 * @param array<int, int> $targets
	 * @return array{targets: int, added: int, existing: int, promoted: int, failed: int}
	 */
	private function publishBatches(
		BulkTestAccountFollowPublisher $publisher,
		string $campaign,
		int $afterId,
		int $accountLimit,
		array $targets,
		array $firstPreview,
		bool $all,
	): array {
		$summary = [
			'targets' => 0,
			'added' => 0,
			'existing' => 0,
			'promoted' => 0,
			'failed' => 0,
		];
		$preview = $firstPreview;

		do {
			$profiles = $preview['targets'];
			$progress = $this->output->createProgressBar($profiles->count());
			$progress->start();

			try {
				$batchSummary = $publisher->publish(
					$campaign,
					$preview['users'],
					$profiles,
					$preview['quotas'],
					function ($profile, $target, $result) use ($progress): void {
						$progress->advance();

						if ($result['failed']) {
							$this->warn("Profile {$profile->id} failed and can be retried safely.");
						}
					},
				);
			} finally {
				$progress->finish();
				$this->newLine(2);
			}

			foreach ($summary as $key => $value) {
				$summary[$key] = $value + $batchSummary[$key];
			}

			$lastProfile = $profiles->last();
			$afterId = $lastProfile?->id ?? $afterId;
			$preview = $all
				? $publisher->preview($campaign, $afterId, $accountLimit, $targets)
				: ['targets' => collect()];
		} while ($all && $preview['targets']->isNotEmpty());

		return $summary;
	}
}
