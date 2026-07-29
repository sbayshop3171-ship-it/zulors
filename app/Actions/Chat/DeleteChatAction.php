<?php

namespace App\Actions\Chat;

use App\Models\Chat;
use App\Actions\Chat\DeleteMessageAction;

class DeleteChatAction
{
	private Chat $chatData;

	public function __construct(Chat $chatData)
	{
		$this->chatData = $chatData;
	}

	public function execute()
	{
		$this->chatData->messages()->chunk(500, function ($messages) {
			foreach ($messages as $messageData) {
				(new DeleteMessageAction($messageData))->execute();
			}
		});

		$this->chatData->participants()->delete();

		$this->chatData->delete();
	}
}
