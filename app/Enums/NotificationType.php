<?php

namespace App\Enums;

enum NotificationType: string
{
	case EMAIL = 'email';
	case PUSH = 'push';
	case TELEGRAM = 'telegram';
}
