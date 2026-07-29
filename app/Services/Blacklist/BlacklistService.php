<?php

namespace App\Services\Blacklist;

use App\Models\Blacklist;
use App\Enums\BlacklistType;
use Illuminate\Support\Facades\Cache;

class BlacklistService
{
	private BlacklistType $type;

	private Blacklist $model;

	private string $reason;

	public function __construct() {
		$this->model = new Blacklist();
	}
	
	public function setType(BlacklistType $type)
	{
		$this->type = $type;

		return $this;
	}

	public function add(string $blacklistable)
	{
		if ($this->isBlacklisted($this->type, $blacklistable)) {
			return false;
		}

		$this->model->create([
			'type' => $this->type,
			'added_at' => now(),
			'blacklistable' => $blacklistable,
			'reason' => (empty($this->reason)) ? null : $this->reason,
			'expires_at' => null,
			'admin_id' => me()->id
		]);

		Cache::forget("blacklisted_{$this->type->value}");

		return true;
	}

	public function setReason(string $reason)
	{
		$this->reason = $reason;

		return $this;
	}

	public function getBlacklistedIps(): array
	{
		return $this->getBlacklistedByType(BlacklistType::IP);
	}

	public function getBlacklistedEmails(): array
	{
		return $this->getBlacklistedByType(BlacklistType::EMAIL);
	}

	public function isEmailBlacklisted(string $email)
	{
		return $this->isBlacklisted(BlacklistType::EMAIL, $email);
	}

	public function isIpBlacklisted(string $email)
	{
		return $this->isBlacklisted(BlacklistType::EMAIL, $email);
	}

	private function isBlacklisted(BlacklistType $type, string $blacklistable)
	{
		return $this->model->where('type', $type)->where('blacklistable', $blacklistable)->exists();
	}

	private function getBlacklistedByType(BlacklistType $type)
	{
		$type = $type->value;
		$key = "blacklisted_{$type}";

		return Cache::get($key, function() use ($key, $type) {
			$blackList = [];
            $listCollection = $this->model->where('type', $type)->select('blacklistable')->get();
			
			if($listCollection->isNotEmpty()) {
				$blackList = $listCollection->pluck('blacklistable')->toArray();
			}

			Cache::rememberForever($key, function() use ($blackList) {
				return $blackList;
			});

			return $blackList;
        });
	}
}
