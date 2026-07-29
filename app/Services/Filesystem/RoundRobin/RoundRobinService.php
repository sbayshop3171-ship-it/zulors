<?php

namespace App\Services\Filesystem\RoundRobin;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class RoundRobinService
{
    private array $disks;

    private $diskCacheKey = 'current_file_storage_disk_index';

    public function __construct()
    {
        $this->disks = array_keys($this->getRoundRobinDisks());

        if(empty($this->disks)) {
            $this->disks = ['public'];
        }
    }

    public function getNextDisk()
    {
        $currentIndex = Cache::get($this->diskCacheKey, 0);

        if ($currentIndex >= count($this->disks)) {
            $currentIndex = 0;
        }

        $selectedDisk = $this->disks[$currentIndex];

        Cache::put($this->diskCacheKey, ($currentIndex + 1) % count($this->disks));

        return $selectedDisk;
    }

    public function getRoundRobinDisks()
    {
        return collect(Arr::except(config('filesystems.disks'), array_keys(config('filesystems.system_disks'))))
            ->filter(function(array $diskConfig) {
                if(array_key_exists('enabled', $diskConfig) && ! $diskConfig['enabled']) {
                    return false;
                }

                return ($diskConfig['round_robin'] ?? true) === true;
            })
            ->all();
    }
}
