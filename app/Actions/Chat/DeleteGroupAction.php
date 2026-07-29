<?php

namespace App\Actions\Chat;

use Throwable;
use App\Models\Group;
use Illuminate\Support\Facades\Storage;

class DeleteGroupAction
{
	private Group $groupData;

	public function __construct(Group $groupData)
	{
		$this->groupData = $groupData;
	}

	public function execute()
	{
		if(! $this->groupData->hasDefaultAvatar() && ! empty($this->groupData->avatar)) {
			try {
				Storage::disk(static_storage_disk())->delete($this->groupData->avatar);
			} catch (Throwable $th) {
				chat_log($th->getMessage());
			}
		}

		$this->groupData->reports()->delete();

		$this->groupData->delete();
	}
}
