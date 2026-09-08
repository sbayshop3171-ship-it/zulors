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
use App\Services\Media\Cloudflare\R2DirectUploadService;
use App\Services\Filesystem\Base64Image\Base64ImageService;
use App\Traits\Http\Controllers\Api\User\Story\ValidatesStoryMedia;
use App\Traits\Http\Controllers\Api\User\Story\InteractsWithDraftStoryFrame;

class StoryMediaController extends Controller
{
    use \App\Traits\Http\Controllers\Api\RequiresDirectVideoUploads;
    use \App\Traits\Http\Controllers\Api\SerializesMediaCompletion;
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
            $this->rejectServerVideoUpload();
            $this->validateStoryVideo($mediaFile);

            return $this->uploadStoryVideo($request, $mediaFile);
        }
    }

    public function createDirectVideoUpload(Request $request, R2DirectUploadService $r2DirectUploadService)
    {
        $this->requireDirectVideoService($r2DirectUploadService);
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
            'name' => ['nullable', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:' . $this->maxDirectVideoBytes()],
            'mime' => ['nullable', 'string', 'max:120'],
            'extension' => ['nullable', 'string', 'max:16'],
            'width' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'duration_seconds' => ['nullable', 'numeric', 'min:0', 'max:' . $this->maxDirectVideoDurationSeconds()],
            'clip_start_seconds' => ['nullable', 'numeric', 'min:0', 'max:' . $this->maxDirectVideoDurationSeconds()],
            'clip_duration_seconds' => ['nullable', 'numeric', 'min:1', 'max:' . config('story.video_clip_size')],
        ]);

        if(! $r2DirectUploadService->isConfigured()) {
            return $this->responseSuccess([
                'data' => [
                    'direct_upload' => false,
                    'reason' => 'direct_upload_not_configured',
                ]
            ]);
        }

        if($this->draftStoryFrame->media()->exists()) {
            return $this->responseValidationError([
                'message' => 'Please remove the current story media before uploading a new video.',
                'errors' => [
                    'media_file' => [
                        'Please remove the current story media before uploading a new video.'
                    ]
                ]
            ]);
        }

        try {
            $uploadData = $r2DirectUploadService->createVideoUpload([
                'name' => (string) $request->input('name'),
                'size' => $request->integer('size', 0),
                'mime' => (string) $request->input('mime', 'video/mp4'),
                'extension' => (string) $request->input('extension', 'mp4'),
            ]);

            $clipData = $this->getStoryVideoClipData($request, (int) round((float) $request->input('duration_seconds', 0)));
            $presentationMetadata = $this->videoPresentationMetadata($request);

            $this->draftStoryFrame->type = StoryType::VIDEO;
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

            $storyMedia = $this->draftStoryFrame->media()->create([
                'source_path' => $uploadData['path'],
                'type' => MediaType::VIDEO,
                'status' => MediaStatus::UNPROCESSED,
                'disk' => $uploadData['disk'],
                'extension' => $request->input('extension', 'mp4'),
                'mime' => $request->input('mime', 'video/mp4'),
                'size' => $request->integer('size', 0),
                'metadata' => array_merge($presentationMetadata, [
                    'duration_seconds' => $clipData['duration_seconds'],
                    'duration' => parse_duration($clipData['duration_seconds']),
                    'original_duration_seconds' => $clipData['original_duration_seconds'],
                    'clip_start_seconds' => $clipData['start_seconds'],
                    'clip_end_seconds' => $clipData['end_seconds'],
                    'provider' => $uploadData['provider'],
                    'upload_state' => 'waiting_for_upload',
                    'upload_progress' => 0,
                    'upload_disk' => $uploadData['upload_disk'] ?? $uploadData['disk'],
                    'temp_disk' => $uploadData['disk'],
                    'temp_path' => $uploadData['path'],
                    'final_disk' => $uploadData['final_disk'],
                    'upload_url_expires_at' => $uploadData['expires_at'],
                    'upload_method' => $uploadData['upload_method'],
                    'upload_type' => $uploadData['upload_type'],
                    'upload_id' => $uploadData['upload_id'] ?? null,
                    'part_size' => $uploadData['part_size'] ?? null,
                    'parts_count' => count($uploadData['parts'] ?? []),
                    'processing_state' => 'waiting_for_upload',
                    'processing_progress' => 0,
                    'original_name' => (string) $request->input('name'),
                    'original_size' => $request->integer('size', 0),
                ])
            ]);

            $this->draftStoryFrame->story->update([
                'updated_at' => now()
            ]);

            return $this->responseSuccess([
                'data' => array_merge($this->buildStoryVideoPreviewPayload($storyMedia, $clipData), [
                    'direct_upload' => true,
                    'media_id' => $storyMedia->id,
                    'provider' => $uploadData['provider'],
                    'uid' => $uploadData['uid'],
                    'upload_url' => $uploadData['upload_url'],
                    'upload_method' => $uploadData['upload_method'],
                    'upload_type' => $uploadData['upload_type'],
                    'upload_headers' => $uploadData['upload_headers'],
                    'upload_id' => $uploadData['upload_id'] ?? null,
                    'part_size' => $uploadData['part_size'] ?? null,
                    'parts' => $uploadData['parts'] ?? [],
                    'upload_concurrency' => $uploadData['upload_concurrency'] ?? null,
                    'expires_at' => $uploadData['expires_at'],
                ])
            ]);
        }
        catch (Exception $e) {
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

    public function updateDirectVideoUploadProgress(Request $request)
    {
        $request->validate([
            'media_id' => ['required', 'integer'],
            'uid' => ['required', 'string', 'max:255'],
            'upload_progress' => ['required', 'integer', 'min:0', 'max:100'],
            'upload_state' => ['nullable', 'string', 'in:waiting_for_upload,uploading,failed'],
        ]);

        $storyMedia = $this->findOwnedDraftStoryVideoMedia($request->integer('media_id'), (string) $request->input('uid'));

        if(empty($storyMedia)) {
            return $this->responseNotFoundError();
        }

        $metadata = $storyMedia->metadata ?? [];

        if(data_get($metadata, 'upload_state') === 'uploaded') {
            return $this->responseSuccess([
                'data' => $this->buildStoryVideoPreviewPayload($storyMedia, $this->clipDataFromMetadata($storyMedia))
            ]);
        }

        $progress = $request->integer('upload_progress');

        if($request->input('upload_state') === 'failed') {
            $metadata['upload_state'] = 'failed';
            $metadata['upload_failed_at'] = now()->toIso8601String();
            $metadata['processing_state'] = 'failed';
            $storyMedia->status = MediaStatus::FAILED;
        }
        else {
            $metadata['upload_state'] = $progress > 0 ? 'uploading' : data_get($metadata, 'upload_state', 'waiting_for_upload');
            $storyMedia->status = MediaStatus::UNPROCESSED;
        }

        $metadata['upload_progress'] = $progress;
        $metadata['upload_progress_updated_at'] = now()->toIso8601String();

        $storyMedia->metadata = $metadata;
        $storyMedia->save();

        return $this->responseSuccess([
            'data' => $this->buildStoryVideoPreviewPayload($storyMedia->refresh(), $this->clipDataFromMetadata($storyMedia))
        ]);
    }

    public function completeDirectVideoUpload(Request $request, R2DirectUploadService $r2DirectUploadService)
    {
        return $this->serializeMediaCompletion($request, fn () => $this->finishVideoUpload($request, $r2DirectUploadService));
    }

    private function finishVideoUpload(Request $request, R2DirectUploadService $r2DirectUploadService)
    {
        $request->validate([
            'media_id' => ['required', 'integer'],
            'uid' => ['required', 'string', 'max:255'],
            'upload_id' => ['nullable', 'string', 'max:2048'],
            'parts' => ['nullable', 'array'],
            'parts.*.part_number' => ['required_with:parts', 'integer', 'min:1', 'max:10000'],
            'parts.*.etag' => ['nullable', 'string', 'max:255'],
        ]);

        $storyMedia = $this->findOwnedDraftStoryVideoMedia($request->integer('media_id'), (string) $request->input('uid'));

        if(empty($storyMedia)) {
            return $this->responseNotFoundError();
        }

        $metadata = $storyMedia->metadata ?? [];

        if(data_get($metadata, 'upload_state') === 'uploaded') {
            return $this->responseSuccess(['data' => $this->buildStoryVideoPreviewPayload($storyMedia, $this->clipDataFromMetadata($storyMedia))]);
        }

        try {
            if(data_get($metadata, 'upload_type') === 'multipart' && data_get($metadata, 'upload_state') !== 'uploaded') {
                $r2DirectUploadService->completeMultipartUpload(
                    $storyMedia->source_path,
                    (string) ($request->input('upload_id') ?: data_get($metadata, 'upload_id')),
                    $request->array('parts'),
                    $storyMedia->disk,
                    (int) data_get($metadata, 'parts_count', 0)
                );
            }

            if(! $r2DirectUploadService->uploaded($storyMedia->source_path, $storyMedia->disk)) {
                throw new Exception('Direct story video file was not found on R2.');
            }

            $r2DirectUploadService->verifyUploadedVideo($storyMedia->source_path, $storyMedia->disk, (int) $storyMedia->size);
        }
        catch (Exception $e) {
            return $this->responseValidationError([
                'message' => $e->getMessage(),
                'errors' => [
                    'media_file' => [
                        $e->getMessage()
                    ]
                ]
            ]);
        }

        $metadata = array_merge($metadata, [
            'upload_state' => 'uploaded',
            'upload_progress' => 100,
            'upload_completed_at' => now()->toIso8601String(),
            'processing_state' => 'uploaded',
            'processing_progress' => 0,
            'processing_updated_at' => now()->toIso8601String(),
            'original_size' => (int) ($storyMedia->size ?: data_get($metadata, 'original_size', 0)),
        ]);

        $storyMedia->metadata = $metadata;
        $storyMedia->status = MediaStatus::UNPROCESSED;
        $storyMedia->save();

        return $this->responseSuccess([
            'data' => $this->buildStoryVideoPreviewPayload($storyMedia->refresh(), $this->clipDataFromMetadata($storyMedia))
        ]);
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
                    $videoStorageDisk = in_array(data_get($storyMedia->metadata, 'provider'), ['r2_temp', 'r2_direct'], true)
                        ? $storyMedia->disk
                        : 'local';
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
                'extension' => $imageData['image_extension'] ?? pathinfo($imageData['image_path'], PATHINFO_EXTENSION),
                'mime' => $imageData['image_mime'] ?? 'image/webp',
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

            $videoPublicDisk = $this->storyVideoPublicDisk();

            $videoData = $videoUploadService->tempSaveLocally($mediaFile);

            $clipData = $this->getStoryVideoClipData($request, (int) $videoData['seconds']);
            $videoThumbnailPath = $videoThumbnailService
                ->setSecondsOffset($clipData['start_seconds'])
                ->generateThumbnail($videoData['video_path']);

            $imageData = $imageUploadService
                ->load($videoThumbnailPath)
                ->setNamespace(Filesystem::mediaNamespace('stories/video_thumbnails'))
                ->setStorageDisk($videoPublicDisk)
                ->scaleTo1080x1920()
                ->compress(config('story.processing.video_thumbnail.compress_rate'))
                ->upload();

            $thumbnailLQIPBase64 = $base64ImageService->load($videoThumbnailPath)->getBase64();
            $storyVideoStorage = $this->persistStoryVideoSource($videoUploadService, $mediaFile, $videoData['video_path']);

            $this->draftStoryFrame->type = StoryType::VIDEO;

            $storyMedia = $this->draftStoryFrame->media()->create([
                'source_path' => $storyVideoStorage['video_path'],
                'thumbnail_path' => $imageData['image_path'],
                'type' => MediaType::VIDEO,
                'status' => MediaStatus::UNPROCESSED,
                'disk' => $storyVideoStorage['disk'],
                'extension' => $mediaFile->getClientOriginalExtension(),
                'mime' => $mediaFile->getClientMimeType(),
                'size' => $storyVideoStorage['video_size'] ?: $mediaFile->getSize(),
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
                    'provider' => $storyVideoStorage['provider'],
                    'upload_state' => $storyVideoStorage['upload_state'],
                    'upload_completed_at' => now()->toIso8601String(),
                    'temp_disk' => $storyVideoStorage['temp_disk'],
                    'final_disk' => $storyVideoStorage['final_disk'],
                    'original_size' => $storyVideoStorage['video_size'] ?: $mediaFile->getSize(),
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

            if(is_file($videoThumbnailPath)) {
                unlink($videoThumbnailPath);
            }

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

    private function clipDataFromMetadata(Media $storyMedia): array
    {
        $metadata = $storyMedia->metadata ?? [];

        return [
            'original_duration_seconds' => (int) data_get($metadata, 'original_duration_seconds', data_get($metadata, 'duration_seconds', 0)),
            'start_seconds' => (int) data_get($metadata, 'clip_start_seconds', 0),
            'duration_seconds' => (int) data_get($metadata, 'duration_seconds', $this->draftStoryFrame->duration_seconds ?: config('story.video_clip_size')),
            'end_seconds' => (int) data_get($metadata, 'clip_end_seconds', data_get($metadata, 'duration_seconds', config('story.video_clip_size'))),
        ];
    }

    private function videoPresentationMetadata(Request $request): array
    {
        $width = $request->integer('width', 0);
        $height = $request->integer('height', 0);

        if($width < 1 || $height < 1) {
            return [];
        }

        return [
            'dimensions' => [
                'width' => $width,
                'height' => $height,
            ],
            'aspect_ratio' => round($width / $height, 6),
            'is_portrait' => $width < $height,
        ];
    }

    private function findOwnedDraftStoryVideoMedia(int $mediaId, string $uid): ?Media
    {
        $storyMedia = Media::with('storyFrame.story')->find($mediaId);

        if(
            empty($storyMedia)
            || $storyMedia->source_path !== $uid
            || ! $storyMedia->type->isVideo()
            || empty($storyMedia->storyFrame)
            || empty($storyMedia->storyFrame->story)
            || $storyMedia->storyFrame->story->user_id !== me()->id
        ) {
            return null;
        }

        return $storyMedia;
    }

    private function maxDirectVideoBytes(): int
    {
        return max(1, (int) config('media.uploads.video.max_bytes', 1024 * 1024 * 1024));
    }

    private function maxDirectVideoDurationSeconds(): int
    {
        return max(1, (int) config('media.uploads.video.max_duration_seconds', 600));
    }

    private function buildStoryVideoPreviewPayload(Media $storyMedia, array $clipData): array
    {
        return [
            'id' => $storyMedia->id,
            'status' => $storyMedia->status->value,
            'type' => 'video',
            'source_url' => $this->storyMediaCanPreviewOriginal($storyMedia) ? $this->storyEditorVideoPreviewUrl($storyMedia->id) : null,
            'preview_url' => $this->storyMediaCanPreviewOriginal($storyMedia) ? $this->storyEditorVideoPreviewUrl($storyMedia->id) : null,
            'thumbnail_url' => filled($storyMedia->thumbnail_path) ? storage_url($storyMedia->thumbnail_path, $storyMedia->thumbnail_disk) : null,
            'duration' => parse_duration($clipData['duration_seconds']),
            'duration_seconds' => $clipData['duration_seconds'],
            'clip_start_seconds' => $clipData['start_seconds'],
            'clip_end_seconds' => $clipData['end_seconds'],
            'metadata' => [
                'provider' => data_get($storyMedia->metadata, 'provider'),
                'upload_state' => data_get($storyMedia->metadata, 'upload_state'),
                'processing_state' => data_get($storyMedia->metadata, 'processing_state'),
                'processing_progress' => data_get($storyMedia->metadata, 'processing_progress', 0),
                'temp_disk' => data_get($storyMedia->metadata, 'temp_disk'),
                'final_disk' => data_get($storyMedia->metadata, 'final_disk'),
                'original_size' => data_get($storyMedia->metadata, 'original_size', $storyMedia->size),
                'optimized_size' => data_get($storyMedia->metadata, 'optimized_size'),
                'optimization_ratio' => data_get($storyMedia->metadata, 'optimization_ratio'),
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

    private function storyMediaCanPreviewOriginal(Media $storyMedia): bool
    {
        return ! in_array(data_get($storyMedia->metadata, 'provider'), ['r2_temp', 'r2_direct'], true);
    }

    private function storyEditorVideoPreviewUrl(int $mediaId): string
    {
        return url("/api/story/editor/media/video/preview/{$mediaId}");
    }

    private function persistStoryVideoSource(
        VideoUploadService $videoUploadService,
        UploadedFile $mediaFile,
        string $localVideoPath
    ): array {
        if(! $this->shouldUseR2StoryVideoPipeline()) {
            return [
                'provider' => 'local',
                'upload_state' => 'uploaded',
                'disk' => 'local',
                'video_path' => $localVideoPath,
                'video_size' => (int) ($mediaFile->getSize() ?: 0),
                'temp_disk' => 'local',
                'final_disk' => $this->storyVideoPublicDisk(),
            ];
        }

        $uploadedVideo = $videoUploadService
            ->setStorageDisk($this->storyVideoTempDisk())
            ->setNamespace(Filesystem::mediaNamespace('stories/raw_videos'))
            ->setDefaultExtension($mediaFile->getClientOriginalExtension())
            ->upload(storage_local_path($localVideoPath));

        return [
            'provider' => 'r2_temp',
            'upload_state' => 'uploaded',
            'disk' => $uploadedVideo['disk'],
            'video_path' => $uploadedVideo['video_path'],
            'video_size' => (int) ($uploadedVideo['video_size'] ?? 0),
            'temp_disk' => $uploadedVideo['disk'],
            'final_disk' => $this->storyVideoPublicDisk(),
        ];
    }

    private function shouldUseR2StoryVideoPipeline(): bool
    {
        return $this->diskEnabled($this->storyVideoTempDisk())
            && $this->diskEnabled((string) config('media.cloudflare.r2.final_disk', 'r2_final'));
    }

    private function storyVideoTempDisk(): string
    {
        return (string) config('media.cloudflare.r2.temp_disk', 'r2_temp');
    }

    private function storyVideoPublicDisk(): string
    {
        $finalDisk = (string) config('media.cloudflare.r2.final_disk', 'r2_final');

        if($this->diskEnabled($finalDisk)) {
            return $finalDisk;
        }

        return $this->roundRobinService->getNextDisk();
    }

    private function diskEnabled(string $disk): bool
    {
        return (bool) data_get(config("filesystems.disks.{$disk}"), 'enabled', true);
    }
}
