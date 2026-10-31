<?php

namespace App\Http\Controllers\Api\User\Tip;

use App\Actions\Recommend\FetchFollowRecommendation;
use App\Http\Controllers\Controller;
use App\Rules\X\XRule;
use App\Services\Relations\FollowService;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Throwable;

class TipController extends Controller
{
    use SupportsApiResponses;

    private $me;

    public function __construct() {
        $this->me = me();
    }

    public function updateBio(Request $request)
    {
        $skip = $request->get('skip', false);
        $bio = null;

        if(empty($skip)) {
            $request->validate([
                'bio' => ['required', 'string', XRule::join('min', config('user.validation.bio.min')), XRule::join('max', config('user.validation.bio.max'))]
            ]);

            $bio = $request->get('bio', '');
        }

        $this->me->update([
            'bio' => (empty($bio)) ? '' : $bio,
            'tips' => Arr::except($this->me->tips, 'onboarding_bio')
        ]);

        return $this->responseSuccess([
            'data' => null
        ]);
    }

    public function updateAvatar(Request $request)
    {
        if($request->hasFile('image')) {
            abort(response()->json(['message' => 'Profile photos must use the signed direct R2 upload flow.', 'code' => 'direct_upload_required'], 409));
        }
        $skip = $request->get('skip', false);

        $updateData = [
            'tips' => Arr::except($this->me->tips, 'onboarding_avatar')
        ];

        if(empty($skip)) {
            $request->validate([
                'image' => ['required', 'image', config('user.validation.avatar.mimes'), config('user.validation.avatar.max')]
            ]);

            $avatarFilePath = $request->image->store('uploads/users/avatars', static_storage_disk());

            if(! empty($avatarFilePath)) {
                $updateData['avatar'] = $avatarFilePath;
                $updateData['avatar_disk'] = static_storage_disk();
            }
        }

        $this->me->update($updateData);

        return $this->responseSuccess([
            'data' => [
                'avatar_url' => (empty($skip) ? $this->me->avatar_url : null)
            ]
        ]);
    }

    public function followRecommendedUsers(Request $request)
    {
        $skip = $request->get('skip', false);

        if(empty($skip)) {
            $followRecommendation = (new FetchFollowRecommendation())->handle();

            if($followRecommendation->isNotEmpty()) {
                try {
                    $followRecommendation->each(function($userData) {
                        (new FollowService($this->me, $userData))->follow();
                    });
                } catch (Throwable $th) {
                    logger()->error('Error following recommended users on onboard.', [
                        'error' => $th->getMessage()
                    ]);
                }
            }
        }

        $this->me->update([
            'tips' => Arr::except($this->me->tips, 'onboarding_follow')
        ]);

        return $this->responseSuccess([
            'data' => null
        ]);
    }
}
