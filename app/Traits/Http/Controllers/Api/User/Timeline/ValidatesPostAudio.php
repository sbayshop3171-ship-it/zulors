<?php

namespace App\Traits\Http\Controllers\Api\User\Timeline;

use App\Rules\X\XRule;
use Illuminate\Support\Facades\Validator;

trait ValidatesPostAudio
{
    private function validatePostAudio(array $data) {
        $validator = Validator::make($data, [
            'audio' => [
                'required',
                'file',
                XRule::join('mimes', config('post.validation.audio.mimes')),
                XRule::join('mimetypes', config('post.validation.audio.mimetypes')),
                XRule::join('max', config('post.validation.audio.max'))
            ]
        ]);
    
        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }
    }
}
