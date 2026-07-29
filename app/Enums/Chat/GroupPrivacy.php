<?php

namespace App\Enums\Chat;

enum GroupPrivacy: string
{
	case OPEN = 'open';
	case REQUEST = 'request';
}
