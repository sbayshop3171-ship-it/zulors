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

namespace App\Http\Controllers\Api\User\Timeline;

use App\Models\PostPoll;
use Illuminate\Support\Arr;
use App\Enums\Post\PostType;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Http\Resources\User\Timeline\PollResource;
use App\Traits\Http\Controllers\Api\User\Timeline\ValidatesPollData;
use App\Traits\Http\Controllers\Api\User\Timeline\InteractsWithDraftPost;

class PostPollController extends Controller
{
    use InteractsWithDraftPost,
        ValidatesPollData,
        SupportsApiResponses;

    public function createPoll()
    {
        $this->fetchOrInitializeDraftPost();
        $this->resetFailedDirectVideoDraftPost();
        $this->resetEmptyAttachmentDraftPost();

        if($this->draftPost->type->isTextified()) {
            if(! $this->draftPost->exists) {
                $this->draftPost->save();
            }

            if($this->draftPost->type->isTextified()) {
                $this->draftPost->type = PostType::POLL;
                $this->draftPost->save();
            }

            $this->draftPost->poll()->create([
                'choices' => [],
                'metadata' => [],
                'votes' => []
            ]);

            return $this->responseSuccess();
        }

        else{
            return $this->responseValidationError([
                'message' => 'Can not attach poll to this type of post.',
                'errors' => [
                    'poll' => [
                        'Can not attach poll to this type of post.'
                    ]
                ]
            ]);
        }
    }

    public function votePoll(Request $request)
    {
        $this->validatePollVote([
            'poll_id' => $request->get('poll_id', null),
            'choice_id' => $request->get('choice_id', null)
        ]);

        $pollId = $request->get('poll_id');
        $choiceIndex = $request->get('choice_id');

        $pollData = PostPoll::where('id', $pollId)->first();

        if(isset($pollData->choices[$choiceIndex])) {

            $pollData->choices[$choiceIndex];
            $myId = me()->id;

            $pollVotes = $pollData->votes;

            $myVote = Arr::first($pollVotes, function($item) use ($myId) {
                return $item['user_id'] === $myId;
            });

            if(empty($myVote)) {
                array_push($pollVotes, [
                    'user_id' => $myId,
                    'choice_id' => $choiceIndex,
                    'times_stamp' => now(),
                    'percentage' => 0,
                    'vote_count' => 0
                ]);
            }

            else{
                $pollVotes = Arr::where($pollVotes, function($item) use ($myId, $choiceIndex) {
                    return ! ($item['user_id'] === $myId && $item['choice_id'] === $choiceIndex);
                });
            }

            $totalVotes = count($pollVotes);

            $pollChoices = collect($pollData->choices)->map(function ($item, $choiceIndex) use ($totalVotes, $pollVotes) {
                $votesCount = count(Arr::where($pollVotes, function($item) use ($choiceIndex) {
                    return $item['choice_id'] === $choiceIndex;
                }));

                $votePercentage = ($totalVotes > 0) ? (($votesCount / $totalVotes) * 100) : 0;

                $item['percentage'] = round($votePercentage, 2);
                $item['vote_count'] = $votesCount;

                return $item;

            })->toArray();

            $pollData->votes = $pollVotes;
            $pollData->choices = $pollChoices;
            $pollData->save();

            return $this->responseSuccess([
                'data' => PollResource::make($pollData->refresh())
            ]);
        }
        else{
            return $this->responseError([
                'message' => 'Can not find poll choice with such Id.',
                'errors' => [
                    'choice_id' => [
                        'Can not find poll choice with such Id.'
                    ]
                ]
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function deletePoll()
    {
        $this->fetchOrInitializeDraftPost();

        if ($this->draftPost->exists) {
            $this->draftPost->delete();
        }

        return $this->responseSuccess();
    }
}
