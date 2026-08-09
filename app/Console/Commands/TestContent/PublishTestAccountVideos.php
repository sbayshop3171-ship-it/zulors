<?php

namespace App\Console\Commands\TestContent;

use App\Services\TestContent\TestAccountVideoPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PublishTestAccountVideos extends Command
{
    protected $signature = 'test-content:publish-videos
        {--campaign=test-video-gallery-v1 : A unique campaign key. Rerunning the same campaign resumes without duplicates.}
        {--source=* : Absolute video directory. Repeat this option to merge multiple video libraries.}
        {--limit=0 : Maximum number of active .test users and videos to process. Use 0 for all.}
        {--random : Select eligible .test accounts in a stable random order.}
        {--seed= : Optional stable random seed. Defaults to the campaign key.}
        {--shards=1 : Split the selected video/user pairs across this many workers.}
        {--shard=0 : Zero-based shard index to publish when --shards is greater than 1.}
        {--dry-run : Show the target count without writing posts or uploading media.}
        {--confirm= : Required value ALL_TEST_VIDEO_POSTS before any video post is written.}';

    protected $description = 'Publish one locally supplied video to each active .test account without touching real accounts.';

    public function handle(TestAccountVideoPublisher $publisher): int
    {
        $campaignKey = trim((string) $this->option('campaign'));
        $sourceDirectories = collect($this->option('source'))
            ->filter(fn ($source) => is_string($source) && trim($source) !== '')
            ->map(fn (string $source) => trim($source))
            ->values()
            ->all();

        if ($sourceDirectories === []) {
            $sourceDirectories = $this->defaultSourceDirectories();
        }

        $limit = max(0, (int) $this->option('limit'));
        $randomizeUsers = (bool) $this->option('random');
        $selectionSeed = trim((string) $this->option('seed'));
        $shards = max(1, (int) $this->option('shards'));
        $shard = max(0, (int) $this->option('shard'));
        $preview = $publisher->previewForDirectories(
            $sourceDirectories,
            $limit,
            $randomizeUsers,
            $selectionSeed !== '' ? $selectionSeed : $campaignKey,
        );

        if ($shard >= $shards) {
            $this->error('The --shard value must be less than --shards.');

            return self::FAILURE;
        }

        if ($shards > 1) {
            $preview = $this->previewForShard($preview, $shards, $shard);
        }

        $targetCount = count($preview['user_ids']);

        if ($campaignKey === '') {
            $this->error('A campaign key is required.');

            return self::FAILURE;
        }

        $missingDirectory = collect($sourceDirectories)->first(fn (string $sourceDirectory) => ! is_dir($sourceDirectory));

        if ($missingDirectory) {
            $this->error("Source directory was not found: {$missingDirectory}");

            return self::FAILURE;
        }

        $alreadyPublished = $publisher->alreadyPublishedCount($campaignKey);

        $this->info("Campaign: {$campaignKey}");
        $this->line('Video source directories: ' . count($sourceDirectories));
        $this->line("Usable source videos: {$preview['source_count']}");
        $this->line("Eligible active .test accounts: {$preview['eligible_count']}");
        $this->line("Video posts targeted in this run: {$targetCount}");
        $this->line("Already published for this campaign: {$alreadyPublished}");
        $this->line('User selection: ' . ($randomizeUsers ? 'stable random' : 'oldest active .test accounts'));

        if ($randomizeUsers) {
            $this->line('Selection seed: ' . ($selectionSeed !== '' ? $selectionSeed : $campaignKey));
        }

        if ($shards > 1) {
            $this->line('Shard: ' . ($shard + 1) . " of {$shards}");
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry run complete. No posts or media were created.');

            return self::SUCCESS;
        }

        if ($this->option('confirm') !== 'ALL_TEST_VIDEO_POSTS') {
            $this->error('Nothing was published. Re-run with --confirm=ALL_TEST_VIDEO_POSTS after checking the target count.');

            return self::FAILURE;
        }

        if ($targetCount === 0) {
            $this->warn('No source videos or active .test accounts were found.');

            return self::SUCCESS;
        }

        $lockKey = $shards > 1
            ? 'test-content-publish-videos:' . sha1("{$campaignKey}:shard:{$shard}:of:{$shards}")
            : 'test-content-publish-videos:' . sha1($campaignKey);
        $lock = Cache::lock($lockKey, 21600);

        if (! $lock->get()) {
            $this->error('This video campaign is already running.');

            return self::FAILURE;
        }

        $progress = $this->output->createProgressBar($targetCount);
        $progress->start();

        try {
            $summary = $publisher->publish(
                $campaignKey,
                $preview['user_ids'],
                $preview['source_files'],
                function () use ($progress) {
                    $progress->advance();
                },
            );

            $progress->finish();
            $this->newLine(2);
            $this->info("Published: {$summary['published']}");
            $this->line("Skipped (already completed): {$summary['skipped']}");
            $this->line("Failed: {$summary['failed']}");

            return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->newLine();
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<int, string>
     */
    private function defaultSourceDirectories(): array
    {
        $localSiblingVideoFolder = dirname(base_path()) . DIRECTORY_SEPARATOR . 'video main ';
        $fallbackDirectory = storage_path('app/test-content/video-gallery-v1');

        foreach ([$localSiblingVideoFolder, $fallbackDirectory] as $candidate) {
            if (is_dir($candidate)) {
                return [$candidate];
            }
        }

        return [$fallbackDirectory];
    }

    /**
     * @param array{user_ids: array<int, int>, source_files: array<int, string>, eligible_count: int, source_count: int} $preview
     * @return array{user_ids: array<int, int>, source_files: array<int, string>, eligible_count: int, source_count: int}
     */
    private function previewForShard(array $preview, int $shards, int $shard): array
    {
        $userIds = [];
        $sourceFiles = [];

        foreach ($preview['user_ids'] as $position => $userId) {
            if ($position % $shards !== $shard) {
                continue;
            }

            $userIds[] = $userId;
            $sourceFiles[] = $preview['source_files'][$position];
        }

        return [
            'user_ids' => $userIds,
            'source_files' => $sourceFiles,
            'eligible_count' => $preview['eligible_count'],
            'source_count' => $preview['source_count'],
        ];
    }
}
