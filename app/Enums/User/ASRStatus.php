<?php

namespace App\Enums\User;

enum ASRStatus: string
{
	case PENDING = 'pending';
	case REJECTED = 'rejected';

	public function label(): string
	{
		return match ($this) {
			self::PENDING => __('labels.approval_labels.pending'),
			self::REJECTED => __('labels.approval_labels.rejected'),
		};
	}

	public function emoji(): string
	{
		return match ($this) {
			self::PENDING => '⏳',
			self::REJECTED => '❌',
		};
	}
}
