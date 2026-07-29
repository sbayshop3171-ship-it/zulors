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

namespace App\Http\Controllers\Api\User\Recommend;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Actions\Recommend\FetchFollowRecommendation;
use App\Http\Resources\User\Recommend\FollowCollection;

class FollowRecommendController extends Controller
{
    use SupportsApiResponses;

    public function getFollowRecommendations(Request $request)
    {
        $limit = $request->integer('limit', config('recommend.follow_recommendation_limit'));

        $recommendations = (new FetchFollowRecommendation())->handle($limit);

        return $this->responseSuccess([
            'data' => FollowCollection::make($recommendations)
        ]);
    }
}
