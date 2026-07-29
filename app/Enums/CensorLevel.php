<?php

namespace App\Enums;

enum CensorLevel: string
{
	case BANNED = 'banned';
	case WARNING = 'warning';
	case REPLACED = 'replaced';
}
