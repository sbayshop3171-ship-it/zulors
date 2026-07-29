<?php

namespace App\Enums\Chat;

enum ChatInviteStatus: string
{
	case PENDING = 'pending';
	case DECLINED = 'declined';
}
