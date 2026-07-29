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

namespace App\Http\Controllers\Api\User\Relations;

use App\Constants\Relationship;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\User\Follows\FollowAcceptNotification;
use App\Notifications\User\Follows\FollowRequestNotification;
use App\Notifications\User\Follows\NewFollowerNotification;
use App\Services\Relations\FollowService;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;

class FollowsController extends Controller
{
    use SupportsApiResponses;

    public function followUser(Request $request)
    {
        $userId = $request->integer('id', 0);

        $userData = User::activeById($userId)->first();

        if($userData) {
            $followService = new FollowService(me(), $userData);

            // Check if Me is Following or Follow Requested. Unfollow if true.
            if($followService->isFollowing() || $followService->followRequested()) {
                $followService->unfollow();
            }
            else {
                if($followService->canFollow()) {
                    $follow = $followService->follow();

                    if($follow->status->isRequested()) {
                        $userData->notify(new FollowRequestNotification());
                    }
                    else if($follow->status->isFollowing()) {
                        $userData->notify(new NewFollowerNotification());
                    }
                }
            }

            return $this->responseSuccess([
                'data' => [
                    'relationship' => [
                        Relationship::FOLLOW_GROUP => [
                            Relationship::FOLLOWING => $followService->isFollowing(),
                            Relationship::REQUESTED => $followService->followRequested()
                        ]
                    ]
                ]
            ]);
        }

        return $this->responseResourceNotFoundError('User', $userId);
    }

    public function acceptFollowRequest(Request $request)
    {
        $userId = $request->integer('id', 0);

        $userData = User::activeById($userId)->first();

        if($userData) {
            $followService = new FollowService($userData, me());
            $followService->accept();

            $userData->notify(new FollowAcceptNotification());

            return $this->responseSuccess([
                'data' => null
            ]);
        }

        return $this->responseResourceNotFoundError('User', $userId);
    }
}
