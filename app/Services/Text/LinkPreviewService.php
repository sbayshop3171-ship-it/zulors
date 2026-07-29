<?php

namespace App\Services\Text;

use Exception;
use InvalidArgumentException;
use App\Services\Text\Drivers\PHPEmbed;
use App\Services\Text\Drivers\Interfaces\LinkPreviewerInterface;

class LinkPreviewService
{
	protected LinkPreviewerInterface $driver;

	public function __construct() {
		$this->setDriver(config('post.link_preview.driver'));
	}
	
	public function previewLink(string $url): array
	{
		try {
			return $this->driver->preview($url);
		}

		catch (Exception $e) {
			return [
				'title' => $url,
				'description' => __('labels.link_preview_fallback'),
				'preview_image_base64' => null,
				'url' => $url,
				'is_fallback' => true
			];
		}
	}

	public function setDriver(string $driver) {
		$drivers = [
			'php-embed' => PHPEmbed::class,
		];

		if (isset($drivers[$driver])) {
			$this->driver = new $drivers[$driver];
		}

		else{
			throw new InvalidArgumentException('Invalid Link preview driver');
		}
	}
}