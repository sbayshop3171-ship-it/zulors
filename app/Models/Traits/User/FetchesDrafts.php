<?php

namespace App\Models\Traits\User;

use App\Enums\Post\PostStatus;

trait FetchesDrafts
{
    public function getDraftPost():mixed
    {
        $draftPost = $this->posts()->where('status', PostStatus::DRAFT)->first();

        return (empty($draftPost)) ? null : $draftPost;
    }
}
