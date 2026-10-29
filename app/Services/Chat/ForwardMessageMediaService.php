<?php

namespace App\Services\Chat;

use App\Models\Message;
use App\Models\Media;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ForwardMessageMediaService
{
    public function attach(Message $sourceMessage, Message $forwardedMessage): ?Media
    {
        $sourceMedia = $sourceMessage->media;

        if (! $sourceMedia) {
            return null;
        }

        $sourcePath = (string) $sourceMedia->source_path;
        $thumbnailPath = (string) $sourceMedia->thumbnail_path;
        $sourceDisk = (string) $sourceMedia->disk;
        $thumbnailDisk = (string) $sourceMedia->thumbnail_disk;
        $copiedPaths = [];
        $isSharedAsset = false;

        if ($sourceDisk !== 'external' && filled($sourcePath)) {
            try {
                $sourcePath = $this->copyAsset($sourceDisk, $sourcePath);
                $copiedPaths[] = [$sourceDisk, $sourcePath];

                if (filled($thumbnailPath)) {
                    $thumbnailPath = $this->copyAsset($thumbnailDisk, $thumbnailPath);
                    $copiedPaths[] = [$thumbnailDisk, $thumbnailPath];
                }
            } catch (Throwable $exception) {
                $this->deleteCopies($copiedPaths);
                $sourcePath = (string) $sourceMedia->source_path;
                $thumbnailPath = (string) $sourceMedia->thumbnail_path;
                $isSharedAsset = true;
            }
        } else {
            $isSharedAsset = true;
        }

        $metadata = (array) $sourceMedia->metadata;
        unset($metadata['forwarded_shared_asset']);

        if ($isSharedAsset) {
            $metadata['forwarded_shared_asset'] = true;
        }

        return $forwardedMessage->media()->create(array_merge(
            Arr::only($sourceMedia->getAttributes(), [
                'lqip_base64', 'type', 'status', 'extension', 'visibility', 'mime',
                'size', 'thumbnail_size', 'order',
            ]),
            [
                'source_path' => $sourcePath,
                'thumbnail_path' => $thumbnailPath,
                'disk' => $sourceDisk,
                'thumbnail_disk' => $thumbnailDisk,
                'metadata' => $metadata,
            ]
        ));
    }

    private function copyAsset(string $diskName, string $path): string
    {
        $disk = Storage::disk($diskName);
        $directory = trim(dirname($path), '.\/');
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $filename = Str::uuid() . ($extension !== '' ? ".{$extension}" : '');
        $destination = ($directory !== '' ? $directory . '/' : '') . 'forwarded/' . $filename;

        if (! $disk->copy($path, $destination)) {
            throw new \RuntimeException('Could not copy forwarded message media.');
        }

        return $destination;
    }

    private function deleteCopies(array $paths): void
    {
        foreach ($paths as [$diskName, $path]) {
            try {
                Storage::disk($diskName)->delete($path);
            } catch (Throwable) {
                // Preserve the original media if temporary copy cleanup fails.
            }
        }
    }
}
