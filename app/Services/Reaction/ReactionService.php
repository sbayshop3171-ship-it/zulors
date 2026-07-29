<?php

namespace App\Services\Reaction;

use Throwable;
use App\Models\Reaction;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use App\Services\Reaction\Interfaces\ReactionServiceInterface;

class ReactionService implements ReactionServiceInterface
{
	private $reactable;
	private $userId;
	private $unifiedId;

	public function setUnifiable(string $unifiedId): self
	{
		$this->unifiedId = $unifiedId;

		return $this;
	}

	public function setReactable(Model $reactable): self
	{
		$this->reactable = $reactable;

		return $this;
	}

	public function setUserId(int $id): self
	{
		$this->userId = $id;

		return $this;
	}

	public function handleReaction(): bool
	{
		try {
			$this->validateReaction();
			
			$reactionData = $this->reactable->reactions()->where('unified_id', $this->unifiedId)->first();

            if($reactionData) {
                return $this->toggleReaction($reactionData);
            }
            else{
                $this->addNewReaction();

				$this->undoOtherReactions();

				return true;
            }
		}
		catch (Throwable $th) {
			throw $th;
		}
	}

	private function addNewReaction()
	{
		$this->reactable->reactions()->create([
			'unified_id' => $this->unifiedId,
			'users' => [$this->userId],
			'reactions_count' => 1
		]);
	}

	private function toggleReaction(Reaction $reactionData)
	{
		$reactedUsers = collect($reactionData['users']);

		if($reactedUsers->contains(me()->id)) {
			$reactionData['users'] = $reactedUsers->filter(function ($userId) {
				return $userId !== $this->userId;
			})->toArray();

			if(empty($reactionData['users'])) {
				$reactionData->delete();
			}
			else{
				$reactionData->reactions_count = count($reactionData['users']);
				$reactionData->save();
			}

			return false;
		}
		else{
			$reactedUsers->add($this->userId);

			$reactionData['users'] = $reactedUsers->toArray();
			$reactionData->reactions_count = count($reactionData['users']);
			$reactionData->save();

			$this->undoOtherReactions();

			return true;
		}
	}

	private function undoOtherReactions()
	{
		if(empty(config('post.reactions.allow_multiple_reactions'))) {
			$otherReactions = $this->reactable->reactions()->whereNot('unified_id', $this->unifiedId)->get();
	
			if(! $otherReactions->isEmpty()) {
				$otherReactions->each(function($item) {
					$reactedUsers = collect($item['users']);
	
					if($reactedUsers->contains(me()->id)) {
						$item['users'] = $reactedUsers->filter(function ($userId) {
							return $userId !== $this->userId;
						})->toArray();
	
						if(empty($item['users'])) {
							$item->delete();
						}
						else{
							$item->reactions_count = count($item['users']);
							$item->save();
						}
					}
				});
			}
		}
	}

	private function validateReaction()
	{
		$reactionImagePath = public_path("assets/emoji/img-apple-64/{$this->unifiedId}.png");

		if(! file_exists($reactionImagePath)) {
			throw new InvalidArgumentException("Reaction Unified ID is invalid. At path: {$reactionImagePath}");
		}
	}
}