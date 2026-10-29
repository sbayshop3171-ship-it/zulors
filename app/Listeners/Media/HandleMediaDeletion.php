<?php

namespace App\Listeners\Media;

use Throwable;
use App\Constants\Filesystem;
use App\Enums\Media\MediaType;
use Illuminate\Support\Facades\Log;
use App\Events\Media\MediaDeletedEvent;
use App\Services\Filesystem\Stats\StorageMetricsService;
use Illuminate\Support\Facades\DB;

class HandleMediaDeletion
{
    private $storageMetricsService;

    public function __construct(StorageMetricsService $storageMetricsService)
    {
        $this->storageMetricsService = $storageMetricsService;
    }

    public function handle(MediaDeletedEvent $event): void
    {
        if($event->mediaItem->disk != Filesystem::EXTERNAL_DISK_NAME) {
            try {
                $sourceStillReferenced = $this->isReferenced($event->mediaItem->disk, $event->mediaItem->source_path);
                $thumbnailStillReferenced = $this->isReferenced($event->mediaItem->thumbnail_disk, $event->mediaItem->thumbnail_path);

                if($event->mediaItem->type->isVideo()) {
					if (! $sourceStillReferenced) {
						$this->storageMetricsService->setDisk($event->mediaItem->disk)
							->decrementPartitionSize($event->mediaItem->size, $event->mediaItem->type);
					}
    
                    // Decrement thumbnail size since it is also stored separately as image
                    // even if it thumbnail of video.
    
					if (! $thumbnailStillReferenced && $event->mediaItem->thumbnail_size) {
						$this->storageMetricsService->setDisk($event->mediaItem->thumbnail_disk)
							->decrementPartitionSize($event->mediaItem->thumbnail_size, MediaType::IMAGE);
					}
                }
                else{
					if (! $sourceStillReferenced) {
						$this->storageMetricsService
							->setDisk($event->mediaItem->disk)
							->decrementPartitionSize($event->mediaItem->size, $event->mediaItem->type);
					}
                }
            }
            
            catch (Throwable $e) {
                Log::error("Error while decrementing partition size: {$e->getMessage()}");
            }
        }
    }

    private function isReferenced(?string $disk, ?string $path): bool
    {
        if (blank($disk) || blank($path)) {
            return false;
        }

        return DB::table('media')
            ->where(function ($query) use ($disk, $path): void {
                $query->where(function ($sourceQuery) use ($disk, $path): void {
                    $sourceQuery->where('disk', $disk)->where('source_path', $path);
                })->orWhere(function ($thumbnailQuery) use ($disk, $path): void {
                    $thumbnailQuery->where('thumbnail_disk', $disk)->where('thumbnail_path', $path);
                });
            })
            ->exists();
    }
}
