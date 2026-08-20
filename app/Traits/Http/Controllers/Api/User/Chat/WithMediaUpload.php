<?php

namespace App\Traits\Http\Controllers\Api\User\Chat;

use App\Constants\Filesystem;
use App\Enums\Chat\MessageType;
use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Jobs\User\Chat\ProcessChatAudio;
use App\Jobs\User\Chat\ProcessChatVideo;
use App\Models\Message;
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use App\Services\Filesystem\Upload\AudioUploadService;
use App\Services\Filesystem\Upload\DocumentUploadService;
use App\Services\Filesystem\Upload\ImageUploadService;
use App\Services\Filesystem\Upload\VideoThumbnailService;
use App\Services\Filesystem\Upload\VideoUploadService;
use Exception;
use FFMpeg\Format\Video\X264;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait WithMediaUpload
{
    private VideoUploadService $videoUploadService;
    private RoundRobinService $roundRobinService;
    private VideoThumbnailService $videoThumbnailService;
    private ImageUploadService $imageUploadService;
    private AudioUploadService $audioUploadService;
    private DocumentUploadService $documentUploadService;
    private Message $messageData;
    private int $mediaDuration;

    private function initializeMediaUploadServices(): void
    {
        $this->videoUploadService = app(VideoUploadService::class);
        $this->audioUploadService = app(AudioUploadService::class);
        $this->roundRobinService = app(RoundRobinService::class);
        $this->videoThumbnailService = app(VideoThumbnailService::class);
        $this->imageUploadService = app(ImageUploadService::class);
        $this->documentUploadService = app(DocumentUploadService::class);
    }

    private function uploadMedia(Message $messageData, UploadedFile $mediaData, string $mediaType, int $mediaDuration)
    {
        $this->initializeMediaUploadServices();
        $this->messageData = $messageData;
        $this->mediaDuration = $mediaDuration;

        if($mediaType === 'video') {
            return $this->uploadVideo($mediaData);
        }
        elseif($mediaType === 'audio') {
            return $this->uploadAudio($mediaData);
        }
        elseif($mediaType === 'image') {
            return $this->uploadImage($mediaData);
        }
        elseif($mediaType === 'document') {
            return $this->uploadDocument($mediaData);
        }
    }

    private function createPendingAudioMedia(Message $messageData, array $audioDetails = []): void
    {
        $this->initializeMediaUploadServices();

        $durationSeconds = max(1, (int) data_get($audioDetails, 'duration_seconds', 1));
        $requestedExtension = $this->normalizeChatAudioExtension(
            data_get($audioDetails, 'extension'),
            data_get($audioDetails, 'mime_type')
        );
        $requestedMime = $this->normalizeChatAudioMime(
            data_get($audioDetails, 'mime_type'),
            $requestedExtension
        );
        $fileName = trim((string) data_get($audioDetails, 'file_name', ''));

        $messageData->media()->create([
            'source_path' => '',
            'type' => MediaType::AUDIO,
            'status' => MediaStatus::PROCESSING,
            'disk' => '',
            'extension' => '',
            'mime' => '',
            'size' => 0,
            'metadata' => [
                'duration' => parse_duration($durationSeconds),
                'duration_seconds' => $durationSeconds,
                'file_name' => $fileName,
                'original_name' => $fileName,
                'requested_extension' => $requestedExtension,
                'requested_mime' => $requestedMime,
                'processing_state' => 'waiting_for_upload',
            ],
        ]);
    }

    private function uploadPendingAudioMedia(Message $messageData, UploadedFile $chatAudioFile): bool
    {
        $this->initializeMediaUploadServices();

        $messageMedia = $messageData->media;

        if(empty($messageMedia) || ! $messageMedia->type->isAudio()) {
            throw new Exception('Pending chat audio media not found.');
        }

        $audioData = $this->audioUploadService
            ->setStorageDisk($this->roundRobinService->getNextDisk())
            ->tempSaveLocally($chatAudioFile);

        $normalizedExtension = $this->normalizeChatAudioExtension(
            $chatAudioFile->getClientOriginalExtension(),
            $chatAudioFile->getClientMimeType() ?: $chatAudioFile->getMimeType(),
            data_get($messageMedia->metadata, 'requested_extension', 'webm')
        );
        $normalizedMime = $this->normalizeChatAudioMime(
            $chatAudioFile->getClientMimeType() ?: $chatAudioFile->getMimeType(),
            $normalizedExtension
        );

        if($this->shouldQueueChatAudioProcessing($normalizedExtension, $normalizedMime)) {
            $this->queuePendingAudioMedia($messageData, $messageMedia, $audioData, $chatAudioFile, $normalizedExtension, $normalizedMime);

            return true;
        }

        $this->storePendingAudioAsReady($messageData, $messageMedia, $audioData, $chatAudioFile, $normalizedExtension, $normalizedMime);

        return false;
    }

    private function storePendingAudioAsReady(
        Message $messageData,
        $messageMedia,
        array $audioData,
        UploadedFile $chatAudioFile,
        string $extension,
        string $mime
    ): void {
        $durationSeconds = max(1, (int) ($audioData['duration_seconds'] ?? 1));
        $finalDisk = (string) ($audioData['disk'] ?? $this->roundRobinService->getNextDisk());
        $audioAbsolutePath = storage_local_path($audioData['audio_path']);
        $audioFileSize = file_exists($audioAbsolutePath)
            ? (int) (filesize($audioAbsolutePath) ?: 0)
            : (int) ($chatAudioFile->getSize() ?: 0);

        $uploadedAudio = $this->audioUploadService
            ->setNamespace(Filesystem::mediaNamespace('chats/audios'))
            ->setStorageDisk($finalDisk)
            ->setDefaultExtension($extension)
            ->upload($audioAbsolutePath);

        $metadata = $messageMedia->metadata ?? [];

        $messageMedia->source_path = $uploadedAudio['audio_path'];
        $messageMedia->status = MediaStatus::PROCESSED;
        $messageMedia->disk = $uploadedAudio['disk'];
        $messageMedia->extension = $extension;
        $messageMedia->mime = $mime;
        $messageMedia->size = $audioFileSize;
        $messageMedia->metadata = array_merge($metadata, [
            'duration' => $audioData['duration'] ?? parse_duration($durationSeconds),
            'duration_seconds' => $durationSeconds,
            'file_name' => $chatAudioFile->getClientOriginalName(),
            'original_name' => $chatAudioFile->getClientOriginalName(),
            'processing_state' => 'processed',
            'processed_at' => now()->toIso8601String(),
            'original_extension' => $extension,
            'original_mime' => $mime,
        ]);
        $messageMedia->save();

        Storage::disk('local')->delete($audioData['audio_path']);

        $messageData->update([
            'type' => MessageType::AUDIO,
        ]);
    }

    private function queuePendingAudioMedia(
        Message $messageData,
        $messageMedia,
        array $audioData,
        UploadedFile $chatAudioFile,
        string $extension,
        string $mime
    ): void {
        $durationSeconds = max(1, (int) ($audioData['duration_seconds'] ?? 1));
        $audioAbsolutePath = storage_local_path($audioData['audio_path']);
        $audioFileSize = file_exists($audioAbsolutePath)
            ? (int) (filesize($audioAbsolutePath) ?: 0)
            : (int) ($chatAudioFile->getSize() ?: 0);
        $finalDisk = (string) ($audioData['disk'] ?? $this->roundRobinService->getNextDisk());
        $metadata = $messageMedia->metadata ?? [];

        $messageMedia->source_path = $audioData['audio_path'];
        $messageMedia->status = MediaStatus::PROCESSING;
        $messageMedia->disk = 'local';
        $messageMedia->extension = $extension;
        $messageMedia->mime = $mime;
        $messageMedia->size = $audioFileSize;
        $messageMedia->metadata = array_merge($metadata, [
            'duration' => $audioData['duration'] ?? parse_duration($durationSeconds),
            'duration_seconds' => $durationSeconds,
            'file_name' => $chatAudioFile->getClientOriginalName(),
            'original_name' => $chatAudioFile->getClientOriginalName(),
            'original_extension' => $extension,
            'original_mime' => $mime,
            'original_size' => $audioFileSize,
            'temp_path' => $audioData['audio_path'],
            'final_disk' => $finalDisk,
            'processing_state' => 'queued',
            'processing_progress' => 5,
            'processing_dispatched_at' => now()->toIso8601String(),
            'processing_updated_at' => now()->toIso8601String(),
        ]);
        $messageMedia->save();

        $messageData->update([
            'type' => MessageType::AUDIO,
        ]);

        ProcessChatAudio::dispatchAfterResponse($messageData)
            ->onQueue(config('media.queues.audio'));
    }

    private function shouldQueueChatAudioProcessing(string $extension, string $mime = ''): bool
    {
        return ! in_array(
            $this->normalizeChatAudioExtension($extension, $mime, $extension),
            ['m4a', 'mp3', 'wav', 'aac'],
            true
        );
    }

    private function normalizeChatAudioExtension(?string $extension, ?string $mime = null, string $fallback = 'webm'): string
    {
        $normalizedExtension = strtolower(trim((string) $extension));
        $normalizedMime = strtolower(trim((string) $mime));

        if(in_array($normalizedExtension, ['mp4', 'm4a'], true) || str_contains($normalizedMime, 'audio/mp4') || str_contains($normalizedMime, 'audio/x-m4a')) {
            return 'm4a';
        }

        if(in_array($normalizedExtension, ['mp3', 'mpeg'], true) || str_contains($normalizedMime, 'audio/mpeg') || str_contains($normalizedMime, 'audio/mp3')) {
            return 'mp3';
        }

        if(in_array($normalizedExtension, ['wav', 'wave'], true) || str_contains($normalizedMime, 'audio/wav') || str_contains($normalizedMime, 'audio/x-wav')) {
            return 'wav';
        }

        if($normalizedExtension === 'aac' || str_contains($normalizedMime, 'audio/aac')) {
            return 'aac';
        }

        if($normalizedExtension === 'ogg' || str_contains($normalizedMime, 'audio/ogg')) {
            return 'ogg';
        }

        if($normalizedExtension === 'webm' || str_contains($normalizedMime, 'audio/webm')) {
            return 'webm';
        }

        return strtolower(trim($fallback)) ?: 'webm';
    }

    private function normalizeChatAudioMime(?string $mime = null, ?string $extension = null): string
    {
        $normalizedMime = strtolower(trim((string) $mime));

        if($normalizedMime !== '') {
            return $normalizedMime;
        }

        return match($this->normalizeChatAudioExtension($extension, $mime, 'webm')) {
            'm4a' => 'audio/mp4',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'aac' => 'audio/aac',
            'ogg' => 'audio/ogg',
            default => 'audio/webm',
        };
    }

    private function uploadImage(UploadedFile $mediaData)
    {
        try {
            $imageStorageDisk = $this->roundRobinService->getNextDisk();
            $imageData = $this->imageUploadService
                ->load($mediaData->getRealPath())
                ->setNamespace(Filesystem::mediaNamespace('chats/images'))
                ->setStorageDisk($imageStorageDisk)
                ->watermark()
                ->compress(config('chat.processing.image.compress_rate'))
                ->upload();

            $this->messageData->media()->create([
                'source_path' => $imageData['image_path'],
                'type' => MediaType::IMAGE,
                'status' => MediaStatus::PROCESSED,
                'disk' => $imageData['disk'],
                'extension' => $mediaData->getClientOriginalExtension(),
                'mime' => $mediaData->getClientMimeType(),
                'size' => $imageData['image_size'],
                'lqip_base64' => null,
                'metadata' => []
            ]);

            $this->messageData->update([
                'type' => MessageType::IMAGE,
            ]);
        }
        catch(Exception $e) {
            // Pass
        }
    }

    private function uploadVideo(UploadedFile $chatVideoFile)
    {
        try {
            $videoData = $this->videoUploadService
                ->tempSaveLocally($chatVideoFile);

            $videoThumbnailPath = $this->videoThumbnailService
                ->setSecondsOffset(1)
                ->generateThumbnail($videoData['video_path']);

            $videoPublicDisk = $this->chatVideoPublicDisk();

            $imageData = $this->imageUploadService
                ->load($videoThumbnailPath)
                ->setNamespace(Filesystem::mediaNamespace('chats/video_thumbnails'))
                ->setStorageDisk($videoPublicDisk)
                ->compress(config('chat.processing.video_thumbnail.compress_rate'))
                ->upload();

            if(is_file($videoThumbnailPath)) {
                unlink($videoThumbnailPath);
            }

            if($this->shouldUseR2ChatVideoPipeline()) {
                $queuedVideo = $this->videoUploadService
                    ->setStorageDisk($this->chatVideoTempDisk())
                    ->setNamespace(Filesystem::mediaNamespace('chats/raw_videos'))
                    ->setDefaultExtension($chatVideoFile->getClientOriginalExtension())
                    ->upload(storage_local_path($videoData['video_path']));

                $this->messageData->media()->create([
                    'source_path' => $queuedVideo['video_path'],
                    'type' => MediaType::VIDEO,
                    'status' => MediaStatus::PROCESSING,
                    'disk' => $queuedVideo['disk'],
                    'extension' => $chatVideoFile->getClientOriginalExtension(),
                    'mime' => $chatVideoFile->getClientMimeType(),
                    'size' => (int) ($queuedVideo['video_size'] ?? $chatVideoFile->getSize()),
                    'thumbnail_path' => $imageData['image_path'],
                    'thumbnail_size' => $imageData['image_size'],
                    'thumbnail_disk' => $imageData['disk'],
                    'metadata' => [
                        'duration' => parse_duration(intval($this->mediaDuration)),
                        'duration_seconds' => (int) $this->mediaDuration,
                        'is_portrait' => (bool) ($videoData['is_portrait'] ?? false),
                        'dimensions' => $videoData['dimensions'] ?? [],
                        'aspect_ratio' => $videoData['aspect_ratio'] ?? null,
                        'provider' => 'r2_temp',
                        'upload_state' => 'uploaded',
                        'upload_completed_at' => now()->toIso8601String(),
                        'temp_disk' => $queuedVideo['disk'],
                        'final_disk' => $videoPublicDisk,
                        'processing_progress' => 5,
                        'processing_state' => 'queued',
                        'processing_dispatched_at' => now()->toIso8601String(),
                        'processing_updated_at' => now()->toIso8601String(),
                        'original_size' => (int) ($queuedVideo['video_size'] ?? $chatVideoFile->getSize()),
                    ]
                ]);

                $this->messageData->update([
                    'type' => MessageType::VIDEO,
                ]);

                ProcessChatVideo::dispatchAfterResponse($this->messageData)
                    ->onQueue(config('media.queues.video'));

                return;
            }

            if(config('chat.enable_video_compression')) {
                $this->compressVideo(storage_local_path($videoData['video_path']));
            }
            else {
                $this->videoUploadService->setDefaultExtension($chatVideoFile->getClientOriginalExtension());
            }

            $videoData = $this->videoUploadService
                ->setStorageDisk($videoPublicDisk)
                ->setNamespace(Filesystem::mediaNamespace('chats/videos'))
                ->upload(storage_local_path($videoData['video_path']));

            $this->messageData->media()->create([
                'source_path' => $videoData['video_path'],
                'type' => MediaType::VIDEO,
                'status' => MediaStatus::PROCESSED,
                'disk' => $videoPublicDisk,
                'extension' => $chatVideoFile->getClientOriginalExtension(),
                'mime' => $chatVideoFile->getClientMimeType(),
                'size' => $chatVideoFile->getSize(),
                'thumbnail_path' => $imageData['image_path'],
                'thumbnail_size' => $imageData['image_size'],
                'thumbnail_disk' => $imageData['disk'],
                'metadata' => [
                    'duration' => parse_duration(intval($this->mediaDuration)),
                    'duration_seconds' => (int) $this->mediaDuration,
                    'is_portrait' => false
                ]
            ]);

            $this->messageData->update([
                'type' => MessageType::VIDEO,
            ]);
        } catch (Exception $e) {
            // Pass
        }
    }

    private function uploadAudio(UploadedFile $chatAudioFile)
    {
        try {
            $audioData = $this->audioUploadService
                ->setStorageDisk($this->roundRobinService->getNextDisk())
                ->tempSaveLocally($chatAudioFile);

            $audioMetadata = $this->optimizeAudioForChat($audioData, $chatAudioFile);

            $audioData = $this->audioUploadService
                ->setNamespace(Filesystem::mediaNamespace('chats/audios'))
                ->setStorageDisk($audioMetadata['disk'])
                ->setDefaultExtension($audioMetadata['extension'])
                ->upload(storage_local_path($audioMetadata['audio_path']));

            $this->messageData->media()->create([
                'source_path' => $audioData['audio_path'],
                'type' => MediaType::AUDIO,
                'status' => MediaStatus::PROCESSED,
                'disk' => $audioData['disk'],
                'extension' => $audioMetadata['extension'],
                'mime' => $audioMetadata['mime'],
                'size' => $audioMetadata['size'],
                'metadata' => [
                    'duration' => $audioMetadata['duration'],
                    'duration_seconds' => $audioMetadata['duration_seconds'],
                    'file_name' => $chatAudioFile->getClientOriginalName(),
                    'original_name' => $chatAudioFile->getClientOriginalName(),
                ]
            ]);

            $this->messageData->update([
                'type' => MessageType::AUDIO,
            ]);
        }
        catch(Exception $e) {
            // Pass
        }
    }

    private function optimizeAudioForChat(array $audioData, UploadedFile $chatAudioFile): array
    {
        $preferredExtension = strtolower((string) config('chat.processing.audio.preferred_extension', 'mp3'));
        $optimizedAudioPath = $audioData['audio_path'];
        $optimizedExtension = strtolower($chatAudioFile->getClientOriginalExtension() ?: $preferredExtension ?: 'webm');
        $optimizedMime = $chatAudioFile->getClientMimeType() ?: $chatAudioFile->getMimeType() ?: 'audio/webm';
        $optimizedDurationSeconds = max(1, (int) ($audioData['duration_seconds'] ?? $this->mediaDuration ?: 1));
        $optimizedDuration = $audioData['duration'] ?? parse_duration($optimizedDurationSeconds);

        if($preferredExtension === 'mp3') {
            try {
                $optimizedAudioPath = $this->transcodeChatAudioToMp3($audioData['audio_path']);
                $optimizedExtension = 'mp3';
                $optimizedMime = 'audio/mpeg';
                $optimizedDurationSeconds = $this->audioUploadService->getAudioDurationSeconds($optimizedAudioPath);
                $optimizedDuration = parse_duration($optimizedDurationSeconds);

                if($optimizedAudioPath !== $audioData['audio_path']) {
                    Storage::disk('local')->delete($audioData['audio_path']);
                }
            }
            catch(Exception $e) {
                $optimizedAudioPath = $audioData['audio_path'];
            }
        }

        $optimizedAbsolutePath = storage_local_path($optimizedAudioPath);
        $optimizedSize = file_exists($optimizedAbsolutePath)
            ? (int) (filesize($optimizedAbsolutePath) ?: 0)
            : (int) ($chatAudioFile->getSize() ?: 0);

        return array_merge($audioData, [
            'audio_path' => $optimizedAudioPath,
            'duration' => $optimizedDuration,
            'duration_seconds' => $optimizedDurationSeconds,
            'extension' => $optimizedExtension,
            'mime' => $optimizedMime,
            'size' => $optimizedSize,
        ]);
    }

    private function transcodeChatAudioToMp3(string $audioPath): string
    {
        return $this->audioUploadService->transcodeToMp3($audioPath, (int) config('chat.processing.audio.bitrate', 96));
    }

    private function uploadDocument(UploadedFile $chatDocumentFile)
    {
        try {
            $documentData = $this->documentUploadService
                ->setNamespace(Filesystem::mediaNamespace('chats/documents'))
                ->setStorageDisk($this->roundRobinService->getNextDisk())
                ->upload($chatDocumentFile);

            $this->messageData->media()->create([
                'source_path' => $documentData['document_path'],
                'type' => MediaType::DOCUMENT,
                'status' => MediaStatus::PROCESSED,
                'disk' => $documentData['disk'],
                'extension' => $chatDocumentFile->getClientOriginalExtension(),
                'mime' => $chatDocumentFile->getClientMimeType(),
                'size' => $chatDocumentFile->getSize(),
                'metadata' => [
                    'file_name' => $chatDocumentFile->getClientOriginalName()
                ]
            ]);

            $this->messageData->update([
                'type' => MessageType::DOCUMENT,
            ]);
        }
        catch(Exception $e) {
            // Pass
        }
    }

    private function compressVideo(string $videoPath)
    {
        $ffmpeg = $this->videoUploadService->getFFMpeg();

        $video = $ffmpeg->open($videoPath);
        $squareSize = (int) config('chat.processing.video.square_size');

        $format = new X264();
        $format->setKiloBitrate(0)
               ->setAudioKiloBitrate((int) config('chat.processing.video.audio_bitrate'))
               ->setAdditionalParameters([
                   '-preset',
                   config('chat.processing.video.preset'),
                   '-crf',
                   (string) config('chat.processing.video.crf'),
                   '-movflags',
                   '+faststart',
                   '-pix_fmt',
                   'yuv420p',
                   '-vf',
                   "scale={$squareSize}:{$squareSize}:force_original_aspect_ratio=increase,crop={$squareSize}:{$squareSize}",
               ]);

        $videoTempNewPath = storage_local_path($this->videoUploadService->generateVideoTemporaryFilePath("compressed.{$this->videoUploadService->videoDefaultExtension}"));

        $video->save($format, $videoTempNewPath);

        rename($videoTempNewPath, $videoPath);
    }

    private function shouldUseR2ChatVideoPipeline(): bool
    {
        return $this->diskEnabled($this->chatVideoTempDisk())
            && $this->diskEnabled((string) config('media.cloudflare.r2.final_disk', 'r2_final'));
    }

    private function chatVideoTempDisk(): string
    {
        return (string) config('media.cloudflare.r2.temp_disk', 'r2_temp');
    }

    private function chatVideoPublicDisk(): string
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
