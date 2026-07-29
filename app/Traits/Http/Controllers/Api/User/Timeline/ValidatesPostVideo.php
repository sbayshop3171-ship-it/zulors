<?php

namespace App\Traits\Http\Controllers\Api\User\Timeline;

use App\Rules\X\XRule;
use Illuminate\Support\Facades\Validator;

trait ValidatesPostVideo
{
    private function validatePostVideo(array $data) {
        $validator = Validator::make($data, [
            'video' => [
                'required',
                'file',
                XRule::join('mimes', config('post.validation.video.mimes')),
                XRule::join('mimetypes', config('post.validation.video.mimetypes')),
                XRule::join('max', config('post.validation.video.max'))
            ]
        ]);
    
        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }
    }
}
