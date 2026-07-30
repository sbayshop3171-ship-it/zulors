<?php

namespace App\Console\Commands\TestContent;

use App\Services\TestContent\TestAccountContentPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PublishTestAccountContent extends Command
{
    protected $signature = 'test-content:publish
        {--campaign=initial-test-articles : A unique campaign key. Rerunning the same campaign resumes without duplicates.}
        {--limit=0 : Maximum number of active .test users to publish for. Use 0 for all.}
        {--dry-run : Show the target count without writing posts.}
        {--confirm= : Required value ALL_TEST_ACCOUNTS before any post is written.}';

    protected $description = 'Publish one original text article to each active .test account without using user credentials.';

    public function handle(TestAccountContentPublisher $publisher): int
    {
        $campaignKey = trim((string) $this->option('campaign'));
        $limit = max(0, (int) $this->option('limit'));
        $targetCount = $publisher->eligibleCount();

        if($campaignKey === '') {
            $this->error('A campaign key is required.');

            return self::FAILURE;
        }

        if($limit > 0) {
            $targetCount = min($targetCount, $limit);
        }

        $alreadyPublished = $publisher->alreadyPublishedCount($campaignKey);

        $this->info("Campaign: {$campaignKey}");
        $this->info("Eligible active .test accounts: {$targetCount}");
        $this->info("Already published for this campaign: {$alreadyPublished}");

        if($this->option('dry-run')) {
            $this->comment('Dry run complete. No posts were created.');

            return self::SUCCESS;
        }

        if($this->option('confirm') !== 'ALL_TEST_ACCOUNTS') {
            $this->error('Nothing was published. Re-run with --confirm=ALL_TEST_ACCOUNTS after checking the target count.');

            return self::FAILURE;
        }

        if($targetCount === 0) {
            $this->warn('No active .test accounts were found.');

            return self::SUCCESS;
        }

        $lock = Cache::lock('test-content-publish:' . sha1($campaignKey), 7200);

        if(! $lock->get()) {
            $this->error('This campaign is already running.');

            return self::FAILURE;
        }

        $progress = $this->output->createProgressBar($targetCount);
        $progress->start();

        try {
            $summary = $publisher->publish($campaignKey, $limit, function() use ($progress) {
                $progress->advance();
            });

            $progress->finish();
            $this->newLine(2);
            $this->info("Published: {$summary['published']}");
            $this->info("Skipped (already completed): {$summary['skipped']}");
            $this->info("Failed: {$summary['failed']}");

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
