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

namespace App\Http\Controllers\Api\User\Bootstrap;

use App\Info\Zulors;
use App\Models\Post;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Services\Timeline\FeedService;
use Illuminate\Support\Facades\Cache;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Http\Resources\User\Timeline\TimelineCollection;

class BootstrapController extends Controller
{
    use SupportsApiResponses;

    public function bootstrap()
    {
        return $this->responseSuccess([
            'data' => [
                'version' => Zulors::VERSION,
                'name' => config('app.name'),
                'author' => [
                    'name' => 'Mansur Terla. Full-Stack Web Developer.',
                    'email' => 'mansurtl.contact@gmail.com'
                ],
                'auth' => [
                    'status' => auth_check(),
                    'user' => $this->getUserData()
                ],
                'home_feed' => $this->getHomeFeedData(),
            ]
        ]);
    }

    public function homeFeedSeed()
    {
        $cacheKey = 'bootstrap.public_home_feed_seed.v1';
        $payload = Cache::remember($cacheKey, now()->addSeconds(20), function() {
            $perPage = min((int) config('post.paginate_per'), 12);
            $posts = Post::query()
                ->timelineFormatPosts()
                ->whereHas('user', function($query) {
                    $query->active();
                })
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->limit($perPage)
                ->get();

            return [
                'type' => FeedService::TYPE_FOR_YOU,
                'session_id' => 'public-seed',
                'refresh_reason' => 'seed',
                'posts' => TimelineCollection::make($posts)->resolve(request()),
                'meta' => [
                    'feed' => [
                        'type' => FeedService::TYPE_FOR_YOU,
                        'strategy' => 'public_seed_cache',
                        'candidate_count' => $posts->count(),
                        'candidate_limit' => null,
                        'scored' => false,
                        'page' => 1,
                        'per_page' => $perPage,
                        'session_id' => 'public-seed',
                    ],
                ],
            ];
        });

        return $this->responseSuccess([
            'data' => $payload,
        ]);
    }

    private function getUserData()
    {
        if(auth_check()) {
            $me = me();

            $userData = [
                'id' => $me->id,
                'name' => $me->name,
                'avatar_url' => $me->avatar_url,
                'cover_url' => $me->cover_url,
                'first_name' => $me->first_name,
                'last_name' => $me->last_name,
                'caption' => $me->getCaption(),
                'username' => $me->username,
                'has_tips' => $me->has_tips,
                'tips' => $me->tips,
                'is_master_account' => $me->isMasterAccount(),
                'is_author' => $me->isAuthor(),
                'verification' => [
                    'status' => $me->verified,
                    'date' => $me->verified_at ? $me->verified_at->getIso() : null
                ],
                'meta' => [
                    'is_admin' => $me->isAdmin(),
                    'is_root' => $me->isRoot()
                ]
            ];

            if($me->isAdmin() || $me->isRoot()) {
                $userData['meta']['admin'] = [
                    'url' => route('admin.dash.index'),
                ];
            }
            
            return $userData;   
        }
        
        return null;
    }

    private function getHomeFeedData()
    {
        if(! auth_check()) {
            return null;
        }

        $sessionId = 'boot-' . Str::lower(Str::random(18));
        $feedResult = app(FeedService::class)->getFeed(me(), [
            'type' => FeedService::TYPE_FOR_YOU,
            'page' => 1,
            'session_id' => $sessionId,
            'refresh_reason' => 'initial',
            'fast_start' => true,
        ]);

        return [
            'type' => FeedService::TYPE_FOR_YOU,
            'session_id' => $sessionId,
            'refresh_reason' => 'initial',
            'posts' => TimelineCollection::make($feedResult->posts),
            'meta' => $feedResult->meta,
        ];
    }
}
