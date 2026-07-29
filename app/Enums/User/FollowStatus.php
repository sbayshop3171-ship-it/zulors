<?php
/*
|--------------------------------------------------------------------------
| Zulors - The Zulors Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  Zulors. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Enums\User;

enum FollowStatus: string
{
	case REQUESTED = 'requested';
	case FOLLOWING = 'following';
	case REJECTED = 'rejected';
	case BLOCKED = 'blocked';

	public function isRequested(): bool
	{
		return $this === self::REQUESTED;
	}

	public function isFollowing(): bool
	{
		return $this === self::FOLLOWING;
	}
}
