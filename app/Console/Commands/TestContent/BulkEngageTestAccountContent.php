<?php

namespace App\Console\Commands\TestContent;

use App\Services\TestContent\BulkTestAccountEngagementPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class BulkEngageTestAccountContent extends Command
{
	protected $signature = 'test-content:bulk-engage
		{--campaign=full-test-engagement-v1 : Stable campaign key used to resume without duplicate comments}
		{--after-id=0 : Only process active posts with an id greater than this value}
		{--posts=25 : Number of active posts to process, unless --all is supplied}
		{--all : Process every remaining active post in controlled batches}
		{--reaction-min=1000 : Minimum unique .test reactions per post}
		{--reaction-max=2000 : Maximum unique .test reactions per post}
		{--comment-min=80 : Minimum unique .test comments per post}
		{--comment-max=200 : Maximum unique .test comments per post}
		{--dry-run : Show the calculated workload without writing data}
		{--confirm= : Must equal FULL_TEST_ENGAGEMENTS before data is written}';

	protected $description = 'Add deterministic high-volume reactions and original comments from active .test accounts to active posts.';

	public function handle(BulkTestAccountEngagementPublisher $publisher): int
	{
		$campaign = trim((string) $this->option('campaign'));
		$afterId = max(0, (int) $this->option('after-id'));
		$postLimit = max(1, (int) $this->option('posts'));
		$reactionMin = max(0, (int) $this->option('reaction-min'));
		$reactionMax = max(0, (int) $this->option('reaction-max'));
		$commentMin = max(0, (int) $this->option('comment-min'));
		$commentMax = max(0, (int) $this->option('comment-max'));

		if ($campaign === '' || $reactionMin > $reactionMax || $commentMin > $commentMax) {
			$this->error('Campaign and target ranges must be valid.');

			return self::FAILURE;
		}

		$preview = $publisher->preview(
			$campaign,
			$afterId,
			$postLimit,
			$reactionMin,
			$reactionMax,
			$commentMin,
			$commentMax,
		);
		$users = $preview['users'];
		$posts = $preview['posts'];
		$targets = $preview['targets'];
		$plannedReactions = array_sum(array_column($targets, 'reactions'));
		$plannedComments = array_sum(array_column($targets, 'comments'));

		$this->info("Campaign: {$campaign}");
		$this->line('Eligible active .test accounts: '.count($users));
		$this->line("Active posts in this batch: {$posts->count()}");
		$this->line("Planned unique reactions: {$plannedReactions}");
		$this->line("Planned unique comments: {$plannedComments}");

		if (count($users) < $reactionMin || count($users) < $commentMin) {
			$this->error('There are not enough active .test accounts for the requested target.');

			return self::FAILURE;
		}

		if ($posts->isEmpty()) {
			$this->comment('No remaining active posts were found.');

			return self::SUCCESS;
		}

		if ($this->option('dry-run')) {
			if ($this->option('all')) {
				$this->comment('The full run will continue in batches of '.$postLimit.' active posts.');
			}
			$this->comment('Dry run complete. No data was written.');

			return self::SUCCESS;
		}

		if ($this->option('confirm') !== 'FULL_TEST_ENGAGEMENTS') {
			$this->error('Refusing to write. Re-run with --confirm=FULL_TEST_ENGAGEMENTS.');

			return self::FAILURE;
		}

		$lock = Cache::lock('test-content-bulk-engagement:'.sha1($campaign), 21600);

		if (! $lock->get()) {
			$this->error('This campaign is already running.');

			return self::FAILURE;
		}

		try {
			$summary = $this->publishBatches(
				$publisher,
				$campaign,
				$afterId,
				$postLimit,
				$reactionMin,
				$reactionMax,
				$commentMin,
				$commentMax,
				$preview,
				(bool) $this->option('all'),
			);
		} finally {
			$lock->release();
		}

		$this->newLine();
		$this->info("Posts completed: {$summary['posts']}");
		$this->line("Reactions added: {$summary['reactions_added']}");
		$this->line("Comments added: {$summary['comments_added']}");
		$this->line("Already present: ".($summary['reactions_existing'] + $summary['comments_existing']));
		$this->line("Failed posts: {$summary['failed']}");

		return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
	}

	/**
	 * @param array<int, int> $users
	 * @param array<int, array{reactions: int, comments: int}> $targets
	 * @return array{posts: int, reactions_added: int, reactions_existing: int, comments_added: int, comments_existing: int, failed: int}
	 */
	private function publishBatches(
		BulkTestAccountEngagementPublisher $publisher,
		string $campaign,
		int $afterId,
		int $postLimit,
		int $reactionMin,
		int $reactionMax,
		int $commentMin,
		int $commentMax,
		array $firstPreview,
		bool $all,
	): array {
		$summary = [
			'posts' => 0,
			'reactions_added' => 0,
			'reactions_existing' => 0,
			'comments_added' => 0,
			'comments_existing' => 0,
			'failed' => 0,
		];
		$preview = $firstPreview;

		do {
			$posts = $preview['posts'];
			$progress = $this->output->createProgressBar($posts->count());
			$progress->start();

			try {
				$batchSummary = $publisher->publish(
					$campaign,
					$preview['users'],
					$posts,
					$preview['targets'],
					function ($post, $target, $result) use ($progress): void {
						$progress->advance();

						if ($result['failed']) {
							$this->warn("Post {$post->id} failed and can be retried safely.");
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

			$lastPost = $posts->last();
			$afterId = $lastPost?->id ?? $afterId;
			$preview = $all
				? $publisher->preview($campaign, $afterId, $postLimit, $reactionMin, $reactionMax, $commentMin, $commentMax)
				: ['posts' => collect()];
		} while ($all && $preview['posts']->isNotEmpty());

		return $summary;
	}
}
