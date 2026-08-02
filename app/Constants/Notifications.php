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

class Notifications
{
	public const FOLLOWED_REQUESTED = 'user.followed-requested';
	public const FOLLOWED = 'user.followed';
	public const FOLLOW_ACCEPTED = 'user.follow-accepted';


	public const POST_COMMENTED = 'post.commented';
	public const POST_MENTIONED = 'post.mentioned';
	public const POST_REACTED = 'post.reacted';

	public const COMMENT_REACTED = 'comment.reacted';
	public const COMMENT_MENTIONED = 'comment.mentioned';

	public const STORY_MENTIONED = 'story.mentioned';

	public const CHAT_MESSAGE_RECEIVED = 'chat.message-received';

	// Important notifications
		public const ACCOUNT_LINKED = 'important.account-linked';
		public const WALLET_DEPOSIT = 'important.wallet-deposit';
		public const PAYMENT_RECEIVED = 'important.payment-received';
		public const PRODUCT_APPROVED = 'important.product-approved';
		public const PRODUCT_REJECTED = 'important.product-rejected';
		public const JOB_APPROVED = 'important.job-approved';
		public const JOB_REJECTED = 'important.job-rejected';
	
	public static function importantTypes(): array
	{
		return [
				self::ACCOUNT_LINKED,
				self::WALLET_DEPOSIT,
				self::PAYMENT_RECEIVED,
				self::PRODUCT_APPROVED,
				self::PRODUCT_REJECTED,
				self::JOB_APPROVED,
				self::JOB_REJECTED
			];
	}

	public static function mentionableTypes(): array
	{
		return [
			self::POST_MENTIONED,
			self::COMMENT_MENTIONED,
			self::STORY_MENTIONED
		];
	}
}
