<?php

namespace App\Notifications\Traits;

trait HasUserActor
{
	public function getUserActor(): array
	{
		$actor = me();

		return [
			'id' => $actor->id,
			'name' => $actor->name,
			'avatar_url' => $actor->avatar_url,
			'username' => $actor->username,
			'type' => 'user',
			'verified' => $actor->isVerified()
		];
	}
}
