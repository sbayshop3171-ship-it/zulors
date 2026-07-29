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

namespace App\Actions\Chat;

use App\Models\HiddenMessage;
use Illuminate\Database\Eloquent\Collection;

class MessagesLocalDeleteAction
{
	private Collection $messagesList;

	public function __construct(Collection $messagesList) {
		$this->messagesList = $messagesList;
	}

	public function execute()
	{
		HiddenMessage::insert($this->messagesList->map(function($item) {
			return [
				'message_id' => $item->id,
				'chat_id' => $item->chat_id,
				'user_id' => me()->id
			];
		})->toArray());
	}
}
