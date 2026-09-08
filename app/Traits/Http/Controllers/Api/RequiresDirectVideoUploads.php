<?php

namespace App\Traits\Http\Controllers\Api;

use App\Services\Media\Cloudflare\R2DirectUploadService;

trait RequiresDirectVideoUploads
{
    protected function requireDirectVideoService(R2DirectUploadService $r2): void
    {
        if(config('media.uploads.video.direct_only', false) && ! $r2->isConfigured()) {
            abort(response()->json([
                'message' => 'Direct video uploads are temporarily unavailable. Please try again later.',
                'code' => 'direct_upload_unavailable',
            ], 503));
        }
    }

    protected function rejectServerVideoUpload(): void
    {
        if(config('media.uploads.video.direct_only', false)) {
            abort(response()->json([
                'message' => 'Please upload videos using the direct upload API. Update your app if needed.',
                'code' => 'direct_upload_required',
            ], 409));
        }
    }
}
