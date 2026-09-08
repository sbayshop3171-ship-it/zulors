<?php

namespace App\Console\Commands;

use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Services\Media\Cloudflare\R2DirectUploadService;

class CleanupMediaTemporaryFiles extends Command
{
    protected $signature = 'media:cleanup-temp {--hours=72 : Delete temporary files older than this many hours}';

    protected $description = 'Delete stale local and R2 temporary media files.';

    public function handle(): int
    {
        $olderThan = now()->subHours(max(1, (int) $this->option('hours')))->getTimestamp();

        $deleted = $this->cleanupDisk('local', 'tmp', $olderThan);

        $tempDisk = config('media.cloudflare.r2.temp_disk', 'r2_temp');

        if(config("filesystems.disks.{$tempDisk}.enabled", false)) {
            $tempPrefix = config('media.cloudflare.r2.temp_prefix', 'tmp/direct/videos');
            $deleted += $this->cleanupDisk($tempDisk, $tempPrefix, $olderThan);
            $deleted += $this->cleanupMultipartUploads($tempDisk, $tempPrefix, $olderThan);
        }

        $this->info("Deleted {$deleted} stale temporary media file(s).");

        return self::SUCCESS;
    }

    private function cleanupDisk(string $disk, string $path, int $olderThan): int
    {
        $deleted = 0;

        try {
            foreach(Storage::disk($disk)->getDriver()->listContents(trim($path, '/'), true) as $file) {
                if(! $file->isFile()) continue;
                $filePath = $file->path();
                if(($file->lastModified() ?? Storage::disk($disk)->lastModified($filePath)) <= $olderThan) {
                    Storage::disk($disk)->delete($filePath);

                    $deleted++;
                }
            }
        }
        catch(Throwable $th) {
            $this->warn("Skipping {$disk}: {$th->getMessage()}");
        }

        return $deleted;
    }

    private function cleanupMultipartUploads(string $disk, string $path, int $olderThan): int
    {
        try {
            return app(R2DirectUploadService::class)->abortStaleMultipartUploads($disk, $path, $olderThan);
        }
        catch(Throwable $th) {
            $this->warn("Skipping {$disk} multipart cleanup: {$th->getMessage()}");

            return 0;
        }
    }
}
