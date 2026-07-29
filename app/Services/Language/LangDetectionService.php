<?php

namespace App\Services\Language;

use Exception;
use LanguageDetection\Language;

class LangDetectionService
{
	private string $text;

	public function __construct(string $text)
	{
		$this->text = $text;
	}

	public function detect(): string
	{
		try {
			$LD = new Language();

			$detectedLangs = $LD->detect($this->text)->bestResults()->close();
			
			if(empty($detectedLangs)) {
				return 'en';
			}
			
			return array_key_first($detectedLangs);
		}
		catch (Exception $e) {
			return 'en';
		}
	}
}
