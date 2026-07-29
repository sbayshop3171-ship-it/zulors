<?php

namespace App\Enums\Chat;

enum GroupVisibility: string
{
	case PUBLIC = 'public';
	case HIDDEN = 'hidden';
}