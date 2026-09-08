<?php

namespace App\Traits\Http\Controllers\Api;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

trait SerializesMediaCompletion
{
    private function serializeMediaCompletion(Request $request, Closure $complete)
    {
        $key = 'media-completion:' . $request->user()->id . ':' . $request->integer('media_id');
        try {
            return Cache::lock($key, 3600)->block(5, $complete);
        }
        catch (LockTimeoutException $e) {
            return response()->json(['message' => 'Upload completion is in progress. Please retry.'], 409);
        }
    }
}
