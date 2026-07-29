<?php

namespace App\Actions\Chat;

use App\Actions\Media\DeleteMediaAction;
use App\Models\Message;

class DeleteMessageAction
{
	private Message $messageData;

	public function __construct(Message $messageData) {
		$this->messageData = $messageData;
	}

	public function execute() {
		$this->messageData->linkSnapshot()->delete();

        if($this->messageData->media) {
            (new DeleteMediaAction($this->messageData->media))->execute();
        }

		$this->messageData->reactions()->delete();

		$this->messageData->delete();
	}
}
