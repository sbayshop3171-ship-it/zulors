<?php

namespace App\Enums\Wallet;

enum CashoutStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }

    public function label(): string
    {
        return match($this) {
            self::PENDING => __('labels.status_labels.pending'),
            self::PAID => __('labels.status_labels.paid'),
            self::REJECTED => __('labels.status_labels.rejected'),
            self::CANCELLED => __('labels.status_labels.cancelled'),
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::PENDING => 'default',
            self::PAID => 'success',
            self::REJECTED => 'danger',
            self::CANCELLED => 'warning',
        };
    }
}
