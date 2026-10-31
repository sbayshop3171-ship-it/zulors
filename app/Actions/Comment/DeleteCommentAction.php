<?php

namespace App\Actions\Comment;

use App\Models\Comment;
use App\Actions\Media\DeleteMediaAction;
use Illuminate\Support\Facades\DB;

class DeleteCommentAction
{
	private Comment $commentData;

	public function __construct(Comment $commentData)
	{
		$this->commentData = $commentData;
	}

	public function execute()
	{
		DB::transaction(function () {
			$levels = [];
			$frontier = [$this->commentData->id];
			$visited = [];
			while ($frontier !== []) {
				$frontier = array_values(array_filter($frontier, function ($id) use (&$visited) {
					if (isset($visited[$id])) {
						return false;
					}
					$visited[$id] = true;

					return true;
				}));
				if ($frontier === []) {
					break;
				}
				$levels[] = $frontier;
				$next = [];
				foreach (array_chunk($frontier, 500) as $parentIds) {
                    $childIds = Comment::where('post_id', $this->commentData->post_id)
                        ->whereIn('parent_id', $parentIds)
                        ->pluck('id')->all();
					$next = array_merge($next, $childIds);
				}
				$frontier = $next;
			}

			foreach (array_reverse($levels) as $ids) {
				foreach (array_chunk($ids, 200) as $chunk) {
					Comment::whereIn('id', $chunk)->get()->each(function (Comment $comment) {
						$comment->reactions()->delete();
						$comment->reports()->delete();
						$comment->media()->get()->each(function ($media) {
							$media->delete();
							DB::afterCommit(fn () => (new DeleteMediaAction($media))->execute());
						});
						$comment->delete();
					});
				}
			}
		});
	}
}
