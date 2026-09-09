<?php

namespace App\Services\Media\Publication;

use App\Jobs\Media\CleanupPublication;
use App\Jobs\Media\ProcessPublicationItem;
use App\Models\Chat;
use App\Models\MediaPublication;
use App\Models\MediaPublicationItem;
use App\Models\User;
use App\Services\Media\Cloudflare\R2DirectUploadService;
use App\Services\Safety\SafetyService;
use Aws\S3\Exception\S3Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PublicationService
{
    public function __construct(private R2DirectUploadService $r2) {}

    public function enabled(User $user): bool
    {
        $allowed = array_map('strval', config('media.publications.allowed_user_ids', []));
        return (bool) config('media.publications.enabled')
            && (empty($allowed) || in_array((string) $user->id, $allowed, true))
            && $this->r2->isConfigured();
    }

    public function assertMayPublish(User $user, array $payload): void
    {
        abort_unless($user->status === \App\Enums\User\UserStatus::ACTIVE, 403, 'Account is not active.');
        if(app(SafetyService::class)->isFrozen($user)) {
            throw new HttpException(429, 'Publishing is temporarily paused for this account.', null, ['Retry-After' => '60']);
        }
        if($payload['kind'] === 'chat') {
            $chat = Chat::where('chat_id', $payload['chat_id'])->whereHas('participants', fn($q) => $q->where('user_id', $user->id))->first();
            abort_unless($chat, 403, 'Chat membership is required.');
            if(! empty($payload['parent_id'])) {
                abort_unless($chat->messages()->whereKey($payload['parent_id'])->where('is_deleted', false)->exists(), 422, 'Reply target is unavailable.');
            }
        }
    }

    public function create(User $user, array $payload): MediaPublication
    {
        $hash = hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR));
        // Admission is short and performs no R2 requests. This shared lock also protects the global quota.
        return Cache::lock('media-publications:admission', 15)->block(5, function () use ($user, $payload, $hash) {
            return DB::transaction(function () use ($user, $payload, $hash) {
                $existing = MediaPublication::where('user_id', $user->id)->where('client_uid', $payload['client_uid'])->first();
                if($existing) {
                    abort_unless(hash_equals($existing->request_hash, $hash), 409, 'This publication identifier already belongs to different content.');
                    return $existing->load('items');
                }
                abort_unless($this->enabled($user), 503, 'Background publishing is unavailable.');
                abort_unless(in_array($payload['kind'], config('media.publications.kinds'), true), 503, 'This publication type is not enabled.');
                $this->assertMayPublish($user, $payload);
                if($payload['kind'] === 'story') {
                    abort_if(($user->story?->activeFramesCount() ?? 0) >= (int) config('story.max_frames_per_story', 10), 422, 'Story frame limit reached.');
                }
                $hasVideo = collect($payload['items'])->contains('type', 'video');
                $this->assertCapacity($user, $hasVideo);
                $profile = MediaEncodingProfile::defaults();
                $publication = MediaPublication::create([
                    'id' => (string) Str::uuid(), 'user_id' => $user->id, 'client_uid' => $payload['client_uid'],
                    'request_hash' => $hash, 'kind' => $payload['kind'], 'status' => 'uploading',
                    'has_video' => $hasVideo, 'payload' => $payload, 'profile' => $profile,
                    'expires_at' => now()->addHours(72),
                ]);
                foreach($payload['items'] as $position => $item) {
                    $publication->items()->create([
                        'id' => (string) Str::uuid(), 'client_uid' => $item['client_uid'], 'position' => $position,
                        'name' => $item['name'], 'mime' => $item['mime'], 'type' => $item['type'],
                        'size' => $item['size'], 'metadata' => $item,
                    ]);
                }
                return $publication->load('items');
            });
        });
    }

    private function canonical(array $value): array
    {
        if(! array_is_list($value)) ksort($value);
        foreach($value as &$entry) if(is_array($entry)) $entry = $this->canonical($entry);
        return $value;
    }

    private function assertCapacity(User $user, bool $video, ?string $excludeId = null): void
    {
        $pending = MediaPublication::whereNotIn('status', ['published', 'cancelled'])->where('expires_at', '>', now());
        if($excludeId) $pending->whereKeyNot($excludeId);
        $full = (clone $pending)->where('user_id', $user->id)->count() >= max(1, config('media.publications.per_user_limit'));
        if($video) {
            $videos = (clone $pending)->where('has_video', true);
            $full = $full || (clone $videos)->count() >= max(1, config('media.publications.video_limit'));
            $oldest = (clone $videos)->whereHas('items', fn($q) => $q->whereIn('status', ['uploaded', 'processing'])
                ->where('queued_at', '<', now()->subSeconds(config('media.publications.max_queue_age_seconds'))))->exists();
            $full = $full || $oldest;
        }
        if($full) throw new HttpException(429, 'Media processing is busy. Your publication can be retried shortly.', null, ['Retry-After' => '60']);
    }

    public function resume(MediaPublication $publication, string $itemId): array
    {
        return $this->locked($publication->id, function ($publication) use ($itemId) {
            $item = $publication->items()->whereKey($itemId)->firstOrFail();
            if(in_array($item->status, ['uploaded', 'processing', 'processed'], true)) {
                return ['status' => $item->status, 'generation' => $item->generation, 'upload' => null, 'completed_parts' => []];
            }
            $this->assertOpen($publication);
            $this->assertMayPublish($publication->user, $publication->payload);
            $upload = $item->upload;
            $parts = [];
            if($upload && Storage::disk($upload['disk'])->exists($upload['path'])) {
                return ['status' => 'uploaded', 'generation' => $item->generation, 'upload' => null, 'completed_parts' => []];
            }
            if($upload && ($upload['upload_type'] ?? '') === 'multipart') {
                try {
                    $parts = $this->r2->listMultipartUploadParts($upload['path'], $upload['upload_id'], $upload['disk']);
                } catch(S3Exception $e) {
                    if($e->getAwsErrorCode() !== 'NoSuchUpload') throw $e;
                    $upload = null;
                }
            }
            if(! $upload) {
                $upload = $this->r2->createVideoUpload([
                    'mime' => $item->mime, 'size' => $item->size,
                    'extension' => pathinfo($item->name, PATHINFO_EXTENSION) ?: ($item->type === 'video' ? 'mp4' : 'jpg'),
                ]);
                $upload['content_type'] = $item->mime;
                $upload['created_at'] = now()->toIso8601String();
                $item->generation++;
            } else {
                $upload = $this->r2->refreshPublicationUpload($upload);
            }
            $upload['upload_concurrency'] = 2;
            $upload['raw_fallback_max_bytes'] = $upload['part_fallback_max_bytes'] = 0;
            $item->forceFill(['upload' => $upload, 'status' => 'uploading'])->save();
            return [
                'status' => $item->status, 'generation' => $item->generation, 'upload' => $upload,
                'completed_parts' => array_map(fn($part) => ['part_number' => $part['PartNumber'], 'etag' => $part['ETag']], $parts),
            ];
        });
    }

    public function complete(MediaPublication $publication, string $itemId, int $generation, array $parts): MediaPublication
    {
        return $this->locked($publication->id, function ($publication) use ($itemId, $generation, $parts) {
            $item = $publication->items()->whereKey($itemId)->firstOrFail();
            abort_unless($item->generation === $generation && $generation > 0, 409, 'Upload generation has changed. Resume the current upload.');
            abort_if($publication->status === 'cancelled', 409, 'Publication was cancelled.');
            if(in_array($item->status, ['uploaded', 'processing', 'processed'], true)) return $publication->load('items');
            $this->assertOpen($publication);
            $upload = $item->upload;
            abort_unless($upload, 422, 'No upload session exists.');
            if(($upload['upload_type'] ?? '') === 'multipart') {
                $this->r2->completeMultipartUpload($upload['path'], $upload['upload_id'], $parts, $upload['disk'], count($upload['parts']));
            }
            $disk = Storage::disk($upload['disk']);
            abort_unless($disk->exists($upload['path']) && (int) $disk->size($upload['path']) === $item->size, 422, 'Uploaded bytes do not match the selected file.');
            $item->update(['status' => 'uploaded', 'progress' => 100, 'dispatched_at' => now(), 'queued_at' => now()]);
            $publication->update(['status' => 'processing', 'error' => null]);
            ProcessPublicationItem::dispatch($item->id, $generation)->afterCommit();
            return $publication->load('items');
        });
    }

    public function retry(MediaPublication $publication): MediaPublication
    {
        return Cache::lock('media-publications:admission', 15)->block(5, function () use ($publication) {
            return $this->locked($publication->id, function ($publication) {
                abort_if($publication->terminal(), 409, 'This publication cannot be retried.');
                $this->assertMayPublish($publication->user, $publication->payload);
                if($publication->status === 'failed' || $publication->expires_at->isPast()) {
                    $this->assertCapacity($publication->user, $publication->has_video, $publication->id);
                }
                foreach($publication->items as $item) {
                    if($item->status === 'processed') continue;
                    if($item->status === 'processing') continue;
                    $exists = $item->upload && Storage::disk($item->upload['disk'])->exists($item->upload['path']);
                    $item->update(['status' => $exists ? 'uploaded' : 'pending', 'dispatched_at' => $exists ? now() : null,
                        'queued_at' => $exists ? ($item->queued_at ?? now()) : null]);
                    if($exists) ProcessPublicationItem::dispatch($item->id, $item->generation)->afterCommit();
                }
                $publication->update(['status' => 'uploading', 'error' => null, 'expires_at' => now()->addHours(72)]);
                // All items may already be processed if only final publication failed.
                \App\Jobs\Media\FinalizePublication::dispatch($publication->id)->afterCommit();
                return $publication->load('items');
            });
        });
    }

    public function cancel(MediaPublication $publication): MediaPublication
    {
        return $this->locked($publication->id, function ($publication) {
            abort_if($publication->status === 'published', 409, 'Delete the published item through its normal delete action.');
            $publication->update(['status' => 'cancelled', 'error' => null]);
            CleanupPublication::dispatch($publication->id)->afterCommit();
            return $publication->load('items');
        });
    }

    public function locked(string $id, callable $callback): mixed
    {
        return DB::transaction(fn() => $callback(MediaPublication::whereKey($id)->lockForUpdate()->firstOrFail()), 3);
    }

    private function assertOpen(MediaPublication $publication): void
    {
        abort_if($publication->terminal(), 409, 'Publication is already finished.');
        abort_if($publication->expires_at->isPast(), 410, 'This upload has expired. Retry the publication.');
    }
}
