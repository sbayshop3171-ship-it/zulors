<?php

namespace App\Http\Controllers\Api\User\Timeline;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\Timeline\CommentCollection;
use App\Models\Comment;
use App\Models\Post;
use App\Support\Num;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommentThreadController extends Controller
{
    use SupportsApiResponses;

    public function roots(Request $request, string $hashId)
    {
        $post = Post::active()->whereHashId($hashId)->first();
        if (! $post) {
            return $this->responseResourceNotFoundError('Post', $hashId);
        }

        $sort = $request->string('sort', 'relevant')->toString();
        abort_unless(in_array($sort, ['relevant', 'newest'], true), 422, 'Unsupported comment sort.');
        $offset = min(1000000, max(0, $request->integer('cursor', 0)));
        $commentsCount = (int) $post->comments()->count();
        if ($commentsCount !== (int) $post->comments_count) {
            $post->forceFill(['comments_count' => $commentsCount])->save();
        }

        $query = $post->comments()
            ->whereNull('parent_id')
            ->select('comments.*')
            ->with(['post:id,user_id', 'user:id,first_name,last_name,avatar,username', 'parent.user:id,first_name,last_name,username', 'reactions', 'media'])
            ->withCount('threadReplies as reply_count')
            ->withSum('reactions as reaction_count', 'reactions_count')
            ->addSelect(['author_replied' => DB::table('comments as author_replies')
                ->selectRaw('1')
                ->whereColumn('author_replies.thread_root_id', 'comments.id')
                ->whereColumn('author_replies.post_id', 'comments.post_id')
                ->whereColumn('author_replies.user_id', 'posts.user_id')
                ->whereColumn('author_replies.id', '<>', 'comments.id')
                ->limit(1)])
            ->join('posts', 'posts.id', '=', 'comments.post_id');

        if ($sort === 'newest') {
            $query->orderByDesc('comments.created_at')->orderByDesc('comments.id');
        } else {
            $query->orderByDesc('author_replied')
                ->orderByDesc('reply_count')
                ->orderByDesc('reaction_count')
                ->orderByDesc('comments.created_at')
                ->orderByDesc('comments.id');
        }

        $rows = $query->skip($offset)->take(21)->get();
        $hasMore = $rows->count() > 20;
        $items = $rows->take(20)->values();

        return $this->responseSuccess([
            'data' => CommentCollection::make($items),
            'meta' => [
                'sort' => $sort,
                'next_cursor' => $hasMore ? $offset + 20 : null,
                'has_more' => $hasMore,
                'comments_count' => $commentsCount,
                'comments_count_formatted' => Num::abbreviate($commentsCount),
            ],
        ]);
    }

    public function replies(Request $request, string $hashId, int $rootId)
    {
        $post = Post::active()->whereHashId($hashId)->first();
        if (! $post) {
            return $this->responseResourceNotFoundError('Post', $hashId);
        }

        $root = $post->comments()->whereNull('parent_id')->whereKey($rootId)->first();
        if (! $root) {
            return $this->responseResourceNotFoundError('Comment thread', $rootId);
        }

        $cursorValue = $request->query('cursor');
        $cursor = null;
        if (is_string($cursorValue) && ctype_digit($cursorValue)) {
            // Accept the numeric ID cursor used by the first client rollout.
            $cursor = ['id' => (int) $cursorValue];
        } elseif (is_string($cursorValue) && $cursorValue !== '') {
            $decoded = base64_decode(strtr($cursorValue, '-_', '+/'), true);
            $payload = $decoded === false ? null : json_decode($decoded, true);
            abort_unless(is_array($payload) && is_string($payload['created_at'] ?? null) && is_numeric($payload['id'] ?? null), 422, 'Reply cursor is invalid.');
            $cursor = ['created_at' => $payload['created_at'], 'id' => (int) $payload['id']];
        }
        $query = Comment::query()->where('post_id', $post->id)
            ->where('thread_root_id', $root->id)
            ->where('id', '<>', $root->id)
            ->when($cursor, function ($builder) use ($cursor) {
                if (isset($cursor['created_at'])) {
                    $builder->where(function ($after) use ($cursor) {
                        $after->where('created_at', '>', $cursor['created_at'])
                            ->orWhere(function ($sameTimestamp) use ($cursor) {
                                $sameTimestamp->where('created_at', '=', $cursor['created_at'])->where('id', '>', $cursor['id']);
                            });
                    });
                } else {
                    $builder->where('id', '>', $cursor['id']);
                }
            })
            ->with(['post:id,user_id', 'user:id,first_name,last_name,avatar,username', 'parent.user:id,first_name,last_name,username', 'reactions', 'media'])
            ->orderBy('created_at')->orderBy('id')
            ->take(21)
            ->get();
        $hasMore = $query->count() > 20;
        $items = $query->take(20)->values();

        return $this->responseSuccess([
            'data' => CommentCollection::make($items),
            'meta' => [
                'next_cursor' => $hasMore ? rtrim(strtr(base64_encode(json_encode([
                    'created_at' => $items->last()->getRawOriginal('created_at'),
                    'id' => $items->last()->id,
                ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=') : null,
                'has_more' => $hasMore,
                'thread_root_id' => $root->id,
            ],
        ]);
    }
}
