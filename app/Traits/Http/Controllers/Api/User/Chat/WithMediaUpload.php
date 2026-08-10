<?php

namespace App\Traits\Http\Controllers\Api\User\Chat;

use App\Constants\Filesystem;
use App\Enums\Chat\MessageType;
use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Jobs\User\Chat\ProcessChatVideo;
use App\Models\Message;
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use App\Services\Filesystem\Upload\AudioUploadService;
use App\Services\Filesystem\Upload\DocumentUploadService;
use App\Services\Filesystem\Upload\ImageUploadService;
use App\Services\Filesystem\Upload\VideoThumbnailService;
use App\Services\Filesystem\Upload\VideoUploadService;
use Exception;
use FFMpeg\Format\Audio\Mp3;
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

    private function uploadMedia(Message $messageData, UploadedFile $mediaData, string $mediaType, int $mediaDuration)
    {
        $this->videoUploadService = app(VideoUploadService::class);
        $this->audioUploadService = app(AudioUploadService::class);
        $this->roundRobinService = app(RoundRobinService::class);
        $this->videoThumbnailService = app(VideoThumbnailService::class);
        $this->imageUploadService = app(ImageUploadService::class);
        $this->documentUploadService = app(DocumentUploadService::class);
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
        $sourceAbsolutePath = storage_local_path($audioPath);
        $optimizedAudioPath = $this->audioUploadService->generateAudioTemporaryFilePath('mp3');
        $optimizedAbsolutePath = storage_local_path($optimizedAudioPath);

        $format = new Mp3();
        $format->setAudioKiloBitrate((int) config('chat.processing.audio.bitrate', 96));

        $this->audioUploadService
            ->getFFMpeg()
            ->open($sourceAbsolutePath)
            ->save($format, $optimizedAbsolutePath);

        return $optimizedAudioPath;
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
