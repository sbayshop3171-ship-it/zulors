<?php

namespace App\Services\TestContent;

use App\Enums\Post\PostStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\TestContentEngagement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TestAccountEngagementPublisher
{
	private const REACTIONS = [
		'1f44d',
		'2764-fe0f',
		'1f525',
		'1f60d',
		'1f389',
		'1f642',
		'1f92f',
		'1f970',
	];

	public function __construct(
		private readonly OriginalTestEngagementCommentFactory $commentFactory,
	) {
	}

	/**
	 * @return array{users: Collection<int, User>, posts: Collection<int, Post>}
	 */
	public function preview(int $userLimit, int $postLimit): array
	{
		$users = User::query()
			->active()
			->whereRaw('LOWER(email) LIKE ?', ['%.test'])
			->orderBy('id')
			->limit($userLimit)
			->get();

		$posts = Post::query()
			->where('status', PostStatus::ACTIVE)
			->when(
				$users->isNotEmpty(),
				fn ($query) => $query->whereNotIn('user_id', $users->modelKeys()),
			)
			->latest('id')
			->limit($postLimit)
			->get();

		return compact('users', 'posts');
	}

	/**
	 * @param callable(int, int, User, Post, string): void|null $progress
	 * @return array{published: int, skipped: int, failed: int}
	 */
	public function publish(
		string $campaignKey,
		Collection $users,
		Collection $posts,
		?callable $progress = null,
	): array {
		$summary = ['published' => 0, 'skipped' => 0, 'failed' => 0];
		$total = $users->count() * $posts->count();
		$current = 0;

		foreach ($users as $user) {
			foreach ($posts as $post) {
				$current++;

				try {
					$result = $this->publishFor($campaignKey, $user, $post);
					$summary[$result]++;
					$progress?->__invoke($current, $total, $user, $post, $result);
				} catch (\Throwable $exception) {
					$summary['failed']++;
					$this->markFailed($campaignKey, $user->id, $post->id, $exception);
					$progress?->__invoke($current, $total, $user, $post, 'failed');
				}
			}
		}

		return $summary;
	}

	private function publishFor(string $campaignKey, User $user, Post $post): string
	{
		return DB::transaction(function () use ($campaignKey, $user, $post) {
			$engagement = TestContentEngagement::query()
				->where('campaign_key', $campaignKey)
				->where('user_id', $user->id)
				->where('post_id', $post->id)
				->lockForUpdate()
				->first();

			if ($engagement?->status === 'published') {
				return 'skipped';
			}

			$engagement ??= TestContentEngagement::create([
				'campaign_key' => $campaignKey,
				'user_id' => $user->id,
				'post_id' => $post->id,
				'status' => 'reserved',
			]);

			$lockedPost = Post::query()
				->whereKey($post->id)
				->where('status', PostStatus::ACTIVE)
				->lockForUpdate()
				->firstOrFail();

			$reactionUnifiedId = $this->ensureReaction($lockedPost, $user, $campaignKey);
			$comment = new Comment([
				'content' => $this->commentFactory->make($user, $lockedPost, $campaignKey),
				'user_id' => $user->id,
				'post_id' => $lockedPost->id,
				'parent_id' => null,
			]);
			$comment->text_language = $lockedPost->text_language ?: 'en';
			$comment->save();

			$lockedPost->increment('comments_count');

			$engagement->update([
				'comment_id' => $comment->id,
				'reaction_unified_id' => $reactionUnifiedId,
				'status' => 'published',
				'error_message' => null,
				'published_at' => now(),
			]);

			return 'published';
		});
	}

	private function ensureReaction(Post $post, User $user, string $campaignKey): string
	{
		$reactions = $post->reactions()->lockForUpdate()->get();

		foreach ($reactions as $reaction) {
			if (in_array($user->id, array_map('intval', $reaction->users ?? []), true)) {
				return $reaction->unified_id;
			}
		}

		$unifiedId = self::REACTIONS[$this->deterministicIndex($campaignKey, $user->id, $post->id, count(self::REACTIONS))];
		$reaction = $reactions->firstWhere('unified_id', $unifiedId);

		if (! $reaction) {
			$post->reactions()->create([
				'unified_id' => $unifiedId,
				'users' => [$user->id],
				'reactions_count' => 1,
			]);

			return $unifiedId;
		}

		$users = array_values(array_unique(array_map('intval', [...($reaction->users ?? []), $user->id])));
		$reaction->update([
			'users' => $users,
			'reactions_count' => count($users),
		]);

		return $unifiedId;
	}

	private function markFailed(string $campaignKey, int $userId, int $postId, \Throwable $exception): void
	{
		TestContentEngagement::query()->updateOrCreate(
			[
				'campaign_key' => $campaignKey,
				'user_id' => $userId,
				'post_id' => $postId,
			],
			[
				'status' => 'failed',
				'error_message' => str($exception->getMessage())->limit(1000)->toString(),
			],
		);
	}

	private function deterministicIndex(string $campaignKey, int $userId, int $postId, int $length): int
	{
		return (int) (sprintf('%u', crc32("{$campaignKey}:{$userId}:{$postId}")) % $length);
	}
}
