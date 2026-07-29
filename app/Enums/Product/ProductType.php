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

enum ProductType: string
{
	use TriesFromArray;

	case PHYSICAL = 'physical';
	case DIGITAL = 'digital';
	case SERVICE = 'service';
	case SUBSCRIPTION = 'subscription';
	case MEMBERSHIP = 'membership';
	case EVENT_TICKET = 'event_ticket';
	case GIFT_CARD = 'gift_card';

	public function label(): string
	{
		return match ($this) {
			self::PHYSICAL => __('business/labels.type_labels.physical'),
			self::DIGITAL => __('business/labels.type_labels.digital'),
			self::SERVICE => __('business/labels.type_labels.service'),
			self::SUBSCRIPTION => __('business/labels.type_labels.subscription'),
			self::MEMBERSHIP => __('business/labels.type_labels.membership'),
			self::EVENT_TICKET => __('business/labels.type_labels.event_ticket'),
			self::GIFT_CARD => __('business/labels.type_labels.gift_card'),
		};
	}

	public static function types()
	{
		return [
			[
				'key' => self::PHYSICAL->value,
				'value' => __('business/labels.type_labels.physical')
			],
			[
				'key' => self::DIGITAL->value,
				'value' => __('business/labels.type_labels.digital')
			],
			[
				'key' => self::SERVICE->value,
				'value' => __('business/labels.type_labels.service')
			],
			[
				'key' => self::SUBSCRIPTION->value,
				'value' => __('business/labels.type_labels.subscription')
			],
			[
				'key' => self::MEMBERSHIP->value,
				'value' => __('business/labels.type_labels.membership')
			],
			[
				'key' => self::EVENT_TICKET->value,
				'value' => __('business/labels.type_labels.event_ticket')
			],
			[
				'key' => self::GIFT_CARD->value,
				'value' => __('business/labels.type_labels.gift_card')
			]
		];
	}
}
