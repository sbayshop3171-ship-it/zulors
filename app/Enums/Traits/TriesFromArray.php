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

namespace App\Enums\Traits;

trait TriesFromArray
{
	public static function tryFromArray(array $values)
	{
		$types = array_map(function($value) {
			return self::tryFrom($value);
		}, $values);

		return array_filter($types, function($value) {
			return (! empty($value));
		});
	}
}
