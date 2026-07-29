<?php

namespace App\Http\Controllers\Api\User\Translator;

use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Services\Translation\TranslationService;

class TranslatorController extends Controller
{
    use SupportsApiResponses;

    private $translationService;

    public function __construct(TranslationService $translationService)
    {
        $this->translationService = $translationService;
    }
    
    public function translate(Request $request)
    {
        $request->validate([
            'text' => ['required', 'string'],
        ]);

        try {
            $translatedText = $this->translationService->translate($request->text);

            return $this->responseSuccess([
                'data' => [
                    'translated_text' => $translatedText
                ]
            ]);
        } 
        
        catch (Exception $e) {
            return $this->responseError([
                'message' => $e->getMessage(),
                'errors' => [
                    'text' => [$e->getMessage()]
                ]
            ]);
        }
    }
}
