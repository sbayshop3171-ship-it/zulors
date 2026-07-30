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
			->limit(72, '...')
			->toString();

		if ($topic === '') {
			$topic = 'this update';
		}

		$openers = [
			'Great perspective on %s.',
			'This is thoughtfully presented.',
			'Clear, balanced, and easy to reflect on.',
			'A strong and relevant share.',
			'Well said.',
			'This stood out to me.',
			'An encouraging update with a practical message.',
			'Useful insight and a clear takeaway.',
			'The perspective feels grounded and timely.',
			'Nicely shared.',
			'This gives the topic real context.',
			'The message is focused and approachable.',
		];

		$reflections = [
			'The practical direction makes the idea genuinely useful.',
			'It is a good reminder that consistent small steps create progress.',
			'The point is especially useful when applied to everyday decisions.',
			'Turning a good idea into a habit is where its value grows.',
			'It gives people something constructive to take away.',
			'Good ideas become more meaningful when they are explained this clearly.',
			'A focused approach can make a noticeable difference over time.',
			'The takeaway feels both timely and practical.',
			'This is the kind of point worth revisiting.',
			'There is a lot to learn from the way the subject is framed.',
		];

		$closers = [
			'Thanks for sharing it.',
			'Looking forward to more perspectives like this.',
			'It is a useful conversation to keep going.',
			'This adds a welcome note of clarity.',
			'A thoughtful contribution.',
			'The detail makes the update stronger.',
			'Appreciate the measured approach.',
			'Worth saving for later reflection.',
			'This is a helpful reminder.',
			'Well worth the read.',
		];

		$seed = "{$campaignKey}:{$user->id}:{$post->id}";
		$opener = $openers[$this->deterministicIndex($seed.':opener', count($openers))];
		$reflection = $reflections[$this->deterministicIndex($seed.':reflection', count($reflections))];
		$closer = $closers[$this->deterministicIndex($seed.':closer', count($closers))];

		return sprintf($opener, $topic).' '.$reflection.' '.$closer;
	}

	private function deterministicIndex(string $seed, int $length): int
	{
		return (int) (sprintf('%u', crc32($seed)) % $length);
	}
}
