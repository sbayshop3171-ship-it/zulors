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

namespace App\Constants;

/*
 * Relationship Attributes:
 *
 * - 'following': Indicates whether the authenticated user is currently following the specified account.
 *
 * - 'followed_by': Shows if the specified account is following the authenticated user.
 *
 * - 'blocking': Specifies whether the authenticated user has blocked the specified account.
 *
 * - 'blocked_by': Indicates if the specified account has blocked the authenticated user.
 *
 * - 'muting': Denotes whether the authenticated user has muted the specified account, meaning the user's posts won't appear in their timeline.
 *
 * - 'muting_notifications': Shows if the authenticated user has muted notifications from the specified account, preventing notifications from their activities.
 *
 * - 'requested': Indicates that the authenticated user has sent a follow request to the specified account, pending approval.
 *
 * - 'requested_by': Shows if the specified account has sent a follow request to the authenticated user, awaiting their approval.
 */

class Relationship
{
	public const FOLLOWING = 'following';
	public const FOLLOWED_BY = 'followed_by';
	public const BLOCKING = 'blocking';
	public const BLOCKED_BY = 'blocked_by';
	public const MUTING = 'muting';
	public const REQUESTED = 'requested';

	public const FOLLOW_GROUP = 'follow';
	public const BLOCK_GROUP = 'block';
	public const MUTING_GROUP = 'muting';
}
