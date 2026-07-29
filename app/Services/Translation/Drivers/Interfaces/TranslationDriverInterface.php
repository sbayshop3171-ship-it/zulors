<?php

namespace App\Services\Translation\Drivers\Interfaces;

interface TranslationDriverInterface
{
	public function from(string $text): self;
	public function to(string $language): self;
	public function translate(string $text): string;
}
