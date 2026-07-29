<?php

namespace App\Actions\Media;

use Throwable;
use App\Models\Media;
use App\Constants\Filesystem;
use Illuminate\Support\Facades\Storage;

class DeleteMediaAction
{
	private Media $mediaData;

	public function __construct(Media $mediaData)
	{
		$this->mediaData = $mediaData;
	}

	public function execute()
	{
		try {
			if($this->mediaData->disk !== Filesystem::EXTERNAL_DISK_NAME) {
	
				if(! empty($this->mediaData->thumbnail_path)) {
					Storage::disk($this->mediaData->thumbnail_disk)->delete($this->mediaData->thumbnail_path);
				}
	
				// Delete media main file
				if ($this->mediaData->status->isProcessing()) {
					if(data_get($this->mediaData->metadata, 'provider') === 'r2_temp') {
						Storage::disk($this->mediaData->disk)->delete($this->mediaData->source_path);
					}

					else {
						Storage::disk('local')->delete($this->mediaData->source_path);
					}
				}
	
				else {
					Storage::disk($this->mediaData->disk)->delete($this->mediaData->source_path);
				}
			}
	
			$this->mediaData->delete();
		} catch (Throwable $th) {
			logger()->error('Error while deleting media: ' . $th->getMessage(), [
				'type' => $this->mediaData->mediaable_type,
				'id' => $this->mediaData->mediaable_id
			]);
		}
	}
}
