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

namespace App\Http\Controllers\Api\User\Story;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Constants\Filesystem;
use App\Enums\Media\MediaType;
use App\Enums\Story\StoryType;
use App\Enums\Media\MediaStatus;
use Illuminate\Http\UploadedFile;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Services\Filesystem\Delete\FileDeleteService;
use App\Services\Filesystem\Upload\ImageUploadService;
use App\Services\Filesystem\Upload\VideoUploadService;
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use App\Services\Filesystem\Upload\VideoThumbnailService;
use App\Services\Filesystem\Base64Image\Base64ImageService;
use App\Traits\Http\Controllers\Api\User\Story\ValidatesStoryMedia;
use App\Traits\Http\Controllers\Api\User\Story\InteractsWithDraftStoryFrame;

class StoryMediaController extends Controller
{
    use InteractsWithDraftStoryFrame,
        ValidatesStoryMedia,
        SupportsApiResponses;

    private $roundRobinService;

    public function __construct(RoundRobinService $roundRobinService)
    {
        $this->roundRobinService = $roundRobinService;
        $this->fetchOrInitializeDraftStoryFrame();
    }

    public function uploadMedia(Request $request)
    {
        if(! $this->canAddStoryFrame()) {
            return $this->responseValidationError([
                'message' => __('story.validation.frame_count.max', ['max' => config('story.max_frames_per_story')]),
                'errors' => [
                    'media_file' => [
                        __('story.validation.frame_count.max', ['max' => config('story.max_frames_per_story')])
                    ]
                ]
            ]);
        }

        $request->validate([
            'media_file' => ['required', 'file']
        ]);

        $mediaFile = $request->file('media_file');

        $mediaType = Str::before($mediaFile->getMimeType(), '/');

        if($mediaType === 'image') {
            $this->validateStoryImage($mediaFile);

            return $this->uploadStoryImage($mediaFile);
        }
        else {
            $this->validateStoryVideo($mediaFile);

            return $this->uploadStoryVideo($mediaFile);
        }
    }

    public function deleteMedia()
    {
        $storyMedia = $this->draftStoryFrame->media->first();

        $fileDeleteService = app(FileDeleteService::class);

        if(! empty($storyMedia)) {
            if($this->draftStoryFrame->type->isImage()) {
                $fileDeleteService->setStorageDisk($storyMedia->disk)->deleteFile($storyMedia->source_path);
            }

            else if($this->draftStoryFrame->type->isVideo()) {
                $videoStorageDisk = $storyMedia->disk;

                if($this->draftStoryFrame->status->isDraft()) {
                    // Set the video disk as local, since the video has not
                    // yet been processed or uploaded to public disks.

                    $videoStorageDisk = 'local';
                }

                $fileDeleteService->setStorageDisk($videoStorageDisk)->deleteFile($storyMedia->source_path);

                // Since the thumbnail is always uploaded to public disk when the story is created,
                // we can use its public name on disk to delete it.

                $fileDeleteService->setStorageDisk($storyMedia->thumbnail_disk)->deleteFile($storyMedia->thumbnail_path);
            }
        }

        return $this->responseSuccess([
            'data' => null
        ]);
    }

    private function uploadStoryImage(UploadedFile $mediaFile)
    {
        try {
            $imageUploadService = app(ImageUploadService::class);
            $base64ImageService = app(Base64ImageService::class);

            $imageData = $imageUploadService
                ->setStorageDisk($this->roundRobinService->getNextDisk())
                ->load($mediaFile->getRealPath())
                ->setNamespace(Filesystem::mediaNamespace('stories/images'))
                ->scaleTo1080x1920()
                ->watermark()
                ->compress(config('story.processing.image.compress_rate'))
                ->upload();

            $LQIPBase64 = $base64ImageService->load($mediaFile->getRealPath())
                ->setScaleWidth(256)
                ->setBlurRadius(0)
                ->getBase64();

            $this->draftStoryFrame->type = StoryType::IMAGE;

            $this->draftStoryFrame->media()->create([
                'source_path' => $imageData['image_path'],
                'type' => MediaType::IMAGE,
                'status' => MediaStatus::PROCESSED,
                'disk' => $imageData['disk'],
                'extension' => $mediaFile->getClientOriginalExtension(),
                'mime' => $mediaFile->getClientMimeType(),
                'size' => $imageData['image_size'],
                'lqip_base64' => $LQIPBase64,
                'metadata' => []
            ]);

            $this->draftStoryFrame->save();

            $this->draftStoryFrame->story->update([
                'updated_at' => now()
            ]);

            return $this->responseSuccess([
                'data' => [
                    'type' => 'image',
                    'source_url' => storage_url($imageData['image_path'], $imageData['disk'])
                ]
            ]);
        } catch (Exception $e) {
            return $this->responseValidationError([
                'message' => $e->getMessage(),
                'errors' => [
                    'media_file' => [
                        $e->getMessage()
                    ]
                ]
            ]);
        }
    }

    private function uploadStoryVideo(UploadedFile $mediaFile)
    {
        try {
            $videoUploadService = app(VideoUploadService::class);
            $videoThumbnailService = app(VideoThumbnailService::class);
            $imageUploadService = app(ImageUploadService::class);
            $base64ImageService = app(Base64ImageService::class);

            $videoStorageDisk = $this->roundRobinService->getNextDisk();

            $videoData = $videoUploadService
                ->setStorageDisk($videoStorageDisk)
                ->tempSaveLocally($mediaFile);

            $videoThumbnailPath = $videoThumbnailService->generateThumbnail($videoData['video_path']);

            $imageData = $imageUploadService
                ->load($videoThumbnailPath)
                ->setNamespace(Filesystem::mediaNamespace('stories/video_thumbnails'))
                ->setStorageDisk($videoStorageDisk)
                ->scaleTo1080x1920()
                ->compress(config('story.processing.video_thumbnail.compress_rate'))
                ->upload();

            $thumbnailLQIPBase64 = $base64ImageService->load($videoThumbnailPath)->getBase64();

            $this->draftStoryFrame->type = StoryType::VIDEO;

            $this->draftStoryFrame->media()->create([
                'source_path' => $videoData['video_path'],
                'thumbnail_path' => $imageData['image_path'],
                'type' => MediaType::VIDEO,
                'status' => MediaStatus::UNPROCESSED,
                'disk' => $videoData['disk'],
                'extension' => $mediaFile->getClientOriginalExtension(),
                'mime' => $mediaFile->getClientMimeType(),
                'size' => $mediaFile->getSize(),
                'thumbnail_size' => $imageData['image_size'],
                'thumbnail_disk' => $imageData['disk'],
                'lqip_base64' => $thumbnailLQIPBase64
            ]);

            $this->draftStoryFrame->duration_seconds = $videoData['seconds'];
            $this->draftStoryFrame->save();

            $this->draftStoryFrame->story->update([
                'updated_at' => now()
            ]);

            // Remove video thumbnail local temp file after it's uploaded
            // public disk.

            unlink($videoThumbnailPath);

            return $this->responseSuccess([
                'data' => [
                    'type' => 'video',
                    'source_url' => storage_url($imageData['image_path'], $videoStorageDisk),
                    'duration' => parse_duration(config('story.video_clip_size'))
                ]
            ]);
        } catch (Exception $e) {
            return $this->responseValidationError([
                'message' => $e->getMessage(),
                'errors' => [
                    'media_file' => [
                        $e->getMessage()
                    ]
                ]
            ]);
        }
    }

    private function canAddStoryFrame()
    {
        return $this->draftStoryFrame->story->activeFramesCount() < config('story.max_frames_per_story');
    }
}
