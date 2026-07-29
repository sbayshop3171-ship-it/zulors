<?php

namespace App\Services\Censor;

use App\Models\User;
use App\Models\Censor;
use App\Enums\BlacklistType;
use App\Events\Admin\User\UserBannedEvent;
use Illuminate\Support\Facades\Cache;
use App\Services\Blacklist\BlacklistService;

class CensorService
{
	private User $userData;
	
	private array $bannedWords;

	public function __construct() {
		$this->bannedWords = $this->getBannedWords();
	}

	public function setUser(User $userData)
	{
		$this->userData = $userData;

		return $this;
	}

	public function censor(string $text): void
	{
		foreach($this->bannedWords as $word) {
			if(str_contains($text, $word)) {
				$this->banUser();
			}
		}
	}

	private function getBannedWords(): array
	{
		return Cache::get('censor_banned_words', function() {
			$words = Censor::banned()->get()->pluck('word')->toArray();

			Cache::rememberForever('censor_banned_words', function() use ($words) {
				return $words;
			});

			return $words;
		});
	}

	private function banUser()
	{
		$blacklistService = app(BlacklistService::class);

		$blacklistService->setType(BlacklistType::IP)->add($this->userData->ip_address);
        $blacklistService->setType(BlacklistType::EMAIL)->add($this->userData->email);

		event(new UserBannedEvent($this->userData));
	}
}
