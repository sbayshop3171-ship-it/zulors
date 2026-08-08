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
use App\Http\Resources\User\User\UserPreviewResource;

class ViewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'relations' => [
                'user' => UserPreviewResource::make($this->user)
            ],
            'activity' => [
                'has_liked' => (bool) $this->getAttribute('has_liked_story'),
            ],
            'date' => [
                'time_ago' => $this->viewed_at->getTimeAgo(),
                'time' => $this->viewed_at->getTime(),
                'is_today' => $this->viewed_at->isToday()
            ]
        ];
    }
}
