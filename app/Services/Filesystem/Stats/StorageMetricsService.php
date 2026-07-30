<?php

namespace App\Services\Filesystem\Stats;

use App\Models\DataStat;
use App\Enums\Media\MediaType;

class StorageMetricsService
{
    private string $disk;

    public function setDisk(string $disk): self
    {
        $this->disk = $disk;

        return $this;
    }

    public function incrementPartitionSize($size, MediaType $mediaType): void
    {
        $size = $this->normalizeSize($size);
        $partitionItem = $this->getPartitionData($mediaType);

        $partitionItem->media_type = $mediaType;
        $partitionItem->content_size = intval($partitionItem->content_size + $size);
        $partitionItem->content_items = intval($partitionItem->content_items + 1);

        $partitionItem->save();
    }

    public function decrementPartitionSize($size, MediaType $mediaType): void
    {
        $size = $this->normalizeSize($size);
        $partitionItem = $this->getPartitionData($mediaType);
        $partitionItem->media_type = $mediaType;
        $partitionItem->content_size = max(0, intval($partitionItem->content_size - $size));
        $partitionItem->content_items = max(0, intval($partitionItem->content_items - 1));

        $partitionItem->save();
    }

    private function getPartitionData(MediaType $mediaType): DataStat
    {
        return DataStat::firstOrCreate([
            'media_type' => $mediaType,
            'disk' => $this->disk
        ], [
            'media_type' => $mediaType,
            'disk' => $this->disk
        ]);
    }

    private function normalizeSize($size): int
    {
        if(is_numeric($size)) {
            return max(0, (int) $size);
        }

        return 0;
    }
}
