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
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\Media\MediaResource;
use App\Events\User\Timeline\MediaUpdatedEvent;
use App\Events\User\Timeline\MediaProcessedEvent;
use App\Events\User\Timeline\PublicTimelinePostCreatedEvent;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Services\Media\Cloudflare\R2DirectUploadService;
use App\Services\Media\Cloudflare\CloudflareStreamService;
use App\Traits\Http\Controllers\Api\User\Timeline\InteractsWithDraftPost;

class DirectMediaUploadController extends Controller
{
    use InteractsWithDraftPost,
        SupportsApiResponses;

    public function createVideoUpload(Request $request, CloudflareStreamService $cloudflareStreamService, R2DirectUploadService $r2DirectUploadService)
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'integer', 'min:1'],
            'mime' => ['nullable', 'string', 'max:120'],
            'extension' => ['nullable', 'string', 'max:16'],
            'width' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'duration_seconds' => ['nullable', 'numeric', 'min:0', 'max:86400'],
        ]);
        $presentationMetadata = $this->videoPresentationMetadata($request);

        if(! $r2DirectUploadService->isConfigured() && ! $cloudflareStreamService->isConfigured()) {
            return $this->responseSuccess([
                'data' => [
                    'direct_upload' => false,
                    'reason' => 'direct_upload_not_configured'
                ]
            ]);
        }

        $this->fetchOrInitializeDraftPost();
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
                    ])
                ]);

                return $this->responseSuccess([
                    'data' => [
                        'direct_upload' => true,
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
                    ]
                ]);
            }

            $uploadData = $cloudflareStreamService->createDirectUpload([
                'post_id' => (string) $this->draftPost->id,
                'user_id' => (string) me()->id,
                'original_name' => (string) $request->input('name'),
            ]);

            $media = $this->draftPost->media()->create([
                'source_path' => $uploadData['uid'],
                'type' => MediaType::VIDEO,
                'status' => MediaStatus::PROCESSING,
                'disk' => 'cloudflare_stream',
                'extension' => $request->input('extension', 'mp4'),
                'mime' => $request->input('mime', 'video/mp4'),
                'size' => $request->integer('size', 0),
                'thumbnail_path' => $uploadData['playback']['thumbnail'] ?? null,
                'thumbnail_disk' => 'cloudflare_stream',
                'metadata' => array_merge($presentationMetadata, [
                    'provider' => 'cloudflare_stream',
                    'cloudflare_uid' => $uploadData['uid'],
                    'upload_state' => 'waiting_for_upload',
                    'upload_url_expires_at' => $uploadData['expires_at'],
                    'upload_progress' => 0,
                    'processing_progress' => 0,
                    'processing_state' => 'waiting_for_upload',
                    'playback' => $uploadData['playback'],
                    'original_name' => (string) $request->input('name'),
                ])
            ]);

            return $this->responseSuccess([
                'data' => [
                    'direct_upload' => true,
                    'uid' => $uploadData['uid'],
                    'upload_url' => $uploadData['upload_url'],
                    'expires_at' => $uploadData['expires_at'],
                    'media' => MediaResource::make($media),
                ]
            ]);
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
        $request->validate([
            'media_id' => ['required', 'integer'],
            'uid' => ['required', 'string', 'max:255'],
            'upload_id' => ['nullable', 'string', 'max:2048'],
            'parts' => ['nullable', 'array'],
            'parts.*.part_number' => ['required_with:parts', 'integer', 'min:1', 'max:10000'],
            'parts.*.etag' => ['required_with:parts', 'string', 'max:255'],
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

        if($publishedR2Retry) {
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
                        $media->disk
                    );
                }
                catch (Exception $e) {
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

            // New uploads land directly in the final R2 bucket. Publishing only
            // updates the database; it must never copy the whole video again.
            if($provider === 'r2_direct' && $media->disk === $r2DirectUploadService->finalDisk()) {
                return $this->finalizeDirectR2Upload($media, $metadata);
            }

            $metadata['upload_state'] = 'uploaded';
            $metadata['upload_progress'] = 100;
            $metadata['upload_completed_at'] = now()->toIso8601String();
            $metadata['processing_state'] = 'publishing';
            $metadata['processing_progress'] = max(95, (int) data_get($metadata, 'processing_progress', 0));
            $metadata['processing_updated_at'] = now()->toIso8601String();

            $media->metadata = $metadata;
            $media->status = MediaStatus::PROCESSING;
            $media->save();

            $oldDisk = $media->disk;
            $oldPath = $media->source_path;
            $originalSize = (int) ($media->size ?: data_get($metadata, 'original_size', 0));
            $post = $media->mediaable;
            $postWasProcessing = $post instanceof Post && $post->status === PostStatus::PROCESSING_VIDEO;

            try {
                $videoData = $r2DirectUploadService->publishUploadedVideo(
                    $oldPath,
                    $media->extension ?: 'mp4',
                    $media->mime ?: 'video/mp4'
                );
            }
            catch(Throwable $e) {
                $metadata['processing_state'] = 'publish_failed';
                $metadata['processing_error'] = $e->getMessage();
                $metadata['processing_updated_at'] = now()->toIso8601String();
                $media->metadata = $metadata;
                $media->save();
                $this->broadcastMediaUpdated($media);

                return $this->responseValidationError([
                    'message' => 'Upload completed, but final media publishing failed. Please retry.',
                    'errors' => [
                        'video' => [
                            'Upload completed, but final media publishing failed. Please retry.'
                        ]
                    ]
                ]);
            }

            $media->source_path = $videoData['video_path'];
            $media->disk = $videoData['disk'];
            $media->status = MediaStatus::PROCESSED;
            $media->size = $videoData['video_size'] ?: $media->size;
            $media->metadata = array_merge($metadata, [
                'provider' => 'r2',
                'processed_at' => now()->toIso8601String(),
                'processing_progress' => 100,
                'processing_state' => 'processed',
                'processing_updated_at' => now()->toIso8601String(),
                'processing_fallback' => 'direct_original_publish',
                'original_size' => $originalSize ?: (int) ($videoData['video_size'] ?: $media->size),
                'optimized_size' => (int) ($videoData['video_size'] ?: $media->size),
            ]);
            $media->save();

            if($postWasProcessing) {
                $post->status = PostStatus::ACTIVE;
                $post->save();
            }

            try {
                Storage::disk($oldDisk)->delete($oldPath);
            }
            catch(Throwable $e) {
                report($e);
            }

            $this->broadcastMediaUpdated($media);

            if($postWasProcessing) {
                event(new MediaProcessedEvent($media->refresh(), $post->user_id));
                event(new PublicTimelinePostCreatedEvent($post->refresh()));
            }

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

    public function uploadRawVideo(Request $request, R2DirectUploadService $r2DirectUploadService)
    {
        $request->validate([
            'media_id' => ['required', 'integer'],
            'uid' => ['required', 'string', 'max:255'],
            'content_type' => ['nullable', 'string', 'max:120'],
        ]);

        $media = Media::query()->find($request->integer('media_id'));

        if(empty($media) || $media->source_path !== $request->input('uid')) {
            return $this->responseNotFoundError();
        }

        $media->load('mediaable');

        if(! ($media->mediaable instanceof Post) || $media->mediaable->user_id !== me()->id) {
            return $this->responseUnauthorizedError();
        }

        $metadata = $media->metadata ?? [];

        if(
            ! in_array(data_get($metadata, 'provider'), ['r2_temp', 'r2_direct'], true) ||
            data_get($metadata, 'upload_type') !== 'raw' ||
            data_get($metadata, 'upload_state') === 'uploaded'
        ) {
            return $this->responseValidationError([
                'message' => 'Invalid direct upload session.',
                'errors' => [
                    'video' => [
                        'Invalid direct upload session.'
                    ]
                ]
            ]);
        }

        $videoFile = $request->file('video');
        $requestStream = null;
        $readStream = null;
        $videoSize = (int) ($videoFile?->getSize() ?? 0);
        $contentType = (string) ($videoFile?->getMimeType() ?: $request->input('content_type') ?: $media->mime ?: 'video/mp4');
        $maxRawFallbackSize = max(5, (int) config('media.cloudflare.r2.raw_fallback_max_mb', 8)) * 1024 * 1024;

        if(! $videoFile) {
            $requestStream = $this->openRawRequestStream($request);
            $readStream = $requestStream['stream'] ?? null;
            $videoSize = (int) ($requestStream['size'] ?? 0);
        }

        if($videoSize < 1 || $videoSize > ($maxRawFallbackSize + (1024 * 1024))) {
            return $this->responseValidationError([
                'message' => 'Video is too large for server fallback upload.',
                'errors' => [
                    'video' => [
                        'Video is too large for server fallback upload.'
                    ]
                ]
            ]);
        }

        if($videoFile) {
            $readStream = fopen($videoFile->getRealPath(), 'rb');
        }

        if(! is_resource($readStream)) {
            return $this->responseValidationError([
                'message' => 'Unable to read uploaded video file.',
                'errors' => [
                    'video' => [
                        'Unable to read uploaded video file.'
                    ]
                ]
            ]);
        }

        try {
            $r2DirectUploadService->uploadRawObject(
                $media->source_path,
                $readStream,
                $contentType ?: (string) ($media->mime ?: 'video/mp4'),
                $media->disk
            );
        }
        catch (Exception $e) {
            return $this->responseValidationError([
                'message' => $e->getMessage(),
                'errors' => [
                    'video' => [
                        $e->getMessage()
                    ]
                ]
            ]);
        }
        finally {
            fclose($readStream);
        }

        return $this->responseSuccess([
            'data' => [
                'uploaded' => true,
            ]
        ]);
    }

    public function uploadVideoPart(Request $request, R2DirectUploadService $r2DirectUploadService)
    {
        $request->validate([
            'media_id' => ['required', 'integer'],
            'uid' => ['required', 'string', 'max:255'],
            'upload_id' => ['required', 'string', 'max:2048'],
            'part_number' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $media = Media::query()->find($request->integer('media_id'));

        if(empty($media) || $media->source_path !== $request->input('uid')) {
            return $this->responseNotFoundError();
        }

        $media->load('mediaable');

        if(! ($media->mediaable instanceof Post) || $media->mediaable->user_id !== me()->id) {
            return $this->responseUnauthorizedError();
        }

        $metadata = $media->metadata ?? [];
        $uploadId = (string) $request->input('upload_id');
        $partNumber = $request->integer('part_number');

        if(
            ! in_array(data_get($metadata, 'provider'), ['r2_temp', 'r2_direct'], true) ||
            data_get($metadata, 'upload_type') !== 'multipart' ||
            data_get($metadata, 'upload_state') === 'uploaded' ||
            $uploadId !== (string) data_get($metadata, 'upload_id')
        ) {
            return $this->responseValidationError([
                'message' => 'Invalid multipart upload session.',
                'errors' => [
                    'video' => [
                        'Invalid multipart upload session.'
                    ]
                ]
            ]);
        }

        $partsCount = (int) data_get($metadata, 'parts_count', 0);

        if($partsCount > 0 && $partNumber > $partsCount) {
            return $this->responseValidationError([
                'message' => 'Invalid multipart upload part number.',
                'errors' => [
                    'video' => [
                        'Invalid multipart upload part number.'
                    ]
                ]
            ]);
        }

        $partFile = $request->file('part');
        $requestStream = null;
        $readStream = null;
        $partSize = (int) ($partFile?->getSize() ?? 0);

        if(! $partFile) {
            $requestStream = $this->openRawRequestStream($request);
            $readStream = $requestStream['stream'] ?? null;
            $partSize = (int) ($requestStream['size'] ?? 0);
        }

        $configuredPartSize = max(5, (int) config('media.cloudflare.r2.multipart_part_size_mb', 5)) * 1024 * 1024;
        $expectedPartSize = (int) data_get($metadata, 'part_size', $configuredPartSize);
        $maxPartSize = max(1, $expectedPartSize) + (1024 * 1024);

        if($partSize < 1 || $partSize > $maxPartSize) {
            return $this->responseValidationError([
                'message' => 'Invalid multipart upload part size.',
                'errors' => [
                    'video' => [
                        'Invalid multipart upload part size.'
                    ]
                ]
            ]);
        }

        if($partFile) {
            $readStream = fopen($partFile->getRealPath(), 'rb');
        }

        if(! is_resource($readStream)) {
            return $this->responseValidationError([
                'message' => 'Unable to read uploaded video part.',
                'errors' => [
                    'video' => [
                        'Unable to read uploaded video part.'
                    ]
                ]
            ]);
        }

        try {
            $etag = $r2DirectUploadService->uploadMultipartPart(
                $media->source_path,
                $uploadId,
                $partNumber,
                $readStream,
                $media->disk
            );
        }
        catch (Exception $e) {
            return $this->responseValidationError([
                'message' => $e->getMessage(),
                'errors' => [
                    'video' => [
                        $e->getMessage()
                    ]
                ]
            ]);
        }
        finally {
            fclose($readStream);
        }

        return $this->responseSuccess([
            'data' => [
                'part_number' => $partNumber,
                'etag' => $etag,
            ]
        ]);
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

    private function openRawRequestStream(Request $request): ?array
    {
        $requestBody = $request->getContent(true);

        if(! is_resource($requestBody)) {
            return null;
        }

        $stream = tmpfile();

        if(! is_resource($stream)) {
            return null;
        }

        $bytes = stream_copy_to_stream($requestBody, $stream);

        if($bytes === false || $bytes < 1) {
            fclose($stream);

            return null;
        }

        rewind($stream);

        return [
            'stream' => $stream,
            'size' => (int) $bytes,
        ];
    }

    private function finalizeDirectR2Upload(Media $media, array $metadata)
    {
        $post = $media->mediaable;
        $postWasProcessing = $post instanceof Post && $post->status === PostStatus::PROCESSING_VIDEO;
        $originalSize = (int) ($media->size ?: data_get($metadata, 'original_size', 0));
        $now = now()->toIso8601String();

        $media->status = MediaStatus::PROCESSED;
        $media->metadata = array_merge($metadata, [
            'upload_state' => 'uploaded',
            'upload_progress' => 100,
            'upload_completed_at' => data_get($metadata, 'upload_completed_at', $now),
            'provider' => 'r2_direct',
            'processed_at' => $now,
            'processing_progress' => 100,
            'processing_state' => 'processed',
            'processing_updated_at' => $now,
            'processing_fallback' => 'direct_final_upload',
            'original_size' => $originalSize ?: $media->size,
            'optimized_size' => $media->size,
        ]);
        $media->save();

        if($postWasProcessing) {
            $post->status = PostStatus::ACTIVE;
            $post->save();
        }

        $this->broadcastMediaUpdated($media);

        if($postWasProcessing) {
            event(new MediaProcessedEvent($media->refresh(), $post->user_id));
            event(new PublicTimelinePostCreatedEvent($post->refresh()));
        }

        return $this->responseSuccess([
            'data' => [
                'media' => MediaResource::make($media->refresh()),
            ]
        ]);
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
