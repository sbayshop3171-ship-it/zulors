<?php

namespace App\Telegram;

use DefStudio\Telegraph\Handlers\WebhookHandler;

class BotHandler extends WebhookHandler
{
	public function hello()
	{
		$this->reply("Hello. Hren ");
	}
}
