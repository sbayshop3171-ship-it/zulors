<?php

namespace App\Actions\Comment;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\User\Post\PostCommentedNotification;
use App\Notifications\User\Mention\CommentMentionNotification;
use App\Services\Timeline\UserInterestService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCommentAction
{
    public function execute(Post $post, User $actor, string $content, ?Comment $parent = null, bool $hasMedia = false): Comment
    {
        $targetPost = $post;

        return DB::transaction(function () use ($post, $targetPost, $actor, $content, $parent, $hasMedia) {
            $post = Post::activeById($post->id)->lockForUpdate()->first();
            if (! $post) {
                throw ValidationException::withMessages(['post_id' => 'Publication is no longer available.']);
            }
            if ($parent) {
                $parent = Comment::whereKey($parent->id)->lockForUpdate()->first();
            }
            if ($parent && ((int) $parent->post_id !== (int) $post->id || ! $post->comments()->whereKey($parent->id)->exists())) {
                throw ValidationException::withMessages(['parent_id' => 'Reply target does not belong to this publication.']);
            }

            $rootId = $parent ? $this->resolveThreadRootId($parent, $post->id) : null;
            $comment = $post->comments()->create([
                'content' => $content,
                'user_id' => $actor->id,
                'parent_id' => $parent?->id,
                'thread_root_id' => $rootId,
                'text_language' => $post->getContentLanguage(),
            ]);

            if (! $parent) {
                $comment->thread_root_id = $comment->id;
                $comment->save();
            }

            // The post row is locked for this transaction, so an atomic counter
            // increment stays correct without counting a potentially huge thread.
            $post->comments_count = (int) $post->comments_count + 1;
            $post->save();
            $targetPost->comments_count = (int) $post->comments_count;
            app(UserInterestService::class)->recordPostInteraction($actor, $post, UserInterestService::EVENT_COMMENT);
            $notificationContent = trim($content) === '' && $hasMedia ? '📷' : $content;

            if (! $post->is_owner && ! $parent) {
                $recipient = $post->user;
                $notification = new PostCommentedNotification($post, $notificationContent);
                DB::afterCommit(fn () => $recipient->notify($notification));
            } elseif ($parent && ! $parent->is_owner && (int) $parent->user_id !== (int) $actor->id) {
                $recipient = $parent->user;
                $notification = new CommentMentionNotification($parent, $notificationContent);
                DB::afterCommit(fn () => $recipient->notify($notification));
            }

            return $comment->load(['user', 'parent.user', 'reactions', 'media']);
        });
    }

    /**
     * Older comments may not have thread_root_id until the chunked backfill has
     * completed. Walk the parent chain in that case so replies created during
     * a rolling deployment still join the original root thread.
     */
    private function resolveThreadRootId(Comment $parent, int $postId): int
    {
        if ($parent->thread_root_id) {
            return (int) $parent->thread_root_id;
        }

        $current = $parent;
        $visited = [];
        while ($current->parent_id) {
            if (isset($visited[$current->id])) {
                break;
            }
            $visited[$current->id] = true;

            $ancestor = Comment::query()
                ->where('post_id', $postId)
                ->whereKey($current->parent_id)
                ->first();
            if (! $ancestor) {
                break;
            }
            if ($ancestor->thread_root_id) {
                return (int) $ancestor->thread_root_id;
            }
            $current = $ancestor;
        }

        return (int) $current->id;
    }
}
