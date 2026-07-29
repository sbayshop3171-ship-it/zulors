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

enum TransactionType: string
{
	case DEPOSIT = 'deposit';
	case WITHDRAW = 'withdraw';
	case TRANSFER = 'transfer';
	case PAYMENT = 'payment';
	case REFUND = 'refund';
	case ADVERTISING = 'advertising';

	public static function values(): array
	{
		return array_column(self::cases(), 'value');
	}

	public function label(): string
	{
		return match ($this) {
			self::DEPOSIT => __('labels.transaction_type_labels.deposit'),
			self::WITHDRAW => __('labels.transaction_type_labels.withdraw'),
			self::TRANSFER => __('labels.transaction_type_labels.transfer'),
			self::PAYMENT => __('labels.transaction_type_labels.payment'),
			self::REFUND => __('labels.transaction_type_labels.refund'),
			self::ADVERTISING => __('labels.transaction_type_labels.advertising'),
		};
	}	
}
