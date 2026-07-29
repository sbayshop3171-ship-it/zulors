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

namespace App\Enums\Product;

use App\Enums\Traits\TriesFromArray;

enum ProductCondition: string
{
	use TriesFromArray;
	
	case USED = 'used';
	case NEW = 'new';
	case ACCEPTABLE = 'acceptable';
	case REFURBISHED = 'refurbished'; 
	case DEFECTIVE = 'defective';

	public function label()
	{
		return match ($this) {
			self::USED => __('labels.condition_labels.used'),
			self::NEW => __('labels.condition_labels.new'),
			self::ACCEPTABLE => __('labels.condition_labels.acceptable'),
			self::REFURBISHED => __('labels.condition_labels.refurbished'),
			self::DEFECTIVE => __('labels.condition_labels.defective'),
		};
	}

	public function emoji(): string
	{
		return match ($this) {
			self::USED => '🔥',
			self::NEW => '🆕',
			self::ACCEPTABLE => '👌',
			self::REFURBISHED => '🔄',
			self::DEFECTIVE => '❌',
		};
	}

	public static function physicalProductConditions()
	{
		return [
			[
				'key' => self::USED->value,
				'value' => __('labels.condition_labels.used')
			],
			[
				'key' => self::NEW->value,
				'value' => __('labels.condition_labels.new')
			],
			[
				'key' => self::ACCEPTABLE->value,
				'value' => __('labels.condition_labels.acceptable')
			],
			[
				'key' => self::REFURBISHED->value,
				'value' => __('labels.condition_labels.refurbished')
			],
			[
				'key' => self::DEFECTIVE->value,
				'value' => __('labels.condition_labels.defective')
			],
		];
	}

	public static function digitalProductConditions()
	{
		return [
			[
				'key' => self::NEW->value,
				'value' => __('labels.condition_labels.new')
			]
		];
	}
}
