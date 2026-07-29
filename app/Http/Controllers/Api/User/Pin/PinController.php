<?php

namespace App\Http\Controllers\Api\User\Pin;

use App\Models\Pin;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Http\Resources\User\Timeline\TimelineResource;

class PinController extends Controller
{
    use SupportsApiResponses;

    public function getGlobalPinnedPosts(Request $request)
    {
        $pinedPosts = Pin::query()->globalPinnedPosts()->with(['pinnable' => function($query) {
            $query->timelineFormatPosts();
        }])->latest()->get();

        return $this->responseSuccess([
            'data' => $pinedPosts->map(function($pin) {
                return TimelineResource::make($pin->pinnable);
            })
        ]);
    }
}
