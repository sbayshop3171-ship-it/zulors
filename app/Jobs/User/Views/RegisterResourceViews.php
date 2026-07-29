<?php

namespace App\Jobs\User\Views;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RegisterResourceViews implements ShouldQueue
{
    use Queueable;

    private array $views;

    /**
     * Create a new job instance.
     */
    public function __construct(array $views = [])
    {
        $this->views = $views;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if(! empty($this->views)) {
            foreach($this->views as $resourceModelName => $idsArr) {
                
                $resourceModel = app()->make($resourceModelName);
                
                $resourceModel::whereIn('id', $idsArr)->increment('views_count');
            }
        }
    }
}
