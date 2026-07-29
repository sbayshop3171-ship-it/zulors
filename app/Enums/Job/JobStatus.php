<?php

namespace App\Enums\Job;

enum JobStatus: string
{
	case DRAFT = 'draft';
	case ACTIVE = 'active';
	case INACTIVE = 'inactive';

	public function label(): string
	{
		return match ($this) {
			self::DRAFT => __('labels.status_labels.draft'),
			self::ACTIVE => __('labels.status_labels.active'),
			self::INACTIVE => __('labels.status_labels.inactive')
		};
	}

	public function emoji(): string
	{
		return match ($this) {
			self::DRAFT => '🗒️',
			self::ACTIVE => '🚀',
			self::INACTIVE => '🛑'
		};
	}

	public function isActive(): bool
	{
		return $this === self::ACTIVE;
	}

	public function badgeVariant(): string
	{
		return match ($this) {
			self::ACTIVE => 'success',
			self::INACTIVE => 'default',
			self::DRAFT => 'warning',
		};
	}
}
