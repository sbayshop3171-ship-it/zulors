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

namespace App\Enums\Wallet;

enum TransactionStatus: string
{
	case PENDING = 'pending';
	case COMPLETED = 'completed';
	case FAILED = 'failed';
	case CANCELLED = 'cancelled';

	public static function values(): array
	{
		return array_column(self::cases(), 'value');
	}

	public function label(): string
	{
		return match($this) {
			self::PENDING => __('labels.transaction_status_labels.pending'),
			self::COMPLETED => __('labels.transaction_status_labels.completed'),
			self::FAILED => __('labels.transaction_status_labels.failed'),
			self::CANCELLED => __('labels.transaction_status_labels.cancelled')
		};
	}
}
