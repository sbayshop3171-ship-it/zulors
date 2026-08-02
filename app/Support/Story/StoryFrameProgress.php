<?php

namespace App\Support\Story;

use App\Models\StoryFrame;

class StoryFrameProgress
{
    public static function make(?StoryFrame $frameData): array
    {
        if(empty($frameData)) {
            return [
                'upload' => 100,
                'processing' => 100,
                'overall' => 100,
                'display' => 100,
                'state' => 'processed',
                'stage' => 'ready'
            ];
        }

        $media = $frameData->media->first();
        $metadata = $media?->metadata ?? [];
        $uploadProgress = max(0, min(100, (int) data_get($metadata, 'upload_progress', 100)));
        $processingProgress = max(1, min(100, (int) data_get($metadata, 'processing_progress', 1)));
        $processingState = data_get($metadata, 'processing_state', 'queued');
        $isFailed = $media?->status?->isFailed() || $processingState === 'failed';
        $isProcessed = $media?->status?->isProcessed() || $processingState === 'processed';
        $stage = self::stage($uploadProgress, $processingState, $isFailed, $isProcessed);
        $overallProgress = $uploadProgress < 100 ? $uploadProgress : $processingProgress;
        $displayProgress = max(1, min(100, $overallProgress));

        if(! $isFailed && ! $isProcessed) {
            $displayProgress = min(99, $displayProgress);
        }

        return [
            'upload' => $uploadProgress,
            'processing' => $processingProgress,
            'overall' => $overallProgress,
            'display' => $displayProgress,
            'state' => $processingState,
            'stage' => $stage
        ];
    }

    private static function stage(int $uploadProgress, string $processingState, bool $isFailed, bool $isProcessed): string
    {
        if($isFailed) {
            return 'failed';
        }

        if($isProcessed) {
            return 'ready';
        }

        if($uploadProgress < 100) {
            return 'uploading';
        }

        if($processingState === 'queued') {
            return 'uploaded';
        }

        if($processingState === 'uploading') {
            return 'finishing';
        }

        return 'processing';
    }
}
