<?php

namespace App\Enums\Ad;

enum AdApproval: string
{
    case APPROVED = 'approved';
    case PENDING = 'pending';
    case REJECTED = 'rejected';

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }

    public function emoji(): string
    {
        return match ($this) {
            self::APPROVED => '✅',
            self::PENDING => '⏳',
            self::REJECTED => '❌',
        };
    }

    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }

    public function label(): string
	{
		return match ($this) {
			self::APPROVED => __('labels.approval_labels.approved'),
			self::PENDING => __('labels.approval_labels.pending'),
			self::REJECTED => __('labels.approval_labels.rejected'),
		};
	}
}
