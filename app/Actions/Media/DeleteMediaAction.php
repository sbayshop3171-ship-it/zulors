<?php

namespace App\Actions\Media;

use Throwable;
use App\Models\Media;
use App\Constants\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

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
	
				if(! empty($this->mediaData->thumbnail_path) && ! $this->isAssetReferenced($this->mediaData->thumbnail_disk, $this->mediaData->thumbnail_path)) {
					Storage::disk($this->mediaData->thumbnail_disk)->delete($this->mediaData->thumbnail_path);
				}
	
				// Delete media main file
				if ($this->isAssetReferenced($this->mediaData->disk, $this->mediaData->source_path)) {
					// Forwarded messages can share immutable provider assets when the
					// storage driver cannot make an independent copy.
				}
				else if ($this->mediaData->status->isProcessing()) {
					if(in_array(data_get($this->mediaData->metadata, 'provider'), ['r2_temp', 'r2_direct'], true)) {
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

	private function isAssetReferenced(string $disk, ?string $path): bool
	{
		if (empty($disk) || empty($path)) {
			return false;
		}

		return DB::table('media')
			->where('id', '!=', $this->mediaData->id)
			->where(function ($query) use ($disk, $path): void {
				$query->where(function ($sourceQuery) use ($disk, $path): void {
					$sourceQuery->where('disk', $disk)->where('source_path', $path);
				})->orWhere(function ($thumbnailQuery) use ($disk, $path): void {
					$thumbnailQuery->where('thumbnail_disk', $disk)->where('thumbnail_path', $path);
				});
			})
			->exists();
	}
}
