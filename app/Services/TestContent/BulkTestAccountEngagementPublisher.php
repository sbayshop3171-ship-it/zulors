<?php

namespace App\Services\TestContent;

use App\Database\Configs\Table;
use App\Enums\Post\PostStatus;
use App\Enums\Post\PostType;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\TestContentEngagement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class BulkTestAccountEngagementPublisher
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
	 * @return array{users: array<int, int>, posts: Collection<int, Post>, targets: array<int, array{reactions: int, comments: int}>}
	 */
	public function preview(
		string $campaignKey,
		int $userLimit,
		int $afterId,
		int $postLimit,
		int $reactionMin,
		int $reactionMax,
		int $commentMin,
		int $commentMax,
		bool $testPostsOnly = true,
		?PostType $postType = null,
	): array {
		$users = $this->testUserIds($userLimit);
		$posts = $this->activePostsAfter($afterId, $postLimit, $testPostsOnly, $postType);
		$targets = [];

		foreach ($posts as $post) {
			$targets[$post->id] = $this->targetsFor(
				$campaignKey,
				$post->id,
				$reactionMin,
				$reactionMax,
				$commentMin,
				$commentMax,
			);
		}

		return compact('users', 'posts', 'targets');
	}

	/**
	 * @param array<int, int> $testUserIds
	 * @param callable(Post, array{reactions: int, comments: int}, array{reactions_added: int, reactions_existing: int, comments_added: int, comments_existing: int, failed: bool}): void|null $progress
	 * @return array{posts: int, reactions_added: int, reactions_existing: int, comments_added: int, comments_existing: int, failed: int}
	 */
	public function publish(
		string $campaignKey,
		array $testUserIds,
		Collection $posts,
		array $targets,
		?callable $progress = null,
	): array {
		$summary = [
			'posts' => 0,
			'reactions_added' => 0,
			'reactions_existing' => 0,
			'comments_added' => 0,
			'comments_existing' => 0,
			'failed' => 0,
		];

		foreach ($posts as $post) {
			$target = $targets[$post->id];

			try {
				$result = $this->publishForPost($campaignKey, $testUserIds, $post, $target);
				$summary['posts']++;
				$summary['reactions_added'] += $result['reactions_added'];
				$summary['reactions_existing'] += $result['reactions_existing'];
				$summary['comments_added'] += $result['comments_added'];
				$summary['comments_existing'] += $result['comments_existing'];
				$progress?->__invoke($post, $target, $result);
			} catch (Throwable $exception) {
				$summary['failed']++;
				$progress?->__invoke($post, $target, [
					'reactions_added' => 0,
					'reactions_existing' => 0,
					'comments_added' => 0,
					'comments_existing' => 0,
					'failed' => true,
				]);
				report($exception);
			}
		}

		return $summary;
	}

	/**
	 * @return array{reactions: int, comments: int}
	 */
	public function targetsFor(
		string $campaignKey,
		int $postId,
		int $reactionMin,
		int $reactionMax,
		int $commentMin,
		int $commentMax,
	): array {
		return [
			'reactions' => $this->between("{$campaignKey}:{$postId}:reactions", $reactionMin, $reactionMax),
			'comments' => $this->between("{$campaignKey}:{$postId}:comments", $commentMin, $commentMax),
		];
	}

	/**
	 * @param array<int, int> $testUserIds
	 * @param array{reactions: int, comments: int} $target
	 * @return array{reactions_added: int, reactions_existing: int, comments_added: int, comments_existing: int, failed: bool}
	 */
	private function publishForPost(string $campaignKey, array $testUserIds, Post $post, array $target): array
	{
		return DB::transaction(function () use ($campaignKey, $testUserIds, $post, $target) {
			$lockedPost = Post::query()
				->whereKey($post->id)
				->where('status', PostStatus::ACTIVE)
				->lockForUpdate()
				->firstOrFail();

			$eligibleUserIds = array_values(array_filter(
				$testUserIds,
				fn (int $userId) => $userId !== (int) $lockedPost->user_id,
			));

			if (count($eligibleUserIds) < $target['reactions'] || count($eligibleUserIds) < $target['comments']) {
				throw new \RuntimeException('There are not enough active .test accounts for the requested engagement target.');
			}

			$reactions = $lockedPost->reactions()->lockForUpdate()->get();
			$existingReactionUsers = $this->existingReactionUsers($reactions, $eligibleUserIds);
			$reactionUserIds = $this->rotatingUsers(
				$eligibleUserIds,
				$target['reactions'],
				"{$campaignKey}:{$lockedPost->id}:reaction-users",
			);
			$reactionResult = $this->syncReactions($lockedPost, $reactions, $reactionUserIds, $existingReactionUsers, $campaignKey);

			$existingCommentUserIds = Comment::query()
				->where('post_id', $lockedPost->id)
				->pluck('user_id')
				->map(fn ($id) => (int) $id)
				->all();
			$trackedCommentUserIds = TestContentEngagement::query()
				->where('campaign_key', $campaignKey)
				->where('post_id', $lockedPost->id)
				->where('status', 'published')
				->pluck('user_id')
				->map(fn ($id) => (int) $id)
				->all();
			$plannedCommentUserIds = $this->rotatingUsers(
				$eligibleUserIds,
				$target['comments'],
				"{$campaignKey}:{$lockedPost->id}:comment-users",
			);
			$existingCommentLookup = array_fill_keys($existingCommentUserIds, true);
			$trackedCommentLookup = array_fill_keys($trackedCommentUserIds, true);
			$commentUserIds = array_values(array_filter(
				$plannedCommentUserIds,
				fn (int $userId) => ! isset($existingCommentLookup[$userId]) && ! isset($trackedCommentLookup[$userId]),
			));

			$commentsAdded = $this->insertComments($campaignKey, $lockedPost, $commentUserIds);

			if ($commentsAdded > 0) {
				$lockedPost->increment('comments_count', $commentsAdded);
			}

			return [
				'reactions_added' => $reactionResult['added'],
				'reactions_existing' => $target['reactions'] - $reactionResult['added'],
				'comments_added' => $commentsAdded,
				'comments_existing' => $target['comments'] - $commentsAdded,
				'failed' => false,
			];
		}, 3);
	}

	/**
	 * @param Collection<int, Reaction> $reactions
	 * @param array<int, int> $eligibleUserIds
	 * @return array<int, string>
	 */
	private function existingReactionUsers(Collection $reactions, array $eligibleUserIds): array
	{
		$eligibleLookup = array_fill_keys($eligibleUserIds, true);
		$existing = [];

		foreach ($reactions as $reaction) {
			foreach ($reaction->users ?? [] as $userId) {
				$userId = (int) $userId;

				if (isset($eligibleLookup[$userId])) {
					$existing[$userId] = $reaction->unified_id;
				}
			}
		}

		return $existing;
	}

	/**
	 * @param Collection<int, Reaction> $reactions
	 * @param array<int, int> $reactionUserIds
	 * @param array<int, string> $existingReactionUsers
	 * @return array{added: int}
	 */
	private function syncReactions(
		Post $post,
		Collection $reactions,
		array $reactionUserIds,
		array $existingReactionUsers,
		string $campaignKey,
	): array {
		$reactionByType = $reactions->keyBy('unified_id');
		$usersByType = [];
		$added = 0;

		foreach ($reactionUserIds as $userId) {
			if (isset($existingReactionUsers[$userId])) {
				continue;
			}

			$unifiedId = self::REACTIONS[$this->index("{$campaignKey}:{$post->id}:{$userId}:reaction", count(self::REACTIONS))];
			$usersByType[$unifiedId][] = $userId;
			$added++;
		}

		foreach ($usersByType as $unifiedId => $userIds) {
			/** @var Reaction|null $reaction */
			$reaction = $reactionByType->get($unifiedId);

			if (! $reaction) {
				$post->reactions()->create([
					'unified_id' => $unifiedId,
					'users' => $userIds,
					'reactions_count' => count($userIds),
				]);
				continue;
			}

			$users = array_values(array_unique(array_map('intval', [...($reaction->users ?? []), ...$userIds])));
			$reaction->update([
				'users' => $users,
				'reactions_count' => count($users),
			]);
		}

		return compact('added');
	}

	/**
	 * @param array<int, int> $userIds
	 */
	private function insertComments(string $campaignKey, Post $post, array $userIds): int
	{
		if ($userIds === []) {
			return 0;
		}

		$now = now();
		$comments = [];
		$engagements = [];

		foreach ($userIds as $userId) {
			$user = new User(['id' => $userId]);
			$user->exists = true;
			$comments[] = [
				'post_id' => $post->id,
				'user_id' => $userId,
				'parent_id' => null,
				'content' => $this->commentFactory->make($user, $post, $campaignKey),
				'text_language' => $post->text_language ?: 'en',
				'created_at' => $now,
				'updated_at' => $now,
			];
			$engagements[] = [
				'campaign_key' => $campaignKey,
				'user_id' => $userId,
				'post_id' => $post->id,
				'comment_id' => null,
				'reaction_unified_id' => null,
				'status' => 'published',
				'error_message' => null,
				'published_at' => $now,
				'created_at' => $now,
				'updated_at' => $now,
			];
		}

		DB::table(Table::COMMENTS)->insert($comments);
		DB::table(Table::TEST_CONTENT_ENGAGEMENTS)->insert($engagements);

		return count($comments);
	}

	/**
	 * @param array<int, int> $userIds
	 * @param array<int, int> $excludedUserIds
	 * @return array<int, int>
	 */
	private function rotatingUsers(array $userIds, int $target, string $seed, array $excludedUserIds = []): array
	{
		$excluded = array_fill_keys($excludedUserIds, true);
		$selected = [];
		$total = count($userIds);
		$start = $this->index($seed, $total);

		for ($offset = 0; $offset < $total && count($selected) < $target; $offset++) {
			$userId = $userIds[($start + $offset) % $total];

			if (! isset($excluded[$userId])) {
				$selected[] = $userId;
			}
		}

		if (count($selected) !== $target) {
			throw new \RuntimeException('The requested engagement target could not be satisfied.');
		}

		return $selected;
	}

	/** @return array<int, int> */
	private function testUserIds(int $limit = 0): array
	{
		return User::query()
			->active()
			->whereRaw('LOWER(email) LIKE ?', ['%.test'])
			->orderBy('id')
			->when($limit > 0, fn ($query) => $query->limit($limit))
			->pluck('id')
			->map(fn ($id) => (int) $id)
			->all();
	}

	/** @return Collection<int, Post> */
	private function activePostsAfter(
		int $afterId,
		int $postLimit,
		bool $testPostsOnly = true,
		?PostType $postType = null,
	): Collection
	{
		return Post::query()
			->active()
			->where('id', '>', $afterId)
			->when($postType !== null, fn ($query) => $query->where('type', $postType))
			->when(
				$testPostsOnly,
				fn ($query) => $query->whereHas('user', fn ($userQuery) => $userQuery->whereRaw('LOWER(email) LIKE ?', ['%.test'])),
			)
			->orderBy('id')
			->limit($postLimit)
			->get();
	}

	private function between(string $seed, int $min, int $max): int
	{
		if ($min > $max || $min < 0) {
			throw new \InvalidArgumentException('Invalid engagement target range.');
		}

		return $min + $this->index($seed, ($max - $min) + 1);
	}

	private function index(string $seed, int $length): int
	{
		if ($length < 1) {
			throw new \InvalidArgumentException('The target collection cannot be empty.');
		}

		return (int) (sprintf('%u', crc32($seed)) % $length);
	}
}
