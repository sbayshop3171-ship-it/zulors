<?php

namespace App\Actions\Comment;

use Throwable;
use App\Models\Comment;

class DeleteCommentAction
{
	private Comment $commentData;

	public function __construct(Comment $commentData)
	{
		$this->commentData = $commentData;
	}

	public function execute()
	{
		try {
			$this->commentData->reactions()->delete();
			$this->commentData->replies()->delete();
			$this->commentData->delete();
		} catch (Throwable $th) {
			timeline_log($th->getMessage());
		}
	}
}
