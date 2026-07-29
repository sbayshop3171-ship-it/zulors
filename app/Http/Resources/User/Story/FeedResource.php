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

namespace App\Http\Resources\User\Story;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = ($this->user_id == me()->id);

        return [
            'story_uuid' => $this->story_uuid,
            'relations' => [
                'user' => [
                    'name' => $this->user->name,
                    'avatar_url' => $this->user->avatar_url
                ]
            ],
            'is_seen' => $this->checkIfStorySeen(),
            'is_owner' => $isOwner
        ];
    }

    private function checkIfStorySeen()
    {
        return $this->frames->some(function($frame) {
            return $frame->views->contains('user_id', me()->id);
        });
    }
}
