<?php

namespace App\Traits\Http\Controllers\Api\User\Timeline;

use App\Rules\X\XRule;
use Illuminate\Support\Facades\Validator;

trait ValidatesPostImage
{
    private function validatePostImage(array $data) {
        $validator = Validator::make($data, [
            'image' => [
                'required',
                'image',
                XRule::join('mimes', config('post.validation.image.mimes')),
                XRule::join('mimetypes', config('post.validation.image.mimetypes')),
                XRule::join('max', config('post.validation.image.max'))
            ]
        ]);
    
        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }
    }
}
