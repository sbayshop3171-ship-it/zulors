<?php

namespace App\Services\Translation;

use Exception;
use App\Services\Translation\Drivers\Libretranslate;
use App\Services\Translation\Drivers\Interfaces\TranslationDriverInterface;

class TranslationService
{
	private TranslationDriverInterface $driver;

	public function __construct()
	{
		if (config('services.translation.service') === 'libretranslate') {
			$this->driver = new Libretranslate();
		} else {
			throw new Exception('Invalid translation service');
		}
	}

	public function setDriver(TranslationDriverInterface $driver): self
	{
		$this->driver = $driver;

		return $this;
	}

	public function from(string $from): self
	{
		$this->driver->from($from);

		return $this;
	}

	public function to(string $to): self
	{
		$this->driver->to($to);

		return $this;
	}
	
	public function translate(string $text): string
	{
		return $this->driver->translate($text);
	}
}
