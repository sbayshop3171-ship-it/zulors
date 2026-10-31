<?php

namespace App\Http\Controllers\Api\User\Timeline;

use Exception;
use Throwable;
use App\Models\Post;
use App\Models\Media;
use Illuminate\Http\Request;
use App\Enums\Post\PostType;
use App\Enums\Post\PostStatus;
use App\Enums\Media\MediaType;
use App\Enums\Media\MediaStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\Media\MediaResource;
use App\Events\User\Timeline\MediaUpdatedEvent;
use App\Events\User\Timeline\PublicTimelinePostCreatedEvent;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Services\Media\Cloudflare\R2DirectUploadService;
use App\Services\Media\Cloudflare\CloudflareStreamService;
use App\Support\StoryMusic\OriginalAudioEligibility;
use App\Traits\Http\Controllers\Api\User\Timeline\InteractsWithDraftPost;

class DirectMediaUploadController extends Controller
{
    use \App\Traits\Http\Controllers\Api\RequiresDirectVideoUploads;
    use \App\Traits\Http\Controllers\Api\SerializesMediaCompletion;
    use InteractsWithDraftPost,
        SupportsApiResponses;

    public function createVideoUpload(Request $request, R2DirectUploadService $r2DirectUploadService)
    {
        $this->requireDirectVideoService($r2DirectUploadService);
        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:' . config('media.uploads.video.max_bytes', 8589934592)],
            'mime' => ['required', 'string', 'in:video/mp4,video/quicktime,video/webm,video/avi,video/x-msvideo,video/mpeg,video/x-m4v,video/3gpp,video/3gpp2,video/x-matroska'],
            'extension' => ['required', 'string', 'regex:/\A[a-zA-Z0-9]{2,8}\z/'],
            'width' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'duration_seconds' => ['nullable', 'numeric', 'min:0', 'max:' . config('media.uploads.video.max_duration_seconds', 3600)],
            ...OriginalAudioEligibility::metadataValidationRules(),
        ]);
        $presentationMetadata = $this->videoPresentationMetadata($request);

        $this->fetchOrInitializeDraftPost();
        $this->resetFailedDirectVideoDraftPost();
        $this->resetEmptyAttachmentDraftPost();

        if(! $this->draftPost->type->isTextified()) {
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

        try {
            if(! $this->draftPost->exists) {
                $this->draftPost->save();
            }

            $this->draftPost->type = PostType::VIDEO;
            $this->draftPost->save();

            if($r2DirectUploadService->isConfigured()) {
                return $this->responseSuccess([
                    'data' => $this->startR2VideoUpload($request, $r2DirectUploadService, $presentationMetadata),
                ]);
            }

            throw new Exception('Direct R2 video uploads are temporarily unavailable. Please retry later.');
        }
        catch (Exception $e) {
            $this->resetEmptyAttachmentDraftPost();

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

    private function startR2VideoUpload(Request $request, R2DirectUploadService $r2DirectUploadService, array $presentationMetadata): array
    {
        $uploadData = $r2DirectUploadService->createVideoUpload([
            'name' => (string) $request->input('name'),
            'size' => $request->integer('size', 0),
            'mime' => (string) $request->input('mime', 'video/mp4'),
            'extension' => (string) $request->input('extension', 'mp4'),
        ]);

        $media = $this->draftPost->media()->create([
            'source_path' => $uploadData['path'],
            'type' => MediaType::VIDEO,
            'status' => MediaStatus::PROCESSING,
            'disk' => $uploadData['disk'],
            'extension' => $request->input('extension', 'mp4'),
            'mime' => $request->input('mime', 'video/mp4'),
            'size' => $request->integer('size', 0),
            'metadata' => array_merge($presentationMetadata, [
                'provider' => $uploadData['provider'],
                'temp_disk' => $uploadData['disk'],
                'upload_disk' => $uploadData['upload_disk'] ?? $uploadData['disk'],
                'final_disk' => $uploadData['final_disk'],
                'temp_path' => $uploadData['path'],
                'upload_state' => 'waiting_for_upload',
                'upload_url_expires_at' => $uploadData['expires_at'],
                'upload_method' => $uploadData['upload_method'],
                'upload_type' => $uploadData['upload_type'],
                'upload_id' => $uploadData['upload_id'] ?? null,
                'part_size' => $uploadData['part_size'] ?? null,
                'parts_count' => count($uploadData['parts'] ?? []),
                'upload_progress' => 0,
                'processing_progress' => 0,
                'processing_state' => 'waiting_for_upload',
                'original_name' => (string) $request->input('name'),
                'original_size' => $request->integer('size'),
                'optimized_size' => null,
                'optimization_ratio' => null,
            ], OriginalAudioEligibility::storyMusicMetadataFromRequest($request))
        ]);

        return [
            'direct_upload' => true,
            'media_id' => $media->id,
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
            'upload_stall_timeout_ms' => $uploadData['upload_stall_timeout_ms'] ?? null,
            'raw_fallback_max_bytes' => $uploadData['raw_fallback_max_bytes'] ?? null,
            'part_fallback_max_bytes' => $uploadData['part_fallback_max_bytes'] ?? null,
            'expires_at' => $uploadData['expires_at'],
            'media' => MediaResource::make($media),
        ];
    }

    public function updateVideoUploadProgress(Request $request)
    {
        $request->validate([
            'media_id' => ['required', 'integer'],
            'uid' => ['required', 'string', 'max:255'],
            'upload_progress' => ['required', 'integer', 'min:0', 'max:100'],
            'upload_state' => ['nullable', 'string', 'in:waiting_for_upload,uploading,failed'],
        ]);

        $media = Media::query()->find($request->integer('media_id'));
        $requestedUid = (string) $request->input('uid');
        $mediaMetadata = $media?->metadata ?? [];
        $publishedR2Retry = $media
            && $media->status->isProcessed()
            && data_get($mediaMetadata, 'provider') === 'r2'
            && data_get($mediaMetadata, 'temp_path') === $requestedUid;

        if(empty($media) || ($media->source_path !== $requestedUid && ! $publishedR2Retry)) {
            return $this->responseNotFoundError();
        }

        $media->load('mediaable');

        if(! ($media->mediaable instanceof Post) || $media->mediaable->user_id !== me()->id) {
            return $this->responseUnauthorizedError();
        }

        $metadata = $media->metadata ?? [];
        $provider = (string) data_get($metadata, 'provider');

        if(data_get($metadata, 'upload_state') === 'uploaded') {
            return $this->responseSuccess([
                'data' => [
                    'media' => MediaResource::make($media)
                ]
            ]);
        }

        $progress = $request->integer('upload_progress');
        $requestedUploadState = $request->input('upload_state');

        if($requestedUploadState === 'failed') {
            $metadata['upload_state'] = 'failed';
            $metadata['upload_failed_at'] = now()->toIso8601String();
            $metadata['processing_state'] = 'failed';
            $media->status = MediaStatus::FAILED;
        }
        else {
            $metadata['upload_state'] = $progress > 0 ? 'uploading' : data_get($metadata, 'upload_state', 'waiting_for_upload');

            if(! $media->status->isProcessed()) {
                $media->status = MediaStatus::PROCESSING;
            }
        }

        $metadata['upload_progress'] = $progress;
        $metadata['upload_progress_updated_at'] = now()->toIso8601String();

        $media->metadata = $metadata;
        $media->save();

        $this->broadcastMediaUpdated($media);

        return $this->responseSuccess([
            'data' => [
                'media' => MediaResource::make($media->refresh())
            ]
        ]);
    }

    public function completeVideoUpload(Request $request, CloudflareStreamService $cloudflareStreamService, R2DirectUploadService $r2DirectUploadService)
    {
        return $this->serializeMediaCompletion($request, fn () => $this->finishVideoUpload($request, $cloudflareStreamService, $r2DirectUploadService));
    }

    private function finishVideoUpload(Request $request, CloudflareStreamService $cloudflareStreamService, R2DirectUploadService $r2DirectUploadService)
    {
        $request->validate([
            'media_id' => ['required', 'integer'],
            'uid' => ['required', 'string', 'max:255'],
            'upload_id' => ['nullable', 'string', 'max:2048'],
            'parts' => ['nullable', 'array'],
            'parts.*.part_number' => ['required_with:parts', 'integer', 'min:1', 'max:10000'],
            'parts.*.etag' => ['nullable', 'string', 'max:255'],
        ]);

        $media = Media::query()->find($request->integer('media_id'));
        $requestedUid = (string) $request->input('uid');
        $mediaMetadata = $media?->metadata ?? [];
        $publishedR2Retry = $media
            && $media->status->isProcessed()
            && data_get($mediaMetadata, 'provider') === 'r2'
            && data_get($mediaMetadata, 'temp_path') === $requestedUid;

        if(empty($media) || ($media->source_path !== $requestedUid && ! $publishedR2Retry)) {
            return $this->responseNotFoundError();
        }

        $media->load('mediaable');

        if(! ($media->mediaable instanceof Post) || $media->mediaable->user_id !== me()->id) {
            return $this->responseUnauthorizedError();
        }

        $metadata = $media->metadata ?? [];
        $provider = (string) data_get($metadata, 'provider');

        if($publishedR2Retry || (in_array($provider, ['r2_temp', 'r2_direct'], true) && data_get($metadata, 'upload_state') === 'uploaded')) {
            if(! $media->status->isProcessed()) {
                $media = $this->publishCompletedR2Video($media, $metadata, $r2DirectUploadService, (int) data_get($metadata, 'original_size', $media->size));
                $this->broadcastMediaUpdated($media);
            }

            return $this->responseSuccess([
                'data' => [
                    'media' => MediaResource::make($media->refresh()),
                ]
            ]);
        }

        if(in_array($provider, ['r2_temp', 'r2_direct'], true)) {
            if(data_get($metadata, 'upload_type') === 'multipart' && data_get($metadata, 'upload_state') !== 'uploaded') {
                $uploadId = (string) ($request->input('upload_id') ?: data_get($metadata, 'upload_id'));

                if(blank($uploadId)) {
                    return $this->responseValidationError([
                        'message' => 'Multipart upload id is missing.',
                        'errors' => [
                            'video' => [
                                'Multipart upload id is missing.'
                            ]
                        ]
                    ]);
                }

                try {
                    $r2DirectUploadService->completeMultipartUpload(
                        $media->source_path,
                        $uploadId,
                        $request->array('parts'),
                        $media->disk,
                        (int) data_get($metadata, 'parts_count', 0)
                    );
                }
                catch (Exception $e) {
                    if(! $r2DirectUploadService->uploaded($media->source_path, $media->disk)) {
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
            }

            if(! $r2DirectUploadService->uploaded($media->source_path, $media->disk)) {
                return $this->responseValidationError([
                    'message' => 'Direct upload file was not found on R2.',
                    'errors' => [
                        'video' => [
                            'Direct upload file was not found on R2.'
                        ]
                    ]
                ]);
            }

            try {
                $originalSize = $r2DirectUploadService->verifyUploadedVideo($media->source_path, $media->disk, (int) $media->size);
            }
            catch (\Throwable $e) {
                return $this->responseValidationError(['message' => $e->getMessage(), 'errors' => ['video' => [$e->getMessage()]]]);
            }
            $now = now()->toIso8601String();
            $nextMetadata = array_merge($metadata, [
                'upload_state' => 'uploaded',
                'upload_progress' => 100,
                'upload_completed_at' => data_get($metadata, 'upload_completed_at', $now),
                'processing_state' => 'ready',
                'processing_progress' => max(1, min(99, (int) data_get($metadata, 'processing_progress', 0))),
                'processing_updated_at' => $now,
                'temp_path' => data_get($metadata, 'temp_path', $media->source_path),
                'temp_disk' => data_get($metadata, 'temp_disk', $media->disk),
                'final_disk' => data_get($metadata, 'final_disk', $r2DirectUploadService->finalDisk()),
                'original_size' => $originalSize ?: (int) $media->size,
                'optimized_size' => null,
                'optimization_ratio' => null,
            ]);
            unset($nextMetadata['processed_at'], $nextMetadata['processing_fallback']);

            $media = $this->publishCompletedR2Video($media, $nextMetadata, $r2DirectUploadService, $originalSize ?: (int) $media->size);

            $this->broadcastMediaUpdated($media);

            return $this->responseSuccess([
                'data' => [
                    'media' => MediaResource::make($media->refresh()),
                ]
            ]);
        }

        if($media->disk !== 'cloudflare_stream') {
            return $this->responseNotFoundError();
        }

        $metadata['upload_state'] = 'uploaded';
        $metadata['upload_progress'] = 100;
        $metadata['upload_completed_at'] = now()->toIso8601String();
        $metadata['playback'] = $cloudflareStreamService->playbackUrls($media->source_path);

        $media->metadata = $metadata;
        $media->status = MediaStatus::PROCESSING;
        $media->save();

        $this->broadcastMediaUpdated($media);

        return $this->responseSuccess([
            'data' => [
                'media' => MediaResource::make($media->refresh()),
            ]
        ]);
    }

    private function publishCompletedR2Video(Media $media, array $metadata, R2DirectUploadService $r2DirectUploadService, int $originalSize): Media
    {
        $now = now()->toIso8601String();
        $temporaryPath = $media->source_path;
        $temporaryDisk = $media->disk;
        $published = $temporaryDisk === $r2DirectUploadService->finalDisk()
            ? ['disk' => $temporaryDisk, 'video_path' => $temporaryPath, 'video_size' => $originalSize]
            : $r2DirectUploadService->publishUploadedVideo(
                $temporaryPath,
                $media->extension ?: 'mp4',
                $media->mime ?: 'video/mp4',
                $temporaryDisk
            );

        $media->source_path = $published['video_path'];
        $media->disk = $published['disk'];
        $media->size = (int) $published['video_size'];

        $nextMetadata = array_merge($metadata, [
            'provider' => 'r2',
            'upload_state' => 'uploaded',
            'upload_progress' => 100,
            'upload_completed_at' => data_get($metadata, 'upload_completed_at', $now),
            'processing_state' => 'processed',
            'processing_progress' => 100,
            'processing_updated_at' => $now,
            'background_processing_state' => 'skipped',
            'background_processing_progress' => 100,
            'processing_mode' => 'original_direct',
            'temp_path' => data_get($metadata, 'temp_path', $temporaryPath),
            'temp_disk' => data_get($metadata, 'temp_disk', $temporaryDisk),
            'final_disk' => $published['disk'],
            'original_size' => $originalSize ?: (int) $media->size,
            'optimized_size' => null,
            'optimization_ratio' => null,
            'instant_publish' => true,
        ]);

        unset($nextMetadata['processed_at'], $nextMetadata['processing_fallback']);

        $media->metadata = $nextMetadata;
        $media->status = MediaStatus::PROCESSED;
        $media->save();
        $media->load('mediaable');

        $post = $media->mediaable;
        $postWasProcessing = $post instanceof Post && $post->status === PostStatus::PROCESSING_VIDEO;

        if($postWasProcessing) {
            $post->status = PostStatus::ACTIVE;
            $post->save();
        }

        if($postWasProcessing) {
            try {
                broadcast(new PublicTimelinePostCreatedEvent($post->refresh()))->toOthers();
            }
            catch (Throwable $exception) {
                report($exception);
            }
        }

        return $media->refresh();
    }

    public function uploadRawVideo(Request $request, R2DirectUploadService $r2DirectUploadService)
    {
        abort(response()->json([
            'message' => 'Application-server media proxy uploads are disabled. Retry through the signed R2 URL.',
            'code' => 'direct_upload_required',
        ], 410));
    }

    public function uploadVideoPart(Request $request, R2DirectUploadService $r2DirectUploadService)
    {
        abort(response()->json([
            'message' => 'Application-server media proxy uploads are disabled. Retry through the signed R2 URL.',
            'code' => 'direct_upload_required',
        ], 410));
    }

    private function videoPresentationMetadata(Request $request): array
    {
        $width = $request->integer('width', 0);
        $height = $request->integer('height', 0);
        $durationSeconds = (float) $request->input('duration_seconds', 0);
        $metadata = [];

        if($width > 0 && $height > 0) {
            $metadata['dimensions'] = [
                'width' => $width,
                'height' => $height,
            ];
            $metadata['aspect_ratio'] = round($width / $height, 6);
            $metadata['is_portrait'] = $width < $height;
        }

        if($durationSeconds > 0) {
            $seconds = (int) round($durationSeconds);

            $metadata['duration_seconds'] = $seconds;
            $metadata['duration'] = parse_duration($seconds);
        }

        return $metadata;
    }

    private function broadcastMediaUpdated(Media $media): void
    {
        $media->loadMissing('mediaable');

        $post = $media->mediaable;

        if(! ($post instanceof Post)) {
            return;
        }

        $userId = $post->user_id;

        try {
            event(new MediaUpdatedEvent($media->refresh(), $userId));
        }
        catch (Throwable $e) {
            report($e);
        }
    }
}
