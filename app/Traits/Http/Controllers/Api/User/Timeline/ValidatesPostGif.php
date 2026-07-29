<?php

namespace App\Traits\Http\Controllers\Api\User\Timeline;

use App\MediaApi\Giphy\Giphy;
use Illuminate\Support\Facades\Validator;

trait ValidatesPostGif
{
    private function validatePostGif(array $data) {
        $validator = Validator::make($data, [
            'id' => ['required', 'string']
        ]);
    
        if ($validator->fails() || !Giphy::validateGifId($data['id'])) {

            $validator->errors()->add('id', 'The gif id is not valid.');
            
            $this->throwValidationError($validator);
        }
    }
}
