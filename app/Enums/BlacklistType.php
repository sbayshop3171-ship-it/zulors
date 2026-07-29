<?php
/*
|--------------------------------------------------------------------------
| Zulors - The Zulors Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  Zulors. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Enums;

enum BlacklistType: string
{
	case IP = 'ip_address';
	case EMAIL = 'email';
	case PHONE = 'phone';
	case USERNAME = 'username';

	public function label(): string
	{
		return match ($this) {
			self::IP => 'IP Address',
			self::EMAIL => 'Email',
			self::PHONE => 'Phone',
			self::USERNAME => 'Username',
		};
	}

	public function emoji(): string
	{
		return match ($this) {
			self::IP => '🌐',
			self::EMAIL => '📧',
			self::PHONE => '📱',
			self::USERNAME => '👤',
		};
	}
}
