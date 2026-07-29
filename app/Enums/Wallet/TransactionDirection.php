<?php

namespace App\Enums\Wallet;

enum TransactionDirection: string
{
	case INCOMING = 'incoming';
	case OUTGOING = 'outgoing';

	public function isIncoming(): bool
	{
		return $this === self::INCOMING;
	}
}
