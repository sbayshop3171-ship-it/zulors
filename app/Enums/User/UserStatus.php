<?php

namespace App\Enums\User;

enum UserStatus: string
{
    case ACTIVE = 'active';
	case BLOCKED = 'blocked';
	case SUSPENDED = 'suspended';
	case ONBOARDING = 'onboarding';
}
