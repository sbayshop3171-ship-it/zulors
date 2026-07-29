<?php

namespace App\Enums\Chat;

enum GroupStatus: string
{
	case DRAFT = 'draft';
	case ACTIVE = 'active';

	public function isDraft(): bool
    {
        return $this == self::DRAFT;
    }
}
