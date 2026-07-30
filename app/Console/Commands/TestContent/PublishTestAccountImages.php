<?php

namespace App\Console\Commands\TestContent;

use App\Services\TestContent\TestAccountImagePublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PublishTestAccountImages extends Command
{
    protected $signature = 'test-content:publish-images
        {--campaign=test-image-gallery-v1 : A unique campaign key. Rerunning the same campaign resumes without duplicates.}
        {--source= : Absolute image directory. Defaults to storage/app/test-content/image-gallery-v1.}
        {--limit=0 : Maximum number of active .test users and images to process. Use 0 for all.}
        {--dry-run : Show the target count without writing posts or uploading media.}
        {--confirm= : Required value ALL_TEST_IMAGE_POSTS before any image post is written.}';

    protected $description = 'Publish one locally supplied image to each active .test account without touching real accounts.';

    public function handle(TestAccountImagePublisher $publisher): int
    {
        $campaignKey = trim((string) $this->option('campaign'));
        $sourceDirectory = trim((string) $this->option('source')) ?: storage_path('app/test-content/image-gallery-v1');
        $limit = max(0, (int) $this->option('limit'));
        $preview = $publisher->preview($sourceDirectory, $limit);
        $targetCount = count($preview['user_ids']);

        if($campaignKey === '') {
            $this->error('A campaign key is required.');

            return self::FAILURE;
        }

        if(! is_dir($sourceDirectory)) {
            $this->error("Source directory was not found: {$sourceDirectory}");

            return self::FAILURE;
        }

        $alreadyPublished = $publisher->alreadyPublishedCount($campaignKey);

        $this->info("Campaign: {$campaignKey}");
        $this->line("Usable source images: {$preview['source_count']}");
        $this->line("Eligible active .test accounts: {$preview['eligible_count']}");
        $this->line("Image posts targeted in this run: {$targetCount}");
        $this->line("Already published for this campaign: {$alreadyPublished}");

        if($this->option('dry-run')) {
            $this->comment('Dry run complete. No posts or media were created.');

            return self::SUCCESS;
        }

        if($this->option('confirm') !== 'ALL_TEST_IMAGE_POSTS') {
            $this->error('Nothing was published. Re-run with --confirm=ALL_TEST_IMAGE_POSTS after checking the target count.');

            return self::FAILURE;
        }

        if($targetCount === 0) {
            $this->warn('No source images or active .test accounts were found.');

            return self::SUCCESS;
        }

        $lock = Cache::lock('test-content-publish-images:' . sha1($campaignKey), 21600);

        if(! $lock->get()) {
            $this->error('This image campaign is already running.');

            return self::FAILURE;
        }

        $progress = $this->output->createProgressBar($targetCount);
        $progress->start();

        try {
            $summary = $publisher->publish(
                $campaignKey,
                $preview['user_ids'],
                $preview['source_files'],
                function() use ($progress) {
                    $progress->advance();
                },
            );

            $progress->finish();
            $this->newLine(2);
            $this->info("Published: {$summary['published']}");
            $this->line("Skipped (already completed): {$summary['skipped']}");
            $this->line("Failed: {$summary['failed']}");

            return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }
        catch(Throwable $exception) {
            report($exception);
            $this->newLine();
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        finally {
            $lock->release();
        }
    }
}
