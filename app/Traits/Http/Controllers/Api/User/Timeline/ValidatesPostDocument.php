<?php

namespace App\Traits\Http\Controllers\Api\User\Timeline;

use App\Rules\X\XRule;
use Illuminate\Support\Facades\Validator;

trait ValidatesPostDocument
{
    private function validatePostDocument(array $data) {
        $validator = Validator::make($data, [
            'document' => [
                'required',
                'file',
                XRule::join('mimes', config('post.validation.document.mimes')),
                XRule::join('mimetypes', config('post.validation.document.mimetypes')),
                XRule::join('max', config('post.validation.document.max'))
            ]
        ]);
    
        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }
    }
}
