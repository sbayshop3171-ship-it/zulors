<?php

namespace App\Traits\Http\Controllers\Api\User\Story;

use App\Rules\X\XRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

trait ValidatesStoryMedia
{
	private function validateStoryImage(UploadedFile $mediaFile) {
        $validator = Validator::make([
            'media_file' => $mediaFile
        ], [
            'media_file' => [
                'required',
                'image',
                XRule::join('mimes', config('story.validation.image.mimes')),
                XRule::join('mimetypes', config('story.validation.image.mimetypes')),
                XRule::join('max', config('story.validation.image.max'))
            ]
        ]);
    
        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }
    }

    private function validateStoryVideo(UploadedFile $mediaFile) {
        $validator = Validator::make([
            'media_file' => $mediaFile
        ], [
            'media_file' => [
                'required',
                'file',
                XRule::join('mimes', config('story.validation.video.mimes')),
                XRule::join('mimetypes', config('story.validation.video.mimetypes')),
                XRule::join('max', config('story.validation.video.max'))
            ]
        ]);
    
        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }
    }
}
