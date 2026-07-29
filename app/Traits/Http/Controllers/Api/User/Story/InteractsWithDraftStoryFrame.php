<?php

namespace App\Traits\Http\Controllers\Api\User\Story;

use App\Enums\Story\StoryStatus;
use Illuminate\Support\Str;

trait InteractsWithDraftStoryFrame
{
	private $draftStoryFrame;

    private function fetchOrInitializeDraftStoryFrame()
    {
        $userStory = me()->story;

        if(empty($userStory)) {
            $userStory = me()->story()->create([
                'story_uuid' => Str::uuid()
            ]);
        }
        
        $this->draftStoryFrame = $userStory->frames()->where('status', StoryStatus::DRAFT)->first();

        if(empty($this->draftStoryFrame)) {
            $this->draftStoryFrame = $userStory->frames()->create([
                'status' => StoryStatus::DRAFT,
                'meta' => []
            ]);
        }
    }
}
