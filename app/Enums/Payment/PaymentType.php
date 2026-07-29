<?php

namespace App\Enums\Payment;

enum PaymentType: string
{
	case DEPOSIT = 'deposit';

	public static function getPaymentTypes(): array
	{
		return [
			self::DEPOSIT,
		];
	}

	public function label(): string
	{
		return match ($this) {
			self::DEPOSIT => __('labels.payment_type_labels.deposit'),
		};
	}

	public function emoji(): string
	{
		return match ($this) {
			self::DEPOSIT => '💰',
		};
	}
}
