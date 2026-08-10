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

use App\Models\Post;
use Illuminate\Http\Request;
use App\Enums\Post\PostStatus;
use App\Http\Controllers\Controller;
use App\Services\Timeline\FeedService;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Http\Resources\User\Timeline\TimelineResource;
use App\Http\Resources\User\Timeline\CommentCollection;
use App\Http\Resources\User\Timeline\TimelineCollection;
use App\Http\Resources\User\Overview\UserOverviewResource;

class FeedController extends Controller
{
    use SupportsApiResponses;

    private $me = null;
    private $filter = [];

    public function __construct()
    {
        $this->me = me();
    }

    public function getFeed()
    {
        $filter = request()->array('filter');

        $filter['type'] = request()->string('type', data_get($filter, 'type', FeedService::TYPE_FOR_YOU))->toString();

        foreach(['page', 'onset', 'candidate_limit'] as $filterKey) {
            if(request()->has($filterKey) && empty($filter[$filterKey])) {
                $filter[$filterKey] = request()->integer($filterKey);
            }
        }

        foreach(['session_id', 'refresh_reason', 'seed_hash_id', 'fast_start'] as $filterKey) {
            if(request()->has($filterKey) && empty($filter[$filterKey])) {
                $filter[$filterKey] = request()->string($filterKey)->toString();
            }
        }

        $feedResult = app(FeedService::class)->getFeed($this->me, $filter);
        
        return $this->responseSuccess([
            'data' => TimelineCollection::make($feedResult->posts),
            'meta' => $feedResult->meta,
        ]);
    }

    public function getFeedUpdate()
    {
        return $this->getFeed();
    }

    public function getPostData(Request $request)
    {
        $postHashId = $request->route('hashId');

        $postData = Post::query()
            ->whereHashId($postHashId)
            ->timelineFormatPosts(true)
            ->first();
        
        if($postData && ($postData->status === PostStatus::ACTIVE || $postData->user_id === me()->id)) {
            $postComments = $this->fetchPostItemComments($postData);

            return $this->responseSuccess([
                'data' => [
                    'author' => UserOverviewResource::make($postData->user),
                    'post' => TimelineResource::make($postData),
                    'comments' => CommentCollection::make($postComments),
                    'meta' => [
                        'comments_per_page' => config('post.comments.paginate_per')
                    ]
                ]
            ]);
        }

        else{
            return $this->responseResourceNotFoundError('Post', $postHashId);
        }
    }

    public function getPostComments(Request $request)
    {
        $postHashId = $request->route('hashId');
        $cursorId = $request->integer('cursor');

        $postData = Post::active()->whereHashId($postHashId)->first();

        if(empty($postData)) {
            return $this->responseResourceNotFoundError('Post', $postHashId);
        }

        $postComments = $this->fetchPostItemComments($postData, $cursorId);

        return $this->responseSuccess([
            'data' => CommentCollection::make($postComments)
        ]);
    }

    private function fetchPostItemComments(Post $postData, int|string $cursorId = 0)
    {   
        $postComments = $postData->comments()->with([
            'post:id,user_id',
            'user:id,first_name,last_name,avatar,username',
            'reactions',
            'parent.user:id,first_name,last_name,username'
        ])->when($cursorId, function($query) use ($cursorId) {
            $query->where('id', '<', $cursorId);
        })->latest('id');

        return $postComments->take(config('post.comments.paginate_per'))->get();
    }
}
