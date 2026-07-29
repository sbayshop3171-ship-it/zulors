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

namespace App\Http\Controllers\Api\User\Profile;

use App\Enums\Post\PostType;
use App\Enums\User\FollowStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\People\PeopleCollection;
use App\Http\Resources\User\Profile\ProfileResource;
use App\Http\Resources\User\Timeline\TimelineCollection;
use App\Models\User;
use App\Services\Relations\BlockService;
use App\Traits\Http\Api\SupportsApiResponses;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    use SupportsApiResponses;

    public function getProfileData(Request $request)
    {
        $validator = Validator::make([
            'id' => $request->get('id', null)
        ], [
            'id' => ['required', 'regex:/^[a-zA-Z0-9._]+$/']
        ]);

        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }

        $username = $request->get('id');

        $profileData = User::activeByUsername($username)->first();

        if($profileData) {
            return $this->responseSuccess([
                'data' => ProfileResource::make($profileData)
            ]);
        }

        else{
            return $this->responseResourceNotFoundError('User', $username);
        }
    }

    public function getProfileDetails(Request $request)
    {
        $validator = Validator::make([
            'id' => $request->integer('id', 0)
        ], [
            'id' => ['required', 'integer', 'min:1']
        ]);

        $profileId = $request->integer('id', 0);

        if($validator->fails()) {
            return $this->throwValidationError($validator);
        }

        else {
            $profileData = User::activeById($profileId)->first();

            if($profileData) {
                $blockService = new BlockService(me(), $profileData);
                $isBlocked = $blockService->blockedAny();

                if($isBlocked) {
                    return $this->responseSuccess([
                        'data' => null
                    ]);
                }

                $profileDetails = [
                    'info' => [
                        'join_date' => $profileData->getCreatedAt()->getIso(),
                        'gender' => match ($profileData->gender) {
                            'male' => __('labels.male'),
                            'female' => __('labels.female'),
                            'not-specified' => __('labels.not_specified')
                        },
                        'last_active' => $profileData->getLastActive()->getTimeAgo()
                    ],
                    'contacts' => [

                    ],
                    'social_links' => $profileData->socialLinks->map(function($item) {
                        return [
                            'url' => $item->url,
                            'platform' => $item->platform,
                            'name' => $item->name,
                            'icon_url' => $item->icon_url
                        ];
                    })->toArray()
                ];

                if(! empty($profileData->website)) {
                    $profileDetails['info']['website'] = $profileData->website;
                }

                if(empty($profileData->privacySettings->country_privacy)) {
                    $locationParts = [];

                    if(! empty($profileData->country_name)) {
                        $locationParts[] = $profileData->country_name;
                    }

                    if(empty($profileData->privacySettings->city_privacy) && ! empty($profileData->city)) {
                        $locationParts[] = $profileData->city;
                    }

                    if(! empty($locationParts)) {
                        $profileDetails['info']['location'] = implode(', ', $locationParts);
                    }
                }

                if(empty($profileData->privacySettings->birthdate_privacy) && $this->hasValidBirthdate($profileData)) {
                    $profileDetails['info']['birthdate'] = "{$profileData->birth_day} {$profileData->birth_month}, {$profileData->birth_year}";

                    $profileDetails['info']['age'] = Carbon::createFromDate(
                        (int) $profileData->birth_year,
                        (int) $profileData->birth_month,
                        (int) $profileData->birth_day
                    )->age;
                }

                if(empty($profileData->privacySettings->phone_privacy)) {
                    $profileDetails['contacts']['phone'] = $profileData->phone;
                }

                if(empty($profileData->privacySettings->email_privacy)) {
                    $profileDetails['contacts']['email'] = $profileData->email;
                }

                $profileDetails['contacts'] = (empty($profileDetails['contacts'])) ? null : $profileDetails['contacts'];

                return $this->responseSuccess([
                    'data' => $profileDetails
                ]);
            }

            else{
                return $this->responseResourceNotFoundError('User', $profileId);
            }
        }
    }

    private function hasValidBirthdate(User $profileData): bool
    {
        $birthDay = $profileData->birth_day;
        $birthMonth = $profileData->birth_month;
        $birthYear = $profileData->birth_year;

        return is_numeric($birthDay) &&
            is_numeric($birthMonth) &&
            is_numeric($birthYear) &&
            checkdate((int) $birthMonth, (int) $birthDay, (int) $birthYear);
    }

    public function getProfilePosts(Request $request)
    {
        $validator = Validator::make([
            'id' => $request->integer('id', 0),
            'filter' => $request->input('filter', []),
        ], [
            'id' => ['required', 'integer', 'min:1'],
            'filter' => ['required', 'array'],
            'filter.cursor' => ['nullable', 'integer', 'min:0'],
            'filter.type' => ['required', 'string', 'in:posts,media,activity']
        ]);

        if($validator->fails()) {
            return $this->throwValidationError($validator);
        }

        $profileId = $request->integer('id');
        $filter = $request->input('filter');

        $contentType = $filter['type'];
        $cursorId = (empty($filter['cursor'])) ? 0 : $filter['cursor'];

        $profileData = User::activeById($profileId)->first();

        if(empty($profileData)) {
            return $this->responseResourceNotFoundError('User', $profileId);
        }

        $blockService = new BlockService(me(), $profileData);
        $isBlocked = $blockService->blockedAny();

        if($isBlocked) {
            // Return empty timeline.
            return $this->responseSuccess([
                'data' => TimelineCollection::make([])
            ]);
        }

        if($contentType == 'posts' || $contentType == 'media') {
            $profilePosts = $profileData->posts()->timelineFormatPosts()->when($cursorId, function($query) use ($cursorId) {
                $query->where('id', '<', $cursorId);
            })->when(($contentType == 'media'), function($query) {
                $query->whereNot('type', PostType::TEXT);
            })->latest()->take(config('post.paginate_per'))->get();

            return $this->responseSuccess([
                'data' => TimelineCollection::make($profilePosts)
            ]);
        }
        else {
            return $this->responseSuccess([
                'data' => TimelineCollection::make([])
            ]);
        }
    }

    public function getProfileFollowers(Request $request)
    {
        return $this->getConnections($request);
    }

    public function getProfileFollowings(Request $request)
    {
        return $this->getConnections($request, 'following');
    }

    public function getConnections(Request $request, $type = 'follower')
    {
        $profileId = $request->integer('id');
        $cursorId = $request->integer('cursor', 0);
        $onlyVerified = $request->boolean('only_verified', false);
        $profileData = User::activeById($profileId)->first();

        if($profileData) {
            $connectionQB = $profileData->followers();

            if($type == 'following') {
                $connectionQB = $profileData->followings();
            }

            $people = $connectionQB->whereHas($type, function($query) use ($onlyVerified) {
                if($onlyVerified) {
                    $query->where('verified', true);
                }
            })->where('status', FollowStatus::FOLLOWING)->when($cursorId, function($query) use ($cursorId) {
                $query->where('id', '<', $cursorId);
            })->latest('id')->take(30)->get();

            $people = $people->map(function($follow) use ($type) {
                $follow->{$type}->cursor_id = $follow->id;
                return $follow->{$type};
            });

            return $this->responseSuccess([
                'data' => PeopleCollection::make($people)
            ]);
        }

        return $this->responseResourceNotFoundError('User', $profileId);
    }
}
