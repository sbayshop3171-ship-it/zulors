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

namespace App\Http\Controllers\Api\User\Timeline;

use Exception;
use App\Enums\Post\PostType;
use Illuminate\Http\Request;
use App\Constants\Filesystem;
use App\Enums\Media\MediaType;
use App\Enums\Media\MediaStatus;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Services\Filesystem\Upload\ImageUploadService;
use App\Services\Filesystem\Upload\VideoUploadService;
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use App\Services\Filesystem\Upload\VideoThumbnailService;
use App\Traits\Http\Controllers\Api\User\Timeline\ValidatesPostVideo;
use App\Traits\Http\Controllers\Api\User\Timeline\InteractsWithDraftPost;

class PostVideoController extends Controller
{
    use InteractsWithDraftPost,
        SupportsApiResponses,
        ValidatesPostVideo;

    private $videoUploadService;
    private $roundRobinService;
    private $videoThumbnailService;
    private $imageUploadService;

    public function __construct(VideoUploadService $videoUploadService, ImageUploadService $imageUploadService, VideoThumbnailService $videoThumbnailService, RoundRobinService $roundRobinService)
    {
        $this->videoUploadService = $videoUploadService;
        $this->roundRobinService = $roundRobinService;
        $this->videoThumbnailService = $videoThumbnailService;
        $this->imageUploadService = $imageUploadService;
    }

    public function uploadVideo(Request $request)
    {
        $postVideoFile = $request->file('video');

        $this->validatePostVideo([
            'video' => $postVideoFile
        ]);

        $this->fetchOrInitializeDraftPost();
        $this->resetEmptyAttachmentDraftPost();

        if($this->draftPost->type->isTextified()) {

            if(! $this->draftPost->exists) {
                $this->draftPost->save();
            }

            $wasChangedToVideo = false;

            if($this->draftPost->type->isTextified()) {
                $this->draftPost->type = PostType::VIDEO;
                $this->draftPost->save();

                $wasChangedToVideo = true;
            }

            try {
                $videoStorageDisk = $this->roundRobinService->getNextDisk();

                $videoData = $this->videoUploadService
                    ->setStorageDisk($videoStorageDisk)
                    ->tempSaveLocally($postVideoFile);

                $videoThumbnailPath = $this->videoThumbnailService->generateThumbnail($videoData['video_path']);

                $isPortrait = $this->isVideoPortrait($videoThumbnailPath);

                $imageData = $this->imageUploadService
                    ->load($videoThumbnailPath)
                    ->setNamespace(Filesystem::mediaNamespace('posts/video_thumbnails'))
                    ->setStorageDisk($videoStorageDisk)
                    ->watermark()
                    ->compress(config('post.processing.thumbnail.compress_rate'))
                    ->upload();

                $this->draftPost->media()->create([
                    'source_path' => $videoData['video_path'],
                    'type' => MediaType::VIDEO,
                    'status' => MediaStatus::PROCESSING,
                    'disk' => $videoStorageDisk,
                    'extension' => $postVideoFile->getClientOriginalExtension(),
                    'mime' => $postVideoFile->getClientMimeType(),
                    'size' => $postVideoFile->getSize(),
                    'thumbnail_path' => $imageData['image_path'],
                    'thumbnail_size' => $imageData['image_size'],
                    'thumbnail_disk' => $imageData['disk'],
                    'metadata' => [
                        'duration' => $videoData['duration'],
                        'is_portrait' => $isPortrait
                    ]
                ]);

                unlink($videoThumbnailPath);

                return $this->responseSuccess([
                    'data' => [
                        'url' => storage_url($imageData['image_path'], $videoStorageDisk)
                    ]
                ]);

            } catch (Exception $e) {
                $this->resetDraftPostTypeAfterFailedAttachment($wasChangedToVideo);

                return $this->responseValidationError([
                    'message' => $e->getMessage(),
                    'errors' => [
                        'video' => [
                            $e->getMessage()
                        ]
                    ]
                ]);
            }
        }

        else{
            $errorMessage = __('post.validation.wrong_type_attachment', ['file_type' => __('labels.video')]);

            return $this->responseValidationError([
                'message' => $errorMessage,
                'errors' => [
                    'video' => [
                        $errorMessage
                    ]
                ]
            ]);
        }
    }

    private function isVideoPortrait(string $videoPath)
    {
        try {
            list($width, $height) = $this->getVideoDimensions($videoPath);

            return $width < $height;
        } catch (Exception $e) {
            return false;
        }
    }

    private function getVideoDimensions(string $videoPath)
    {
        list($width, $height) = getimagesize($videoPath);

        return [$width, $height];
    }

    private function resetDraftPostTypeAfterFailedAttachment(bool $wasChangedToVideo): void
    {
        if(! $wasChangedToVideo) {
            return;
        }

        try {
            if($this->draftPost->exists && $this->draftPost->media()->count() === 0) {
                $this->draftPost->type = PostType::TEXT;
                $this->draftPost->save();
            }
        } catch (Exception $e) {
            report($e);
        }
    }
}
