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

    private const PUBLIC_HOME_FEED_SEED_CACHE_KEY = 'bootstrap.public_home_feed_seed.v2';
    private const PUBLIC_HOME_FEED_SEED_TTL_SECONDS = 20;

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
        return $this->responseSuccess([
            'data' => $this->resolvePublicHomeFeedSeedPayload(),
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

        return $this->resolvePublicHomeFeedSeedPayload(
            sessionId: 'boot-' . Str::lower(Str::random(18)),
            refreshReason: 'initial',
            strategy: 'bootstrap_public_seed'
        );
    }

    private function resolvePublicHomeFeedSeedPayload(
        string $sessionId = 'public-seed',
        string $refreshReason = 'seed',
        string $strategy = 'public_seed_cache'
    ): array {
        $seedData = Cache::remember(
            self::PUBLIC_HOME_FEED_SEED_CACHE_KEY,
            now()->addSeconds(self::PUBLIC_HOME_FEED_SEED_TTL_SECONDS),
            function() {
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
                    'candidate_count' => $posts->count(),
                    'per_page' => $perPage,
                    'posts' => TimelineCollection::make($posts)->resolve(request()),
                ];
            }
        );

        return [
            'type' => FeedService::TYPE_FOR_YOU,
            'session_id' => $sessionId,
            'refresh_reason' => $refreshReason,
            'posts' => $seedData['posts'],
            'meta' => [
                'feed' => [
                    'type' => FeedService::TYPE_FOR_YOU,
                    'strategy' => $strategy,
                    'candidate_count' => $seedData['candidate_count'],
                    'candidate_limit' => null,
                    'scored' => false,
                    'page' => 1,
                    'per_page' => $seedData['per_page'],
                    'session_id' => $sessionId,
                ],
            ],
        ];
    }
}
