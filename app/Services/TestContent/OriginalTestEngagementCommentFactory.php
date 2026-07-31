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

		$context = $this->contextFor($post);
		$openers = $context['openers'];
		$reflections = $context['reflections'];
		$closers = $context['closers'];

		$seed = "{$campaignKey}:{$user->id}:{$post->id}";
		$opener = $openers[$this->deterministicIndex($seed.':opener', count($openers))];
		$reflection = $reflections[$this->deterministicIndex($seed.':reflection', count($reflections))];
		$closer = $closers[$this->deterministicIndex($seed.':closer', count($closers))];

		return sprintf($opener, $topic).' '.$reflection.' '.$closer;
	}

	/**
	 * @return array{openers: array<int, string>, reflections: array<int, string>, closers: array<int, string>}
	 */
	private function contextFor(Post $post): array
	{
		$haystack = Str::lower(strip_tags(trim(($post->title ?: '').' '.($post->content ?: ''))));

		$contexts = [
			'technology' => [
				'keywords' => ['ai', 'tech', 'software', 'app', 'cloud', 'digital', 'google', 'device', 'startup', 'data'],
				'openers' => [
					'Great perspective on %s.',
					'This tech-focused update feels sharp and current.',
					'Helpful framing around %s.',
					'This is a strong technology share.',
				],
				'reflections' => [
					'The practical angle makes the topic easier to apply in real work.',
					'It balances innovation with a realistic takeaway.',
					'This kind of framing helps the subject feel more useful than abstract.',
					'The message lands well because it connects ideas to execution.',
				],
				'closers' => [
					'Thanks for sharing the insight.',
					'Looking forward to more updates like this.',
					'This is worth keeping in the conversation.',
					'A genuinely useful read.',
				],
			],
			'business' => [
				'keywords' => ['business', 'market', 'growth', 'brand', 'client', 'strategy', 'sales', 'finance', 'team', 'leader'],
				'openers' => [
					'This business angle is very well presented.',
					'Strong perspective on %s.',
					'This feels relevant and timely.',
					'Clear thinking around %s.',
				],
				'reflections' => [
					'The strategic point is easy to understand and easy to carry forward.',
					'It highlights the value of steady execution, not just big ideas.',
					'There is a balanced, professional tone here that adds credibility.',
					'The message feels useful for anyone building momentum over time.',
				],
				'closers' => [
					'Appreciate the clear breakdown.',
					'This adds real value to the discussion.',
					'Worth revisiting later.',
					'Thanks for sharing the perspective.',
				],
			],
			'lifestyle' => [
				'keywords' => ['life', 'daily', 'health', 'fitness', 'travel', 'home', 'family', 'wellness', 'food', 'routine'],
				'openers' => [
					'This was a refreshing share on %s.',
					'Warm and relatable perspective here.',
					'Nicely shared.',
					'This lifestyle update feels very natural.',
				],
				'reflections' => [
					'The tone makes the idea feel approachable and easy to relate to.',
					'It is the kind of reminder people can genuinely carry into daily life.',
					'There is a calm clarity here that works really well.',
					'This adds something grounded to the feed.',
				],
				'closers' => [
					'Thanks for sharing it.',
					'This was pleasant to read.',
					'Looking forward to more posts like this.',
					'A thoughtful contribution.',
				],
			],
			'marketplace' => [
				'keywords' => ['price', 'product', 'sale', 'deal', 'shop', 'marketplace', 'order', 'service', 'seller', 'offer'],
				'openers' => [
					'This offer is presented very clearly.',
					'Good detail around %s.',
					'This product-focused post feels easy to evaluate.',
					'Useful marketplace-style update.',
				],
				'reflections' => [
					'The practical information helps people make a faster decision.',
					'It is easy to understand what makes the offer worthwhile.',
					'The post keeps the message focused, which works well here.',
					'There is a nice balance between clarity and usefulness.',
				],
				'closers' => [
					'Thanks for laying it out so well.',
					'This is easy to scan and understand.',
					'A helpful listing-style post.',
					'Nicely structured update.',
				],
			],
		];

		foreach ($contexts as $context) {
			foreach ($context['keywords'] as $keyword) {
				if (str_contains($haystack, $keyword)) {
					unset($context['keywords']);

					return $context;
				}
			}
		}

		return [
			'openers' => [
				'This is thoughtfully presented.',
				'Clear, balanced, and easy to reflect on.',
				'A strong and relevant share.',
				'Well said.',
				'This stood out to me.',
				'An encouraging update with a practical message.',
			],
			'reflections' => [
				'The practical direction makes the idea genuinely useful.',
				'It is a good reminder that consistent small steps create progress.',
				'The point is especially useful when applied to everyday decisions.',
				'Turning a good idea into a habit is where its value grows.',
				'Good ideas become more meaningful when they are explained this clearly.',
				'The takeaway feels both timely and practical.',
			],
			'closers' => [
				'Thanks for sharing it.',
				'Looking forward to more perspectives like this.',
				'It is a useful conversation to keep going.',
				'A thoughtful contribution.',
				'Appreciate the measured approach.',
				'Well worth the read.',
			],
		];
	}

	private function deterministicIndex(string $seed, int $length): int
	{
		return (int) (sprintf('%u', crc32($seed)) % $length);
	}
}
