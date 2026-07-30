<?php

namespace App\Services\TestContent;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Str;

class OriginalTestEngagementCommentFactory
{
	/**
	 * Create deterministic, original test copy without importing or reproducing third-party content.
	 */
	public function make(User $user, Post $post, string $campaignKey): string
	{
		$topic = Str::of(strip_tags((string) ($post->title ?: $post->content)))
			->squish()
			->limit(76, '...')
			->toString();

		if ($topic === '') {
			$topic = 'this update';
		}

		$templates = [
			'Great perspective on %s. The practical direction makes the idea feel genuinely useful.',
			'This is thoughtfully presented. %s is a reminder that consistent small steps can create real progress.',
			'Clear, balanced, and easy to reflect on. The point about %s is especially well made.',
			'I appreciate the useful tone here. Turning %s into an everyday habit is where the value grows.',
			'A strong and relevant share. The message around %s gives people something constructive to take away.',
			'Well said. Good ideas become more meaningful when they are explained as clearly as %s is here.',
			'This stood out to me. A focused approach to %s can make a noticeable difference over time.',
			'An encouraging update with a practical message. Thanks for bringing attention to %s.',
			'Useful insight and a clear takeaway. %s is the kind of topic worth revisiting.',
			'The perspective feels grounded and timely. There is a lot to learn from the way %s is framed here.',
			'Nicely shared. It is refreshing to see %s discussed with both clarity and purpose.',
			'A valuable reminder that good results usually come from intention, patience, and a focus on %s.',
		];

		$index = $this->deterministicIndex($campaignKey, $user->id, $post->id, count($templates));

		return sprintf($templates[$index], $topic);
	}

	private function deterministicIndex(string $campaignKey, int $userId, int $postId, int $length): int
	{
		return (int) (sprintf('%u', crc32("{$campaignKey}:{$userId}:{$postId}")) % $length);
	}
}
