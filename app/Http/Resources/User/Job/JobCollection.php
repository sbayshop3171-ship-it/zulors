<?php

namespace App\Http\Resources\User\Job;

use Illuminate\Http\Request;
use App\Http\Resources\User\Job\JobResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class JobCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return $this->collection->map(function ($job) {
            return JobResource::make($job);
        })->all();
    }
}
