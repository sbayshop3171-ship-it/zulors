<?php

namespace App\Support\Views;

use App\Enums\Flash\FlashType;

class Flash
{
	private string $title;
	private string $content;
	private FlashType $type = FlashType::SUCCESS;

	public function __construct(string $title = '', string $content = '', FlashType $type = FlashType::SUCCESS)
	{
		$this->title = $title;
		$this->content = $content;
		$this->type = $type;

		return $this;
	}

	public function get(): array
	{
		return [
			'title' => $this->title,
			'content' => $this->content,
			'type' => $this->type->value,
		];
	}
}
