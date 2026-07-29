<?php

namespace App\Listeners\Media;

use Exception;
use App\Constants\Filesystem;
use App\Enums\Media\MediaType;
use App\Events\Media\MediaCreatedEvent;
use App\Services\Filesystem\Stats\StorageMetricsService;

class HandleMediaCreation
{
    private $storageMetricsService;

    public function __construct(StorageMetricsService $storageMetricsService)
    {
        $this->storageMetricsService = $storageMetricsService;
    }

    public function handle(MediaCreatedEvent $event): void
    {
        if($event->mediaItem->disk != Filesystem::EXTERNAL_DISK_NAME) {
            try {
                if($event->mediaItem->type->isVideo()) {
                    $service = $this->storageMetricsService->setDisk($event->mediaItem->disk);

                    $service->incrementPartitionSize($event->mediaItem->size, $event->mediaItem->type);

                    // Increment thumbnail size since it is also stored separately as image
                    // even if it thumbnail of video.

                    if($event->mediaItem->thumbnail_size) {
                        $service->incrementPartitionSize($event->mediaItem->thumbnail_size, MediaType::IMAGE);
                    }
                }
                else{
                    $this->storageMetricsService
                        ->setDisk($event->mediaItem->disk)
                        ->incrementPartitionSize($event->mediaItem->size, $event->mediaItem->type);
                }
            }

            catch (Exception $e) {
                //
            }
        }
    }
}
