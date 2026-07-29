<?php

namespace App\Services\Translation\Drivers;

use Exception;
use Illuminate\Support\Facades\Http;
use App\Services\Translation\Drivers\Interfaces\TranslationDriverInterface;

class Libretranslate implements TranslationDriverInterface
{
	private string $from = 'auto';
	private string $to = 'en';

    public function from(string $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function to(string $to): self
    {
        $this->to = $to;

        return $this;
    }
    
    public function translate(string $text): string
    {
		try {
			$response = Http::post(config('services.translation.api_url') . '/translate', [
				'q' => $text,
				'source' => $this->from,
				'target' => $this->to,
			]);
            
            $responseData = $response->json();

            if (isset($responseData['error'])) {
                throw new Exception($responseData['error']);
            }

			return (string) $responseData['translatedText'];
		} catch (Exception $e) {
			throw new Exception('Failed to translate text: ' . $e->getMessage());
		}
    }
}
