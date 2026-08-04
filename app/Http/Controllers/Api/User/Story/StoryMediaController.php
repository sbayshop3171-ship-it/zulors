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
use App\Models\Media;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Constants\Filesystem;
use App\Enums\Media\MediaType;
use App\Enums\Story\StoryType;
use App\Enums\Media\MediaStatus;
use Illuminate\Http\UploadedFile;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
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
            'media_file' => ['required', 'file'],
            'clip_start_seconds' => ['nullable', 'numeric', 'min:0', 'max:86400'],
            'clip_duration_seconds' => ['nullable', 'numeric', 'min:1', 'max:' . config('story.video_clip_size')],
        ]);

        $mediaFile = $request->file('media_file');

        $mediaType = Str::before($mediaFile->getMimeType(), '/');

        if($mediaType === 'image') {
            $this->validateStoryImage($mediaFile);

            return $this->uploadStoryImage($mediaFile);
        }
        else {
            $this->validateStoryVideo($mediaFile);

            return $this->uploadStoryVideo($request, $mediaFile);
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

    private function uploadStoryVideo(Request $request, UploadedFile $mediaFile)
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

            $clipData = $this->getStoryVideoClipData($request, (int) $videoData['seconds']);
            $videoThumbnailPath = $videoThumbnailService
                ->setSecondsOffset($clipData['start_seconds'])
                ->generateThumbnail($videoData['video_path']);

            $imageData = $imageUploadService
                ->load($videoThumbnailPath)
                ->setNamespace(Filesystem::mediaNamespace('stories/video_thumbnails'))
                ->setStorageDisk($videoStorageDisk)
                ->scaleTo1080x1920()
                ->compress(config('story.processing.video_thumbnail.compress_rate'))
                ->upload();

            $thumbnailLQIPBase64 = $base64ImageService->load($videoThumbnailPath)->getBase64();

            $this->draftStoryFrame->type = StoryType::VIDEO;

            $storyMedia = $this->draftStoryFrame->media()->create([
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
                'lqip_base64' => $thumbnailLQIPBase64,
                'metadata' => [
                    'duration_seconds' => $clipData['duration_seconds'],
                    'original_duration_seconds' => $clipData['original_duration_seconds'],
                    'clip_start_seconds' => $clipData['start_seconds'],
                    'clip_end_seconds' => $clipData['end_seconds'],
                    'dimensions' => $videoData['dimensions'] ?? [],
                    'aspect_ratio' => $videoData['aspect_ratio'] ?? null,
                    'is_portrait' => $videoData['is_portrait'] ?? false,
                ]
            ]);

            $this->draftStoryFrame->duration_seconds = $clipData['duration_seconds'];
            $this->draftStoryFrame->meta = array_merge($this->draftStoryFrame->meta ?? [], [
                'video' => [
                    'duration_seconds' => $clipData['duration_seconds'],
                    'original_duration_seconds' => $clipData['original_duration_seconds'],
                    'clip_start_seconds' => $clipData['start_seconds'],
                    'clip_end_seconds' => $clipData['end_seconds'],
                ]
            ]);
            $this->draftStoryFrame->save();

            $this->draftStoryFrame->story->update([
                'updated_at' => now()
            ]);

            // Remove video thumbnail local temp file after it's uploaded
            // public disk.

            unlink($videoThumbnailPath);

            return $this->responseSuccess([
                'data' => $this->buildStoryVideoPreviewPayload($storyMedia, $clipData)
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

    public function previewVideo(int $mediaId)
    {
        $storyMedia = Media::with('storyFrame.story')->findOrFail($mediaId);

        abort_unless($storyMedia->type->isVideo(), 404);
        abort_unless($storyMedia->storyFrame && $storyMedia->storyFrame->story && ($storyMedia->storyFrame->story->user_id === me()->id), 403);

        if(in_array(data_get($storyMedia->metadata, 'provider'), ['r2_temp', 'r2_direct'], true)) {
            return redirect()->away(Storage::disk($storyMedia->disk)->temporaryUrl(
                $storyMedia->source_path,
                now()->addMinutes(config('media.cloudflare.r2.temp_preview_expiry_minutes', 30))
            ));
        }

        $videoPath = $storyMedia->status->isProcessed()
            ? Storage::disk($storyMedia->disk)->path($storyMedia->source_path)
            : storage_local_path($storyMedia->source_path);

        abort_unless(is_file($videoPath), 404);

        $contentType = str_starts_with((string) $storyMedia->mime, 'video/')
            ? $storyMedia->mime
            : 'video/mp4';

        return response()->file($videoPath, [
            'Content-Type' => $contentType,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function canAddStoryFrame()
    {
        return $this->draftStoryFrame->story->activeFramesCount() < config('story.max_frames_per_story');
    }

    private function getStoryVideoClipData(Request $request, int $originalDurationSeconds): array
    {
        $maxClipSeconds = max(1, (int) config('story.video_clip_size'));
        $originalDurationSeconds = max(0, $originalDurationSeconds);
        $requestedStart = max(0, (float) $request->input('clip_start_seconds', 0));
        $requestedDuration = max(1, (float) $request->input('clip_duration_seconds', $maxClipSeconds));
        $clipDurationSeconds = min($maxClipSeconds, (int) ceil($requestedDuration));

        if($originalDurationSeconds > 0) {
            $maxStart = max(0, $originalDurationSeconds - min($clipDurationSeconds, $originalDurationSeconds));
            $clipStartSeconds = min((int) floor($requestedStart), $maxStart);
            $clipDurationSeconds = min($clipDurationSeconds, max(1, $originalDurationSeconds - $clipStartSeconds));
        }
        else {
            $clipStartSeconds = 0;
        }

        return [
            'original_duration_seconds' => $originalDurationSeconds,
            'start_seconds' => $clipStartSeconds,
            'duration_seconds' => $clipDurationSeconds,
            'end_seconds' => $clipStartSeconds + $clipDurationSeconds,
        ];
    }

    private function buildStoryVideoPreviewPayload(Media $storyMedia, array $clipData): array
    {
        return [
            'id' => $storyMedia->id,
            'type' => 'video',
            'source_url' => $this->storyEditorVideoPreviewUrl($storyMedia->id),
            'preview_url' => $this->storyEditorVideoPreviewUrl($storyMedia->id),
            'thumbnail_url' => storage_url($storyMedia->thumbnail_path, $storyMedia->thumbnail_disk),
            'duration' => parse_duration($clipData['duration_seconds']),
            'duration_seconds' => $clipData['duration_seconds'],
            'clip_start_seconds' => $clipData['start_seconds'],
            'clip_end_seconds' => $clipData['end_seconds'],
            'metadata' => [
                'duration' => parse_duration($clipData['duration_seconds']),
                'duration_seconds' => $clipData['duration_seconds'],
                'original_duration_seconds' => $clipData['original_duration_seconds'],
                'clip_start_seconds' => $clipData['start_seconds'],
                'clip_end_seconds' => $clipData['end_seconds'],
                'dimensions' => data_get($storyMedia->metadata, 'dimensions', []),
                'aspect_ratio' => data_get($storyMedia->metadata, 'aspect_ratio'),
                'is_portrait' => data_get($storyMedia->metadata, 'is_portrait', false),
            ],
        ];
    }

    private function storyEditorVideoPreviewUrl(int $mediaId): string
    {
        return url("/api/story/editor/media/video/preview/{$mediaId}");
    }
}
