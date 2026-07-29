<?php

namespace App\Traits\Http\Controllers\Api\User\Timeline;

use App\Rules\X\XRule;
use App\Enums\Post\PostType;
use Illuminate\Support\Facades\Validator;

trait ValidatesPostData
{
    private function validatePostData(array $data) {
        $validator = Validator::make($data, [
            'content' => [
                XRule::requiredOrNullable(($this->draftPost->type->isTextified() || $this->draftPost->type->isPoll())), 
                XRule::join('max', config('post.validation.content.max')), 
                XRule::join('min', config('post.validation.content.min'))
            ]
        ], messages: [
            'content.required' => match ($this->draftPost->type) {
                PostType::TEXT => __('post.validation.add_post_text'),
                PostType::POLL => __('post.validation.add_poll_text'),
                default => null
            }
        ]);
    
        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }
    }
}
