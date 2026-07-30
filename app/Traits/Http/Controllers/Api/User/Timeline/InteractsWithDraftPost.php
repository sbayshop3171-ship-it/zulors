<?php

namespace App\Traits\Http\Controllers\Api\User\Timeline;

use App\Enums\Post\PostType;
use App\Enums\Post\PostStatus;

trait InteractsWithDraftPost
{
    private $draftPost;

    private function fetchOrInitializeDraftPost()
    {
        if($this->draftPost) {
            return $this->draftPost;
        }
        else{
            $this->draftPost = me()->getDraftPost();
    
            if(empty($this->draftPost)) {
                $this->draftPost = me()->posts()->make([
                    'type' => PostType::TEXT,
                    'status' => PostStatus::DRAFT
                ]);
            }
        }

        return $this->draftPost;
    }

    private function resetEmptyAttachmentDraftPost(): void
    {
        if(! $this->draftPost?->exists || $this->draftPost->type->isTextified()) {
            return;
        }

        if($this->draftPost->media()->exists() || $this->draftPost->poll()->exists()) {
            return;
        }

        $this->draftPost->type = PostType::TEXT;
        $this->draftPost->save();
    }
}
