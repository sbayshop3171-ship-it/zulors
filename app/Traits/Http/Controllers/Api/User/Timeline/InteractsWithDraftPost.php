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
}
