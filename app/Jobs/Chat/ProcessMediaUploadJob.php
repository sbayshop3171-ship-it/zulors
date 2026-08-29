/**
 * Async Background Queue Manager for Media Processing
 * Handles media compression, thumbnail generation, and uploads
 * Non-blocking to keep messaging latency sub-100ms
 */

namespace App\Jobs\Chat;

use App\Models\Message;
use App\Models\MediaFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

class ProcessMediaUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $messageId;
    private $mediaId;
    private $filePath;
    private $mediaType;

    public $tries = 3;
    public $timeout = 300; // 5 minutes
    public $queue = 'media';
    public $backoff = [10, 60, 300]; // Retry delays

    public function __construct(
        int $messageId,
        int $mediaId,
        string $filePath,
        string $mediaType = 'image'
    ) {
        $this->messageId = $messageId;
        $this->mediaId = $mediaId;
        $this->filePath = $filePath;
        $this->mediaType = $mediaType;
    }

    public function handle()
    {
        try {
            $startTime = microtime(true);

            $media = MediaFile::find($this->mediaId);
            if (!$media) {
                Log::warning("Media file not found: {$this->mediaId}");
                return;
            }

            // Process based on media type
            switch ($this->mediaType) {
                case 'image':
                    $this->processImage($media);
                    break;
                case 'voice':
                case 'audio':
                    $this->processAudio($media);
                    break;
                case 'video':
                    $this->processVideo($media);
                    break;
                default:
                    $this->processFile($media);
            }

            // Update media status to ready
            $media->update([
                'status' => 'ready',
                'processed_at' => now()
            ]);

            // Broadcast media ready event
            broadcast(new \App\Events\User\Chat\MediaReadyEvent($media));

            $elapsedMs = (microtime(true) - $startTime) * 1000;

            Log::info("Media processed successfully", [
                'media_id' => $this->mediaId,
                'message_id' => $this->messageId,
                'type' => $this->mediaType,
                'elapsed_ms' => round($elapsedMs, 2)
            ]);
        } catch (\Exception $e) {
            Log::error("Media processing failed: " . $e->getMessage(), [
                'media_id' => $this->mediaId,
                'exception' => $e
            ]);

            $media = MediaFile::find($this->mediaId);
            if ($media) {
                $media->update([
                    'status' => 'error',
                    'error_message' => $e->getMessage()
                ]);
            }

            throw $e;
        }
    }

    private function processImage(MediaFile $media)
    {
        $disk = Storage::disk('public');

        // Generate thumbnail (max 300x300)
        $thumbnailPath = "media/thumbnails/" . uniqid() . ".jpg";
        $imageManager = new ImageManager();
        $image = $imageManager->make($disk->path($media->file_path))
            ->fit(300, 300, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

        $disk->put($thumbnailPath, $image->encode());

        $media->update([
            'thumbnail_path' => $thumbnailPath,
            'dimensions' => json_encode([
                'width' => $image->width(),
                'height' => $image->height()
            ])
        ]);
    }

    private function processAudio(MediaFile $media)
    {
        $disk = Storage::disk('public');

        // Extract duration using ffmpeg
        $filePath = $disk->path($media->file_path);
        $command = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1:noprint_wrappers=1 '{$filePath}'";

        exec($command, $output);
        $duration = (float) ($output[0] ?? 0);

        $media->update([
            'duration' => $duration,
            'metadata' => json_encode(['duration_seconds' => $duration])
        ]);
    }

    private function processVideo(MediaFile $media)
    {
        $disk = Storage::disk('public');
        $filePath = $disk->path($media->file_path);

        // Extract duration and generate thumbnail
        $duration = $this->getVideoDuration($filePath);
        $thumbnailPath = $this->generateVideoThumbnail($filePath);

        $media->update([
            'duration' => $duration,
            'thumbnail_path' => $thumbnailPath,
            'metadata' => json_encode(['duration_seconds' => $duration])
        ]);
    }

    private function processFile(MediaFile $media)
    {
        // For generic files, just mark as processed
        $media->update(['status' => 'ready']);
    }

    private function getVideoDuration(string $filePath): float
    {
        $command = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '{$filePath}'";
        exec($command, $output);
        return (float) ($output[0] ?? 0);
    }

    private function generateVideoThumbnail(string $filePath): string
    {
        $disk = Storage::disk('public');
        $thumbnailPath = "media/thumbnails/" . uniqid() . ".jpg";

        // Extract frame at 1 second
        $command = "ffmpeg -i '{$filePath}' -ss 00:00:01 -vframes 1 -q:v 2 '{$disk->path($thumbnailPath)}' 2>/dev/null";
        exec($command);

        return $thumbnailPath;
    }

    public function failed(\Exception $exception)
    {
        Log::error("Job failed permanently", [
            'media_id' => $this->mediaId,
            'message_id' => $this->messageId,
            'exception' => $exception->getMessage()
        ]);

        // Update media status
        $media = MediaFile::find($this->mediaId);
        if ($media) {
            $media->update(['status' => 'error']);
        }
    }
}

/**
 * Bulk Message Processing Job
 * Handles message creation, reactions, reads in batches
 */
class BulkMessageSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $chatId;
    private $updates;

    public $queue = 'messages';
    public $timeout = 60;

    public function __construct(int $chatId, array $updates)
    {
        $this->chatId = $chatId;
        $this->updates = $updates;
    }

    public function handle()
    {
        try {
            foreach ($this->updates as $update) {
                switch ($update['type'] ?? null) {
                    case 'message_read':
                        $this->markMessageAsRead($update['message_id']);
                        break;
                    case 'message_delete':
                        $this->deleteMessage($update['message_id']);
                        break;
                    case 'reaction_add':
                        $this->addReaction($update['message_id'], $update['reaction']);
                        break;
                }
            }

            Log::info("Bulk message sync completed", [
                'chat_id' => $this->chatId,
                'updates_processed' => count($this->updates)
            ]);
        } catch (\Exception $e) {
            Log::error("Bulk sync failed: " . $e->getMessage());
            throw $e;
        }
    }

    private function markMessageAsRead(int $messageId)
    {
        Message::find($messageId)?->markAsRead();
    }

    private function deleteMessage(int $messageId)
    {
        Message::find($messageId)?->delete();
    }

    private function addReaction(int $messageId, string $reaction)
    {
        // Implementation for adding reactions
    }
}
