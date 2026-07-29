<?php

namespace App\Actions\Ad;

use App\Models\Ad;
use App\Actions\Media\DeleteMediaAction;

class DeleteAdAction
{
	private $adData;

	public function __construct(Ad $adData)
	{
		$this->adData = $adData;
	}
	
	public function execute()
	{
		(new RefundUnusedAdBudgetAction($this->adData))->execute();

		$this->adData->media->each(function ($mediaItem) {
			(new DeleteMediaAction($mediaItem))->execute();
		});

		$this->adData->delete();
	}
}
