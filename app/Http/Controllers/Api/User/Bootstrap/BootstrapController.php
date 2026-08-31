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
    private const PUBLIC_HOME_FEED_SEED_TTL_SECONDS = 1800;

    public function bootstrap()
    {
        $startedAt = microtime(true);
        $userStartedAt = microtime(true);
        $userData = $this->getUserData();
        $userDurationMs = $this->toMilliseconds($userStartedAt);
        $feedStartedAt = microtime(true);
        $homeFeedData = $this->getHomeFeedData();
        $feedDurationMs = $this->toMilliseconds($feedStartedAt);
        $response = $this->responseSuccess([
            'data' => [
                'version' => Zulors::VERSION,
                'name' => config('app.name'),
                'author' => [
                    'name' => 'Mansur Terla. Full-Stack Web Developer.',
                    'email' => 'mansurtl.contact@gmail.com'
                ],
                'auth' => [
                    'status' => auth_check(),
                    'user' => $userData
                ],
                'home_feed' => $homeFeedData,
            ]
        ]);

        return $response->withHeaders([
            'Cache-Control' => 'private, no-store',
            'Server-Timing' => implode(', ', array_filter([
                'bootstrap;dur=' . $this->toMilliseconds($startedAt),
                'user;dur=' . $userDurationMs,
                auth_check() ? 'feed;dur=' . $feedDurationMs : null
            ])),
            'X-Zulors-Home-Feed-Cache' => ! auth_check()
                ? 'skip'
                : 'personalized'
        ]);
    }

    public function homeFeedSeed()
    {
        $startedAt = microtime(true);
        $cacheKey = $this->publicHomeFeedSeedCacheKey();
        $cacheHit = Cache::has($cacheKey);
        $response = $this->responseSuccess([
            'data' => $this->resolvePublicHomeFeedSeedPayload($cacheKey),
        ]);

        return $response->withHeaders([
            'Cache-Control' => 'public, max-age=60, stale-while-revalidate=300',
            'Server-Timing' => 'home-feed-seed;dur=' . $this->toMilliseconds($startedAt),
            'X-Zulors-Home-Feed-Cache' => $cacheHit ? 'hit' : 'miss'
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
        $refreshReason = 'initial';
        $feedResult = app(FeedService::class)->getFeed(me(), [
            'page' => 1,
            'type' => FeedService::TYPE_FOR_YOU,
            'session_id' => $sessionId,
            'refresh_reason' => $refreshReason,
        ]);

        return [
            'type' => FeedService::TYPE_FOR_YOU,
            'session_id' => $sessionId,
            'refresh_reason' => $refreshReason,
            'posts' => TimelineCollection::make($feedResult->posts)->resolve(request()),
            'meta' => $feedResult->meta,
        ];
    }

    private function resolvePublicHomeFeedSeedPayload(
        string $cacheKey = null,
        string $sessionId = 'public-seed',
        string $refreshReason = 'seed',
        string $strategy = 'public_seed_cache'
    ): array {
        $cacheKey ??= $this->publicHomeFeedSeedCacheKey();
        $cacheStore = $this->publicHomeFeedCacheStore();
        $seedData = $cacheStore->remember(
            $cacheKey,
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
                    'rank_version' => 'chronological_v1',
                    'feed_family' => 'home',
                    'candidate_count' => $seedData['candidate_count'],
                    'candidate_limit' => null,
                    'candidate_sources' => ['latest'],
                    're_rank_allowed' => false,
                    'session_window_size' => 0,
                    'scored' => false,
                    'page' => 1,
                    'per_page' => $seedData['per_page'],
                    'session_id' => $sessionId,
                ],
            ],
        ];
    }

    private function publicHomeFeedCacheStore()
    {
        $redisAvailable = extension_loaded('redis') && class_exists('Redis');

        if($redisAvailable && config('cache.default') === 'redis') {
            return Cache::store('redis');
        }

        if($redisAvailable && filled(config('database.redis.default.host'))) {
            return Cache::store('redis');
        }

        return Cache::store('file');
    }

    private function publicHomeFeedSeedCacheKey(): string
    {
        $latestPost = Post::query()
            ->select('id', 'updated_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        $suffix = $latestPost
            ? $latestPost->updated_at->format('YmdHis') . ':' . $latestPost->id
            : 'empty';

        return self::PUBLIC_HOME_FEED_SEED_CACHE_KEY . ':' . md5($suffix);
    }

    private function toMilliseconds(float $startedAt): string
    {
        return number_format((microtime(true) - $startedAt) * 1000, 1, '.', '');
    }
}
