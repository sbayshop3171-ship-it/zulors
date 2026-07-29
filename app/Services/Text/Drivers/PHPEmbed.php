<?php

namespace App\Services\Text\Drivers;

use Exception;
use Embed\Embed;
use Embed\Http\Crawler;
use Embed\Http\CurlClient;
use App\Services\Filesystem\Base64Image\Base64ImageService;
use App\Services\Text\Drivers\Interfaces\LinkPreviewerInterface;

class PHPEmbed implements LinkPreviewerInterface
{
	private $phpEmbed;
	private $linkInfo;

	public function __construct()
	{
		$client = new CurlClient();
		$client->setSettings([
			'cookies_path' => "",
			'ignored_errors' => [18],
			'max_redirs' => 3,
			'connect_timeout' => 2,
			'timeout' => 10,
			'ssl_verify_host' => 2,
			'ssl_verify_peer' => 1,
			'follow_location' => true,
			'user_agent' => 'Mozilla'
		]);

		$this->phpEmbed = new Embed(new Crawler($client));
	}

	public function preview(string $url): array
	{
		$this->linkInfo = $this->phpEmbed->get($url);

		if (!$this->ensureTitle()) {
			throw new Exception('Failed to get link preview info.');
		}

		$lqipBase64 = null;

		if($this->ensureImage()) {
			$base64ImageService = app(Base64ImageService::class);
			
			$base64ImageService->loadFromUrl((string) $this->linkInfo->image);
			$base64ImageService->setScaleWidth(590);
			$base64ImageService->setBlurRadius(0);
			$base64ImageService->setCompressRate(20);

			$lqipBase64 = $base64ImageService->getBase64();
		}
		
		$description = $this->ensureDescription() ? $this->linkInfo->description : __('labels.link_preview_fallback');

		return [
			'title' => $this->linkInfo->title,
			'description' => $description, 
			'preview_image_base64' => $lqipBase64,
			'url' => (string) $this->linkInfo->url
		];
	}

	private function ensureImage(): bool
	{
		return $this->linkInfo->image !== null;
	}

	private function ensureTitle(): bool
	{
		return $this->linkInfo->title !== null;
	}
	
	private function ensureDescription(): bool
	{
		return $this->linkInfo->description !== null;
	}
}