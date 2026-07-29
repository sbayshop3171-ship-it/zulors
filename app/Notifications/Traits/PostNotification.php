<?php

namespace App\Notifications\Traits;

trait PostNotification
{
    private function getPostPreviewLQIPBase64(): string|null
    {
        if($this->postData->type->isImage()) {
            return $this->postData->preview_lqip_base64;
        }

        return null;
    }
}
