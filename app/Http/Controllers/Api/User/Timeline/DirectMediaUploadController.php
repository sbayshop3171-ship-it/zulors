<?php

namespace App\Http\Controllers\Api\User\Timeline;

use Exception;
use App\Models\Post;
use App\Models\Media;
use Illuminate\Http\Request;
use App\Enums\Post\PostType;
use App\Enums\Media\MediaType;
use App\Enums\Media\MediaStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\Media\MediaResource;
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
        ]);

        if(! $r2DirectUploadService->isConfigured() && ! $cloudflareStreamService->isConfigured()) {
            return $this->responseSuccess([
                'data' => [
                    'direct_upload' => false,
                    'reason' => 'direct_upload_not_configured'
                ]
            ]);
        }

        $this->fetchOrInitializeDraftPost();

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
                    'metadata' => [
                        'provider' => 'r2_temp',
                        'temp_disk' => $uploadData['disk'],
                        'final_disk' => $uploadData['final_disk'],
                        'temp_path' => $uploadData['path'],
                        'upload_state' => 'waiting_for_upload',
                        'upload_url_expires_at' => $uploadData['expires_at'],
                        'upload_method' => $uploadData['upload_method'],
                        'upload_type' => $uploadData['upload_type'],
                        'upload_id' => $uploadData['upload_id'] ?? null,
                        'part_size' => $uploadData['part_size'] ?? null,
                        'parts_count' => count($uploadData['parts'] ?? []),
                        'original_name' => (string) $request->input('name'),
                    ]
                ]);

                return $this->responseSuccess([
                    'data' => [
                        'direct_upload' => true,
                        'provider' => 'r2_temp',
                        'uid' => $uploadData['uid'],
                        'upload_url' => $uploadData['upload_url'],
                        'upload_method' => $uploadData['upload_method'],
                        'upload_type' => $uploadData['upload_type'],
                        'upload_headers' => $uploadData['upload_headers'],
                        'upload_id' => $uploadData['upload_id'] ?? null,
                        'part_size' => $uploadData['part_size'] ?? null,
                        'parts' => $uploadData['parts'] ?? [],
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
                'metadata' => [
                    'provider' => 'cloudflare_stream',
                    'cloudflare_uid' => $uploadData['uid'],
                    'upload_state' => 'waiting_for_upload',
                    'upload_url_expires_at' => $uploadData['expires_at'],
                    'playback' => $uploadData['playback'],
                    'original_name' => (string) $request->input('name'),
                ]
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

    public function completeVideoUpload(Request $request, CloudflareStreamService $cloudflareStreamService, R2DirectUploadService $r2DirectUploadService)
    {
        $request->validate([
            'media_id' => ['required', 'integer'],
            'uid' => ['required', 'string', 'max:255'],
            'upload_id' => ['nullable', 'string', 'max:255'],
            'parts' => ['nullable', 'array'],
            'parts.*.part_number' => ['required_with:parts', 'integer', 'min:1', 'max:10000'],
            'parts.*.etag' => ['required_with:parts', 'string', 'max:255'],
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

        if(data_get($metadata, 'provider') === 'r2_temp') {
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
                        $request->array('parts')
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

            if(! $r2DirectUploadService->uploaded($media->source_path)) {
                return $this->responseValidationError([
                    'message' => 'Direct upload file was not found on R2.',
                    'errors' => [
                        'video' => [
                            'Direct upload file was not found on R2.'
                        ]
                    ]
                ]);
            }

            $metadata['upload_state'] = 'uploaded';
            $metadata['upload_completed_at'] = now()->toIso8601String();

            $media->metadata = $metadata;
            $media->save();

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
        $metadata['upload_completed_at'] = now()->toIso8601String();
        $metadata['playback'] = $cloudflareStreamService->playbackUrls($media->source_path);

        $media->metadata = $metadata;
        $media->save();

        return $this->responseSuccess([
            'data' => [
                'media' => MediaResource::make($media->refresh()),
            ]
        ]);
    }
}
