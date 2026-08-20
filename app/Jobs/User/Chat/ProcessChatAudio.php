<?php

namespace App\Jobs\User\Chat;

use App\Actions\Chat\MessageGlobalDeleteAction;
use App\Constants\Filesystem;
use App\Enums\Media\MediaStatus;
use App\Events\User\Chat\MessageDeletedEvent;
use App\Events\User\Chat\MessageMediaReadyEvent;
use App\Models\Message;
use App\Services\Filesystem\Upload\AudioUploadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessChatAudio implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;
    public int $timeout = 60 * 30;

    private Message $messageData;

    public function __construct(Message $messageData)
    {
        $this->messageData = $messageData;
    }

    public function handle(): void
    {
        $messageMedia = null;
        $sourcePath = null;
        $transcodedAudioPath = null;

        try {
            $audioUploadService = app(AudioUploadService::class);
            $this->messageData = Message::query()->with('media')->find($this->messageData->id);

            if(empty($this->messageData)) {
                return;
            }

            $messageMedia = $this->messageData->media;

            if(empty($messageMedia) || ! $messageMedia->type->isAudio() || $messageMedia->status->isProcessed()) {
                return;
            }

            $metadata = $messageMedia->metadata ?? [];
            $sourcePath = data_get($metadata, 'temp_path') ?: $messageMedia->source_path;

            if(blank($sourcePath)) {
                throw new \RuntimeException('Queued chat audio is missing its temporary source path.');
            }

            $transcodedAudioPath = $audioUploadService->transcodeToMp3(
                $sourcePath,
                (int) config('chat.processing.audio.bitrate', 96)
            );

            $durationSeconds = max(1, $audioUploadService->getAudioDurationSeconds($transcodedAudioPath));
            $transcodedAbsolutePath = storage_local_path($transcodedAudioPath);
            $processedSize = file_exists($transcodedAbsolutePath)
                ? (int) (filesize($transcodedAbsolutePath) ?: 0)
                : (int) ($messageMedia->size ?: 0);
            $finalDisk = (string) data_get($metadata, 'final_disk', 'public');

            $uploadedAudio = $audioUploadService
                ->setNamespace(Filesystem::mediaNamespace('chats/audios'))
                ->setStorageDisk($finalDisk)
                ->setDefaultExtension('mp3')
                ->upload($transcodedAbsolutePath);

            $messageMedia->source_path = $uploadedAudio['audio_path'];
            $messageMedia->disk = $uploadedAudio['disk'];
            $messageMedia->status = MediaStatus::PROCESSED;
            $messageMedia->extension = 'mp3';
            $messageMedia->mime = 'audio/mpeg';
            $messageMedia->size = $processedSize;
            $messageMedia->metadata = array_merge($metadata, [
                'duration' => parse_duration($durationSeconds),
                'duration_seconds' => $durationSeconds,
                'file_name' => $this->processedFileName($metadata),
                'original_name' => data_get($metadata, 'original_name') ?: $this->processedFileName($metadata),
                'processing_state' => 'processed',
                'processing_progress' => 100,
                'processed_at' => now()->toIso8601String(),
                'optimized_size' => $processedSize,
                'temp_path' => null,
            ]);
            $messageMedia->save();

            Storage::disk('local')->delete($sourcePath);

            if($transcodedAudioPath && $transcodedAudioPath !== $sourcePath) {
                Storage::disk('local')->delete($transcodedAudioPath);
            }

            $messageData = $this->loadRealtimeRelations($this->messageData->fresh());

            event(new MessageMediaReadyEvent($messageData));
        } catch (Throwable $exception) {
            if($transcodedAudioPath && $transcodedAudioPath !== $sourcePath) {
                Storage::disk('local')->delete($transcodedAudioPath);
            }

            Log::error('Chat audio processing failed. Error: ' . $exception->getMessage(), [
                'message_id' => $this->messageData->id ?? null,
                'media_id' => $messageMedia?->id,
            ]);

            throw $exception;
        }
    }

    public function tries(): int
    {
        return 5;
    }

    public function failed(?Throwable $exception): void
    {
        $messageData = Message::query()->with('media')->find($this->messageData->id);

        if(empty($messageData) || $messageData->is_deleted || empty($messageData->media)) {
            return;
        }

        Log::error('Chat audio processing exhausted retries.', [
            'message_id' => $messageData->id,
            'media_id' => $messageData->media?->id,
            'error' => $exception?->getMessage(),
        ]);

        (new MessageGlobalDeleteAction($messageData))->execute();

        event(new MessageDeletedEvent($messageData->id, $messageData->chat_uuid));
    }

    private function loadRealtimeRelations(Message $messageData): Message
    {
        return $messageData->load([
            'reactions',
            'media',
            'participant',
            'user:id,first_name,last_name,username,avatar,verified',
            'parent.user:id,first_name,last_name,username,avatar,verified',
            'parent.participant',
            'parent.media',
            'parent.linkSnapshot',
            'linkSnapshot'
        ]);
    }

    private function processedFileName(array $metadata): string
    {
        $baseFileName = trim((string) (data_get($metadata, 'original_name') ?: data_get($metadata, 'file_name') ?: 'voice-note.mp3'));

        return Str::finish(Str::beforeLast($baseFileName, '.'), '.mp3');
    }
}
