<?php

namespace App\Listeners\Media;

use Exception;
use App\Constants\Filesystem;
use App\Enums\Media\MediaType;
use Illuminate\Support\Facades\Log;
use App\Events\Media\MediaDeletedEvent;
use App\Services\Filesystem\Stats\StorageMetricsService;

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
                if($event->mediaItem->type->isVideo()) {
        
                    $service = $this->storageMetricsService->setDisk($event->mediaItem->disk);
    
                    $service->decrementPartitionSize($event->mediaItem->size, $event->mediaItem->type);
    
                    // Decrement thumbnail size since it is also stored separately as image
                    // even if it thumbnail of video.
    
                    $service->decrementPartitionSize($event->mediaItem->thumbnail_size, MediaType::IMAGE);
                }
                else{
                    $this->storageMetricsService
                        ->setDisk($event->mediaItem->disk)
                        ->decrementPartitionSize($event->mediaItem->size, $event->mediaItem->type);
                }
            }
            
            catch (Exception $e) {
                Log::error("Error while decrementing partition size: {$e->getMessage()}");
            }
        }
    }
}
