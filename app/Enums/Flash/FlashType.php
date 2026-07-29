<?php

namespace App\Enums\Flash;

enum FlashType: string
{
	case SUCCESS = 'success';
	case ERROR = 'error';

	public function isSuccess(): bool
	{
		return $this === self::SUCCESS;
	}

	public function isError(): bool
	{
		return $this === self::ERROR;
	}
}
