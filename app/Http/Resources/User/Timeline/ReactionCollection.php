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

namespace App\Http\Resources\User\Timeline;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ReactionCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->map(function($item) {
            $hasReacted = $this->checkIfReactionMade($item->users);

            $reactionData = [
                'unified_id' => $item->unified_id,
                'image_url' => reaction_image_url($item->unified_id),
                'native_symbol' => null,
                'total' => $item->reactions_count,
                'has_reacted' => $hasReacted
            ];

            return $reactionData;
        })->toArray();
    }

    private function checkIfReactionMade(array $usersList): bool
    {
        if(auth_check()) {
            return in_array(me()->id, $usersList);
        }

        return false;
    }
}
