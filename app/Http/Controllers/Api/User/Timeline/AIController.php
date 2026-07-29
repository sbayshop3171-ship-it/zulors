<?php

namespace App\Http\Controllers\Api\User\Timeline;

use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;
use Exception;
use Illuminate\Http\Response;

class AIController extends Controller
{
    use SupportsApiResponses;

    public function greetingMessage()
    {
        try {
            return $this->responseSuccess([
                'data' => null
            ]);
        } catch (Exception $e) {

            $errorMessage = (app()->environment('production')) ? __('ai.greeting_message.error') : $e->getMessage();

            return $this->responseError([
                'message' => $errorMessage,
                'errors' => [
                    'message' => [$errorMessage]
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
