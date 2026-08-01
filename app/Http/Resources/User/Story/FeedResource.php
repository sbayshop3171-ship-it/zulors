<?php
/*
|--------------------------------------------------------------------------
| Zulors - The Zulors Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  Zulors. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Http\Resources\User\Story;

use App\Enums\Story\StoryStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = ($this->user_id == me()->id);
        $processingFrame = $isOwner ? $this->getProcessingFrame() : null;
        $hasActiveFrame = $this->frames->some(function($frame) {
            return $frame->status->isActive() && $this->isFrameUnexpired($frame);
        });

        return [
            'story_uuid' => $this->story_uuid,
            'frame_id' => $processingFrame?->id,
            'status' => $processingFrame ? StoryStatus::PROCESSING->value : StoryStatus::ACTIVE->value,
            'can_open' => $hasActiveFrame,
            'relations' => [
                'user' => [
                    'name' => $this->user->name,
                    'avatar_url' => $this->user->avatar_url
                ]
            ],
            'is_seen' => $this->checkIfStorySeen(),
            'is_owner' => $isOwner,
            'progress' => $this->getProcessingProgress($processingFrame)
        ];
    }

    private function checkIfStorySeen()
    {
        return $this->frames->some(function($frame) {
            return $frame->views->contains('user_id', me()->id);
        });
    }

    private function getProcessingFrame()
    {
        return $this->frames->filter(function($frame) {
            return $frame->status->isProcessing() && $this->isFrameUnexpired($frame);
        })->sortByDesc('id')->first();
    }

    private function isFrameUnexpired($frame): bool
    {
        return ! empty($frame->expires_at) && ! $frame->expires_at->isPast();
    }

    private function getProcessingProgress($processingFrame): array
    {
        if(empty($processingFrame)) {
            return [
                'upload' => 100,
                'processing' => 100,
                'overall' => 100,
                'state' => 'processed'
            ];
        }

        $media = $processingFrame->media->first();
        $metadata = $media?->metadata ?? [];
        $uploadProgress = max(0, min(100, (int) data_get($metadata, 'upload_progress', 100)));
        $processingProgress = max(1, min(99, (int) data_get($metadata, 'processing_progress', 1)));
        $processingState = data_get($metadata, 'processing_state', 'queued');

        if($media?->status?->isFailed()) {
            $processingProgress = 100;
            $processingState = 'failed';
        }

        return [
            'upload' => $uploadProgress,
            'processing' => $processingProgress,
            'overall' => $uploadProgress < 100 ? $uploadProgress : $processingProgress,
            'state' => $processingState
        ];
    }
}
