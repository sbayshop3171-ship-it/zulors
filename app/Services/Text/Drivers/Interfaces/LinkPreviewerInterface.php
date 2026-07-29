<?php

namespace App\Services\Text\Drivers\Interfaces;

interface LinkPreviewerInterface
{
	public function preview(string $url): array;
}