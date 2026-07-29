<?php

namespace App\Enums\User;

enum UserType: string
{
	case READER = 'reader';
	case AUTHOR = 'author';

	public function label(): string
	{
		return match ($this) {
			self::READER => __('labels.reader'),
			self::AUTHOR => __('labels.author'),
		};
	}

	public function emoji(): string
	{
		return match ($this) {
			self::READER => '📚',
			self::AUTHOR => '⭐',
		};
	}
}
