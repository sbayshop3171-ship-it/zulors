<?php

namespace App\Http\Resources\User\Chat;

use Illuminate\Http\Request;
use App\Http\Resources\User\Chat\RequestResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class RequestCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return $this->collection->map(function($requestItem) {
            return RequestResource::make($requestItem->resource);
        })->all();
    }
}
