<?php

namespace App\Services\TestContent;

use App\Database\Configs\Table;
use App\Enums\User\FollowStatus;
use App\Enums\User\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class BulkTestAccountFollowPublisher
{
	/**
	 * @param array<int, int> $followerTargets
	 * @return array{users: array<int, int>, targets: Collection<int, User>, quotas: array<int, int>}
	 */
	public function preview(string $campaignKey, int $afterId, int $targetLimit, array $followerTargets): array
	{
		$users = $this->testUserIds();
		$targets = $this->testUsersAfter($afterId, $targetLimit);
		$quotas = [];

		foreach ($targets as $target) {
			$quotas[$target->id] = $this->followerTargetFor($campaignKey, $target->id, $followerTargets);
		}

		return compact('users', 'targets', 'quotas');
	}

	/**
	 * @param array<int, int> $testUserIds
	 * @param array<int, int> $quotas
	 * @param callable(User, int, array{added: int, existing: int, promoted: int, failed: bool}): void|null $progress
	 * @return array{targets: int, added: int, existing: int, promoted: int, failed: int}
	 */
	public function publish(
		string $campaignKey,
		array $testUserIds,
		Collection $targets,
		array $quotas,
		?callable $progress = null,
	): array {
		$summary = [
			'targets' => 0,
			'added' => 0,
			'existing' => 0,
			'promoted' => 0,
			'failed' => 0,
		];

		foreach ($targets as $target) {
			try {
				$result = $this->syncTarget($campaignKey, $testUserIds, $target, $quotas[$target->id]);
				$summary['targets']++;
				$summary['added'] += $result['added'];
				$summary['existing'] += $result['existing'];
				$summary['promoted'] += $result['promoted'];
				$progress?->__invoke($target, $quotas[$target->id], $result);
			} catch (Throwable $exception) {
				$summary['failed']++;
				$progress?->__invoke($target, $quotas[$target->id], [
					'added' => 0,
					'existing' => 0,
					'promoted' => 0,
					'failed' => true,
				]);
				report($exception);
			}
		}

		return $summary;
	}

	/** @param array<int, int> $testUserIds */
	public function synchronizeCounts(array $testUserIds): void
	{
		if ($testUserIds === []) {
			return;
		}

		foreach (array_chunk($testUserIds, 500) as $userIds) {
			DB::table(Table::USERS)
				->whereIn('id', $userIds)
				->update([
					'followers_count' => DB::raw("(SELECT COUNT(*) FROM ".Table::FOLLOWS." WHERE following_id = ".Table::USERS.".id AND status = '".FollowStatus::FOLLOWING->value."')"),
					'following_count' => DB::raw("(SELECT COUNT(*) FROM ".Table::FOLLOWS." WHERE follower_id = ".Table::USERS.".id AND status = '".FollowStatus::FOLLOWING->value."')"),
				]);
		}
	}

	public function availableTestFollowerPoolFor(User $target): int
	{
		return count($this->activeTestFollowerIdsFor($target));
	}

	public function currentTestFollowerCountFor(User $target): int
	{
		return (int) DB::table(Table::FOLLOWS.' as follows')
			->join(Table::USERS.' as follower_users', 'follower_users.id', '=', 'follows.follower_id')
			->where('follows.following_id', $target->id)
			->where('follows.status', FollowStatus::FOLLOWING->value)
			->where('follower_users.status', UserStatus::ACTIVE->value)
			->whereRaw('LOWER(follower_users.email) LIKE ?', ['%.test'])
			->count();
	}

	/**
	 * @return array{
	 *     target: int,
	 *     available: int,
	 *     current: int,
	 *     added: int,
	 *     existing: int,
	 *     promoted: int,
	 *     removed: int
	 * }
	 */
	public function syncExactFollowersForUser(User $target, int $targetCount): array
	{
		return DB::transaction(function () use ($target, $targetCount) {
			$lockedTarget = User::query()
				->whereKey($target->id)
				->lockForUpdate()
				->firstOrFail();
			$eligibleFollowerIds = $this->activeTestFollowerIdsFor($lockedTarget);
			$available = count($eligibleFollowerIds);

			if ($targetCount < 0 || $targetCount > $available) {
				throw new \InvalidArgumentException('The requested follower target is outside the eligible account range.');
			}

			$selectedFollowerIds = $targetCount > 0
				? $this->rotatingUsers($eligibleFollowerIds, $targetCount, "admin:test-followers:{$lockedTarget->id}")
				: [];
			$selectedFollowerLookup = array_fill_keys($selectedFollowerIds, true);
			$existingTestFollows = DB::table(Table::FOLLOWS.' as follows')
				->join(Table::USERS.' as follower_users', 'follower_users.id', '=', 'follows.follower_id')
				->where('follows.following_id', $lockedTarget->id)
				->whereRaw('LOWER(follower_users.email) LIKE ?', ['%.test'])
				->lockForUpdate()
				->get([
					'follows.follower_id',
					'follows.status',
				])
				->keyBy('follower_id');
			$toInsert = [];
			$toPromote = [];
			$existingFollowing = 0;

			foreach ($selectedFollowerIds as $followerId) {
				$follow = $existingTestFollows->get($followerId);

				if (! $follow) {
					$toInsert[] = $followerId;
					continue;
				}

				if ($follow->status === FollowStatus::FOLLOWING->value) {
					$existingFollowing++;
				} else {
					$toPromote[] = $followerId;
				}
			}

			$toRemove = $existingTestFollows->keys()
				->reject(fn ($followerId) => isset($selectedFollowerLookup[(int) $followerId]))
				->map(fn ($followerId) => (int) $followerId)
				->values()
				->all();
			$toRemoveFollowing = $existingTestFollows
				->filter(fn ($follow, $followerId) => ! isset($selectedFollowerLookup[(int) $followerId]) && $follow->status === FollowStatus::FOLLOWING->value)
				->keys()
				->map(fn ($followerId) => (int) $followerId)
				->values()
				->all();
			$now = now();
			$added = 0;

			foreach (array_chunk($toInsert, 500) as $followerChunk) {
				$rows = array_map(fn (int $followerId) => [
					'follower_id' => $followerId,
					'following_id' => $lockedTarget->id,
					'status' => FollowStatus::FOLLOWING->value,
					'created_at' => $now,
					'updated_at' => $now,
				], $followerChunk);
				$added += DB::table(Table::FOLLOWS)->insertOrIgnore($rows);
			}

			if ($toPromote !== []) {
				DB::table(Table::FOLLOWS)
					->where('following_id', $lockedTarget->id)
					->whereIn('follower_id', $toPromote)
					->where('status', '!=', FollowStatus::FOLLOWING->value)
					->update([
						'status' => FollowStatus::FOLLOWING->value,
						'updated_at' => $now,
					]);
			}

			$this->adjustFollowingCounts($toInsert, 1);
			$this->adjustFollowingCounts($toPromote, 1);

			$removed = 0;

			foreach (array_chunk($toRemove, 500) as $followerChunk) {
				$removed += DB::table(Table::FOLLOWS)
					->where('following_id', $lockedTarget->id)
					->whereIn('follower_id', $followerChunk)
					->delete();
			}

			$this->adjustFollowingCounts($toRemoveFollowing, -1);
			$this->synchronizeFollowerCountForUser($lockedTarget->id);

			return [
				'target' => $targetCount,
				'available' => $available,
				'current' => $targetCount,
				'added' => $added,
				'existing' => $existingFollowing,
				'promoted' => count($toPromote),
				'removed' => $removed,
			];
		}, 3);
	}

	/**
	 * Synchronize the cached counts from this campaign's stable relationship plan.
	 *
	 * @param array<int, int> $testUserIds
	 * @param array<int, int> $followerTargets
	 */
	public function synchronizeCampaignCounts(string $campaignKey, array $testUserIds, array $followerTargets): void
	{
		if ($testUserIds === []) {
			return;
		}

		$totalUsers = count($testUserIds);

		if ($totalUsers - 1 < max($followerTargets)) {
			throw new \RuntimeException('There are not enough active .test accounts for the requested follower target.');
		}

		$userPositions = array_flip($testUserIds);
		$followerCounts = [];
		$followingCounts = array_fill_keys($testUserIds, 0);

		foreach ($testUserIds as $targetId) {
			$quota = $this->followerTargetFor($campaignKey, $targetId, $followerTargets);
			$followerCounts[$targetId] = $quota;
			$targetIndex = $userPositions[$targetId];
			$eligibleTotal = $totalUsers - 1;
			$start = $this->index("{$campaignKey}:{$targetId}:follower-users", $eligibleTotal);

			for ($offset = 0; $offset < $quota; $offset++) {
				$eligibleIndex = ($start + $offset) % $eligibleTotal;
				$userIndex = $eligibleIndex >= $targetIndex ? $eligibleIndex + 1 : $eligibleIndex;
				$followingCounts[$testUserIds[$userIndex]]++;
			}
		}

		foreach (array_chunk($testUserIds, 500) as $userIds) {
			$followerCases = [];
			$followingCases = [];

			foreach ($userIds as $userId) {
				$followerCases[] = "WHEN {$userId} THEN {$followerCounts[$userId]}";
				$followingCases[] = "WHEN {$userId} THEN {$followingCounts[$userId]}";
			}

			DB::table(Table::USERS)
				->whereIn('id', $userIds)
				->update([
					'followers_count' => DB::raw('CASE id '.implode(' ', $followerCases).' END'),
					'following_count' => DB::raw('CASE id '.implode(' ', $followingCases).' END'),
				]);
		}
	}

	/** @param array<int, int> $followerTargets */
	private function followerTargetFor(string $campaignKey, int $userId, array $followerTargets): int
	{
		if ($followerTargets === []) {
			throw new \InvalidArgumentException('At least one follower target is required.');
		}

		return $followerTargets[$this->index("{$campaignKey}:{$userId}:follower-target", count($followerTargets))];
	}

	/**
	 * @param array<int, int> $testUserIds
	 * @return array{added: int, existing: int, promoted: int, failed: bool}
	 */
	private function syncTarget(string $campaignKey, array $testUserIds, User $target, int $quota): array
	{
		return DB::transaction(function () use ($campaignKey, $testUserIds, $target, $quota) {
			$lockedTarget = User::query()
				->active()
				->whereRaw('LOWER(email) LIKE ?', ['%.test'])
				->whereKey($target->id)
				->lockForUpdate()
				->firstOrFail();
			$eligibleFollowerIds = array_values(array_filter(
				$testUserIds,
				fn (int $userId) => $userId !== (int) $lockedTarget->id,
			));

			if (count($eligibleFollowerIds) < $quota) {
				throw new \RuntimeException('There are not enough active .test accounts for the requested follower target.');
			}

			$followerIds = $this->rotatingUsers(
				$eligibleFollowerIds,
				$quota,
				"{$campaignKey}:{$lockedTarget->id}:follower-users",
			);
			$existing = DB::table(Table::FOLLOWS)
				->where('following_id', $lockedTarget->id)
				->whereIn('follower_id', $followerIds)
				->lockForUpdate()
				->get(['follower_id', 'status'])
				->keyBy('follower_id');
			$toInsert = [];
			$toPromote = [];
			$existingFollowing = 0;

			foreach ($followerIds as $followerId) {
				$follow = $existing->get($followerId);

				if (! $follow) {
					$toInsert[] = $followerId;
					continue;
				}

				if ($follow->status === FollowStatus::FOLLOWING->value) {
					$existingFollowing++;
				} else {
					$toPromote[] = $followerId;
				}
			}

			$now = now();
			$added = 0;

			foreach (array_chunk($toInsert, 500) as $followerChunk) {
				$rows = array_map(fn (int $followerId) => [
					'follower_id' => $followerId,
					'following_id' => $lockedTarget->id,
					'status' => FollowStatus::FOLLOWING->value,
					'created_at' => $now,
					'updated_at' => $now,
				], $followerChunk);
				$added += DB::table(Table::FOLLOWS)->insertOrIgnore($rows);
			}

			if ($toPromote !== []) {
				DB::table(Table::FOLLOWS)
					->where('following_id', $lockedTarget->id)
					->whereIn('follower_id', $toPromote)
					->where('status', '!=', FollowStatus::FOLLOWING->value)
					->update([
						'status' => FollowStatus::FOLLOWING->value,
						'updated_at' => $now,
					]);
			}

			return [
				'added' => $added,
				'existing' => $existingFollowing,
				'promoted' => count($toPromote),
				'failed' => false,
			];
		}, 3);
	}

	/**
	 * @param array<int, int> $userIds
	 * @return array<int, int>
	 */
	private function rotatingUsers(array $userIds, int $target, string $seed): array
	{
		$total = count($userIds);

		if ($target < 1 || $target > $total) {
			throw new \InvalidArgumentException('The requested follower target is outside the eligible account range.');
		}

		$selected = [];
		$start = $this->index($seed, $total);

		for ($offset = 0; $offset < $target; $offset++) {
			$selected[] = $userIds[($start + $offset) % $total];
		}

		return $selected;
	}

	/** @return array<int, int> */
	private function testUserIds(): array
	{
		return User::query()
			->active()
			->whereRaw('LOWER(email) LIKE ?', ['%.test'])
			->orderBy('id')
			->pluck('id')
			->map(fn ($id) => (int) $id)
			->all();
	}

	/** @return array<int, int> */
	private function activeTestFollowerIdsFor(User $target): array
	{
		return User::query()
			->active()
			->whereRaw('LOWER(email) LIKE ?', ['%.test'])
			->when($this->isTestEmail($target->email), fn ($query) => $query->whereKeyNot($target->id))
			->orderBy('id')
			->pluck('id')
			->map(fn ($id) => (int) $id)
			->all();
	}

	/** @return Collection<int, User> */
	private function testUsersAfter(int $afterId, int $targetLimit): Collection
	{
		return User::query()
			->active()
			->whereRaw('LOWER(email) LIKE ?', ['%.test'])
			->where('id', '>', $afterId)
			->orderBy('id')
			->limit($targetLimit)
			->get();
	}

	private function index(string $seed, int $length): int
	{
		if ($length < 1) {
			throw new \InvalidArgumentException('The target collection cannot be empty.');
		}

		return (int) (sprintf('%u', crc32($seed)) % $length);
	}

	private function isTestEmail(string $email): bool
	{
		return str_ends_with(strtolower($email), '.test');
	}

	/** @param array<int, int> $userIds */
	private function adjustFollowingCounts(array $userIds, int $delta): void
	{
		if ($userIds === [] || $delta === 0) {
			return;
		}

		foreach (array_chunk(array_values(array_unique($userIds)), 500) as $chunk) {
			$query = DB::table(Table::USERS)->whereIn('id', $chunk);

			if ($delta > 0) {
				$query->increment('following_count', $delta);
				continue;
			}

			$decrement = abs($delta);

			$query->update([
				'following_count' => DB::raw("CASE WHEN following_count <= {$decrement} THEN 0 ELSE following_count - {$decrement} END"),
			]);
		}
	}

	private function synchronizeFollowerCountForUser(int $userId): void
	{
		DB::table(Table::USERS)
			->where('id', $userId)
			->update([
				'followers_count' => DB::raw("(SELECT COUNT(*) FROM ".Table::FOLLOWS." WHERE following_id = ".Table::USERS.".id AND status = '".FollowStatus::FOLLOWING->value."')"),
			]);
	}
}
