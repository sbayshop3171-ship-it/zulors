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

namespace App\Actions\Story;

use App\Models\StoryFrame;
use App\Actions\Media\DeleteMediaAction;

class DeleteStoryFrameAction
{
	private StoryFrame $storyFrame;

	public function __construct(StoryFrame $storyFrame)
	{
		$this->storyFrame = $storyFrame;
	}

	public function execute()
	{
		$storyMedia = $this->storyFrame->media->first();

		if ($storyMedia) {
			(new DeleteMediaAction($storyMedia))->execute();
		}

		$this->storyFrame->views()->delete();

		$this->storyFrame->delete();
	}
}
