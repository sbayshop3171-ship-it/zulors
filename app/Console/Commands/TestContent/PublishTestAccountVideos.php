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
            $sourceDirectories = [storage_path('app/test-content/video-gallery-v1')];
        }

        $limit = max(0, (int) $this->option('limit'));
        $preview = $publisher->previewForDirectories($sourceDirectories, $limit);
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

        $lock = Cache::lock('test-content-publish-videos:' . sha1($campaignKey), 21600);

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
}
