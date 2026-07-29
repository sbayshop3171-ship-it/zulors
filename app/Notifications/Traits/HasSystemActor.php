<?php

namespace App\Notifications\Traits;

trait HasSystemActor
{
	protected function getSystemActor(): array
	{
		return [
			'id' => 0,
			'name' => config('app.name'),
			'avatar_url' => asset('assets/avatars/app-avatar.png'),
			'username' => null,
			'type' => 'system',
			'verified' => true
		];
	}
}
