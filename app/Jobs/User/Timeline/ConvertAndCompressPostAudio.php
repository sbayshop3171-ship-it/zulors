<?php

namespace App\Jobs\User\Timeline;

use Exception;
use App\Models\Post;
use Illuminate\Support\Str;
use FFMpeg\Format\Audio\Mp3;
use App\Constants\Filesystem;
use App\Enums\Post\PostStatus;
use App\Enums\Media\MediaStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Events\User\Timeline\MediaProcessedEvent;
use App\Services\Filesystem\Upload\AudioUploadService;

class ConvertAndCompressPostAudio implements ShouldQueue
{
    use Queueable;

    public $timeout = (60 * 30); // 30 minutes

    private $postData;

    public function __construct(Post $postData)
    {
        $this->postData = $postData;
    }

    public function handle(): void
    {
        $postMedia = $this->postData->media()->first();

        try {
            $audioUploadService = app(AudioUploadService::class);
            $postMedia = $this->postData->media()->first();

            if (! $audioUploadService) {
                throw new Exception('Required services are not available. Ensure that fileUploaderService and ffmpegService are properly injected.');
            }

            // Get audio local temporary path
            $audioTempOldPath = $postMedia->source_path;

            // Generate new audio temporary path for compressed audio marking it as compressed. [compressed.mp3]
            $audioTempNewPath = $audioUploadService->generateAudioTemporaryFilePath("compressed.{$audioUploadService->audioDefaultExtension}");

            $ffmpeg = $audioUploadService->getFFMpeg();
            $audioOldAbsLocalPath = storage_local_path($audioTempOldPath);
            $audioNewAbsLocalPath = storage_local_path($audioTempNewPath);

            if(config('logging.debugging.audio_process_logging')) {
                $fileOldExists = (file_exists($audioOldAbsLocalPath)) ? 'Yes' : 'No';

                Log::info("Audio with path: {$audioOldAbsLocalPath} loaded. Audio file exists: {$fileOldExists}");
            }

            // Compress audio and save to new path converting it to mp3
            $audio = $ffmpeg->open($audioOldAbsLocalPath);
            $format = new Mp3();
            $format = $format->setAudioKiloBitrate(128);

            $audio->save($format, $audioNewAbsLocalPath);

            if(file_exists($audioNewAbsLocalPath)) {

                // Upload compressed audio to public disk and update post media
                // Public disk is determined by post media with round robin algorithm
                // and it is not local public folder of the application.

                $audioFileDuration = $audioUploadService->getAudioDuration($audioTempNewPath);
                $audioFileSize = filesize($audioNewAbsLocalPath) ?? 0;

                $audioFilePath = $audioUploadService
                    ->setNamespace(Filesystem::mediaNamespace('posts/audios'))
                    ->setStorageDisk($postMedia->disk)
                    ->upload($audioNewAbsLocalPath);


                $mediaMetadata = $postMedia->metadata;
                $newFilename = Str::beforeLast($mediaMetadata['file_name'], '.');

                $mediaMetadata['duration'] = $audioFileDuration;
                $mediaMetadata['file_name'] = "{$newFilename}.{$audioUploadService->audioDefaultExtension}";
                $postMedia->source_path = $audioFilePath['audio_path'];
                $postMedia->extension = $audioUploadService->audioDefaultExtension;
                $postMedia->status = MediaStatus::PROCESSED;
                $postMedia->metadata = $mediaMetadata;
                $postMedia->size = $audioFileSize;
                $postMedia->save();

                $this->postData->status = PostStatus::ACTIVE;

                $this->postData->save();

                if(config('logging.debugging.audio_process_logging')) {
                    $fileNewExists = file_exists($audioNewAbsLocalPath) ? 'Yes' : 'No';

                    Log::info("Compressed audio with new path: {$audioNewAbsLocalPath} saved. Audio new file exists: {$fileNewExists}");
                }

                Storage::disk('local')->delete($audioTempOldPath);
                Storage::disk('local')->delete($audioTempNewPath);

                // Broadcast audio processed event with updated post media and user id
                // to notify users that audio has been processed.

                try {
                    event(new MediaProcessedEvent($postMedia->refresh(), $this->postData->user_id));
                } catch (Exception $e) {
                    Log::error('Failed to broadcast audio processed event: ' . $e->getMessage());
                }
            }
        }

        catch (Exception $e) {
            Log::error('Post audio processing failed after 5 attempts. Error: ' . $e->getMessage());

            $this->fail();
        }
    }

    public function tries(): int
    {
        return 5;
    }
}
