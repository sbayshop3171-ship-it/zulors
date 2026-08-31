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
use Illuminate\Http\Response;
use App\Enums\Post\PostStatus;
use App\Http\Controllers\Controller;
use App\Services\Timeline\FeedService;
use Illuminate\Support\Facades\Cache;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Http\Resources\User\Timeline\TimelineResource;
use App\Http\Resources\User\Timeline\CommentCollection;
use App\Http\Resources\User\Timeline\TimelineCollection;
use App\Http\Resources\User\Overview\UserOverviewResource;

class FeedController extends Controller
{
    use SupportsApiResponses;

    private const HOME_FEED_RESPONSE_CACHE_TTL_SECONDS = 60;

    private $me = null;
    private $filter = [];

    public function __construct()
    {
        $this->me = me();
    }

    public function getFeed()
    {
        $startedAt = microtime(true);
        $filter = $this->normalizedFeedFilter();
        $canUseHomeSwr = $this->canUseHomeFeedSwr($filter);
        $responseCacheKey = $canUseHomeSwr ? $this->homeFeedResponseCacheKey($filter) : null;
        $cachedResponse = $responseCacheKey ? Cache::get($responseCacheKey) : null;

        if($canUseHomeSwr && is_array($cachedResponse) && is_array(data_get($cachedResponse, 'payload'))) {
            $etag = data_get($cachedResponse, 'etag');
            $snapshotHash = data_get($cachedResponse, 'snapshot_hash');

            if($etag && $this->requestEtagMatches($etag)) {
                return $this->homeFeedNotModifiedResponse($etag, $snapshotHash, $startedAt, 'hit');
            }

            return $this->homeFeedSuccessResponse($cachedResponse['payload'], $etag, $snapshotHash, $startedAt, 'hit');
        }

        $feedResult = app(FeedService::class)->getFeed($this->me, $filter);
        $payload = [
            'data' => $this->timelineCollectionData($feedResult->posts),
            'meta' => $feedResult->meta,
        ];

        if(! $canUseHomeSwr) {
            return $this->responseSuccess($payload);
        }

        $snapshotHash = $this->homeFeedSnapshotHash($payload);
        $etag = $this->formatHomeFeedEtag($snapshotHash);

        Cache::put($responseCacheKey, [
            'payload' => $payload,
            'etag' => $etag,
            'snapshot_hash' => $snapshotHash,
        ], now()->addSeconds(self::HOME_FEED_RESPONSE_CACHE_TTL_SECONDS));

        if($this->requestEtagMatches($etag)) {
            return $this->homeFeedNotModifiedResponse($etag, $snapshotHash, $startedAt, 'miss-validated');
        }

        return $this->homeFeedSuccessResponse($payload, $etag, $snapshotHash, $startedAt, 'miss');
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

    private function normalizedFeedFilter(): array
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

        return $filter;
    }

    private function canUseHomeFeedSwr(array $filter): bool
    {
        return data_get($filter, 'type', FeedService::TYPE_FOR_YOU) === FeedService::TYPE_FOR_YOU
            && data_get_integer($filter, 'page', 1) === 1
            && data_get_integer($filter, 'onset', 0) === 0;
    }

    private function timelineCollectionData($posts): array
    {
        return TimelineCollection::make($posts)
            ->response(request())
            ->getData(true)['data'] ?? [];
    }

    private function homeFeedResponseCacheKey(array $filter): string
    {
        return 'timeline.feed.response.home.v1.' . sha1(json_encode([
            'user_id' => $this->me->id,
            'type' => FeedService::TYPE_FOR_YOU,
            'page' => 1,
            'candidate_limit' => data_get_integer($filter, 'candidate_limit', 0),
            'seed_hash_id' => substr((string) data_get($filter, 'seed_hash_id', ''), 0, 80),
            'fast_start' => filter_var(data_get($filter, 'fast_start', false), FILTER_VALIDATE_BOOLEAN),
            'session_id' => substr((string) data_get($filter, 'session_id', ''), 0, 80),
        ]));
    }

    private function homeFeedSnapshotHash(array $payload): string
    {
        $postMarkers = collect(data_get($payload, 'data', []))->map(function(array $postData) {
            return [
                'id' => data_get($postData, 'id'),
                'hash_id' => data_get($postData, 'hash_id'),
                'updated_at' => data_get($postData, 'date.updated_at') ?? data_get($postData, 'updated_at'),
            ];
        })->values()->all();

        return sha1(json_encode([
            'viewer' => $this->me->id,
            'posts' => $postMarkers,
            'meta' => data_get($payload, 'meta.feed', []),
        ]));
    }

    private function formatHomeFeedEtag(string $snapshotHash): string
    {
        return '"zulors-home-feed-' . $snapshotHash . '"';
    }

    private function requestEtagMatches(?string $etag): bool
    {
        if(empty($etag)) {
            return false;
        }

        $clientEtags = collect(explode(',', (string) request()->headers->get('If-None-Match', '')))
            ->map(fn($value) => trim($value))
            ->filter()
            ->all();

        return in_array($etag, $clientEtags, true) || in_array(trim($etag, '"'), $clientEtags, true);
    }

    private function homeFeedSuccessResponse(array $payload, ?string $etag, ?string $snapshotHash, float $startedAt, string $cacheStatus)
    {
        return $this->responseSuccess($payload)->withHeaders($this->homeFeedSwrHeaders($etag, $snapshotHash, $startedAt, $cacheStatus));
    }

    private function homeFeedNotModifiedResponse(?string $etag, ?string $snapshotHash, float $startedAt, string $cacheStatus)
    {
        return response('', Response::HTTP_NOT_MODIFIED)
            ->withHeaders($this->homeFeedSwrHeaders($etag, $snapshotHash, $startedAt, $cacheStatus));
    }

    private function homeFeedSwrHeaders(?string $etag, ?string $snapshotHash, float $startedAt, string $cacheStatus): array
    {
        return array_filter([
            'Cache-Control' => 'private, max-age=0, stale-while-revalidate=60',
            'ETag' => $etag,
            'X-Zulors-Feed-Snapshot' => $snapshotHash,
            'X-Zulors-Feed-Cache' => $cacheStatus,
            'Server-Timing' => 'timeline-feed;dur=' . $this->toMilliseconds($startedAt),
        ], fn($value) => $value !== null && $value !== '');
    }

    private function toMilliseconds(float $startedAt): string
    {
        return number_format((microtime(true) - $startedAt) * 1000, 1, '.', '');
    }
}
