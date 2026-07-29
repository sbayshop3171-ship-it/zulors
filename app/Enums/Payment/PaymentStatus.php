<?php

namespace App\Enums\Payment;

enum PaymentStatus: string
{
	case PENDING = 'pending';
	case COMPLETED = 'completed';
	case FAILED = 'failed';
	case EXPIRED = 'expired';
	case CANCELLED = 'cancelled';
	case REFUNDED = 'refunded';
	case CHARGEBACK = 'chargeback';
	case CHARGEBACK_REVERSED = 'chargeback_reversed';

	public function isPending(): bool
	{
		return $this === self::PENDING;
	}

	public function isCompleted(): bool
	{
		return $this === self::COMPLETED;
	}

	public function label(): string
	{
		return match ($this) {
			self::PENDING => __('labels.payment_status_labels.pending'),
			self::COMPLETED => __('labels.payment_status_labels.completed'),
			self::FAILED => __('labels.payment_status_labels.failed'),
			self::EXPIRED => __('labels.payment_status_labels.expired'),
			self::CANCELLED => __('labels.payment_status_labels.cancelled'),
			self::REFUNDED => __('labels.payment_status_labels.refunded'),
			self::CHARGEBACK => __('labels.payment_status_labels.chargeback'),
			self::CHARGEBACK_REVERSED => __('labels.payment_status_labels.chargeback_reversed'),
		};
	}

	public function emoji(): string
	{
		return match ($this) {
			self::PENDING => '⏳',
			self::COMPLETED => '✅',
			self::FAILED => '❌',
			self::EXPIRED => '🪫',
			self::CANCELLED => '✋',
			self::REFUNDED => '💸',
			self::CHARGEBACK => '💸',
			self::CHARGEBACK_REVERSED => '💸',
		};
	}
}
