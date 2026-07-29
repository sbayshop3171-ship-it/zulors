<?php

namespace App\Services\Reaction\Interfaces;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

interface ReactionServiceInterface
{
	public function handleReaction(): bool;

	public function setReactable(Model $reactable): self;

	public function setUserId(int $id): self;

	public function setUnifiable(string $unifiedId): self;
}