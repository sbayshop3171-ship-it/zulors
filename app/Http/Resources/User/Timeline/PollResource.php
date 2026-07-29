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

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PollResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'has_voted' => $this->checkPollIsVoted(),
            'is_expired' => (! empty($this->expires_at)),
            'choices' => $this->getPollChoices(),
            'votes' => $this->votes,
            'voter_users' => $this->getPollVoters(),
            'is_anonymous' => $this->is_anonymous,
            'metadata' => $this->metadata
        ];
    }

    private function getPollVoters(): array
    {
        $voterIds = collect($this->votes)->take(7)->pluck('user_id')->all();

        if(is_array($voterIds)) {
            $voters = User::whereIn('id', $voterIds)->get(['id', 'avatar', 'first_name', 'last_name']);

            return $voters->map(function($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'avatar_url' => $item->avatar_url
                ];
            })->toArray();
        }

        return [];
    }

    private function getPollChoices(): array
    {
        if(auth_check()) {
            return collect($this->choices)->map(function($item, $choiceIndex) {

                $item['has_voted_choice'] = ! empty(Arr::first($this->votes, function ($value) use ($choiceIndex) {
                    return ($value['user_id'] === me()->id && $value['choice_id'] === $choiceIndex);
                }));

                return $item;

            })->toArray();
        }

        return $this->choices;
    }

    private function checkPollIsVoted(): bool
    {
        if(auth_check()) {
            return ! empty(Arr::first($this->votes, function ($value) {
                return $value['user_id'] === me()->id;
            }));
        }

        return false;
    }
}
