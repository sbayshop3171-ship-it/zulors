<?php
/*
|--------------------------------------------------------------------------
| Zulors - The Zulors Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  Zulors. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Actions\Product;

use App\Models\Product;
use App\Actions\Media\DeleteMediaAction;

class DeleteProductAction
{
	private Product $productData;

	public function __construct(Product $productData) {
		$this->productData = $productData;
	}

	public function execute() {
		$this->productData->media()->each(function($mediaItem) {
			(new DeleteMediaAction($mediaItem))->execute();
		});

		$this->productData->delete();
	}
}
