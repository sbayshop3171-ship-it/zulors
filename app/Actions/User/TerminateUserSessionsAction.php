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

namespace App\Actions\User;

class TerminateUserSessionsAction
{
	private $excludeCurrent = true;

	public function withCurrent()
	{
		$this->excludeCurrent = false;

		return $this;
	}

	public function execute()
	{
		me()->devices()->when($this->excludeCurrent, function ($query) { 
			return $query->where('session_id', '!=', session()->getId());
		})->update([
            'is_terminated' => true
        ]);
	}
}