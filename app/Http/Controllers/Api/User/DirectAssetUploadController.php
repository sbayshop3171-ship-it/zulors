<?php

namespace App\Http\Controllers\Api\User;

use App\Enums\Chat\ChatType;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Services\Filesystem\Delete\FileDeleteService;
use App\Services\Media\Cloudflare\R2DirectUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DirectAssetUploadController extends Controller
{
    private const MAX_IMAGE_BYTES = 12 * 1024 * 1024;

    public function create(Request $request, R2DirectUploadService $r2)
    {
        abort_unless($r2->isConfigured(), 503, 'Direct R2 uploads are temporarily unavailable.');
        $data = $request->validate([
            'operation' => ['required', Rule::in(['profile_avatar', 'profile_cover', 'group_avatar'])],
            'client_uid' => ['required', 'uuid'],
            'chat_id' => ['required_if:operation,group_avatar', 'nullable', 'uuid'],
            'name' => ['nullable', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.self::MAX_IMAGE_BYTES],
            'mime' => ['required', 'string', Rule::in(['image/jpeg', 'image/png', 'image/webp'])],
            'extension' => ['required', 'string', Rule::in(['jpg', 'jpeg', 'png', 'webp'])],
        ]);
        $chat = $data['operation'] === 'group_avatar' ? $this->authorizedGroup($request, $data['chat_id']) : null;
        $expectedExtension = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp']][$data['mime']];
        if (! in_array(strtolower($data['extension']), $expectedExtension, true)) {
            throw ValidationException::withMessages(['extension' => 'The image extension does not match its content type.']);
        }

        $requestHash = hash('sha256', json_encode([
            'operation' => $data['operation'], 'chat_id' => $chat?->chat_id,
            'size' => (int) $data['size'], 'mime' => $data['mime'],
            'extension' => strtolower($data['extension']),
        ], JSON_THROW_ON_ERROR));
        $idempotencyKey = $this->createKey($request, $data['operation'], $data['client_uid']);
        $session = Cache::lock($idempotencyKey.':lock', 10)->block(5, function () use ($request, $data, $chat, $r2, $requestHash, $idempotencyKey) {
            $existing = Cache::get($idempotencyKey);
            if (is_array($existing)) {
                abort_unless(hash_equals((string) ($existing['request_hash'] ?? ''), $requestHash), 409, 'This upload identifier already belongs to different content.');
                $existing = Cache::get($this->sessionKey($request, (string) $existing['path'], $data['operation'])) ?: $existing;
                if (! empty($existing['completed'])) return $existing;
                $existing['upload'] = $r2->refreshPublicationUpload($existing['upload']);
                Cache::put($this->sessionKey($request, $existing['path'], $data['operation']), $existing, now()->addHours(24));
                Cache::put($idempotencyKey, $existing, now()->addHours(24));
                return $existing;
            }

            $upload = $r2->createScopedObjectUpload($data, $data['operation']);
            $session = [
                'user_id' => (string) $request->user()->id,
                'operation' => $data['operation'],
                'client_uid' => $data['client_uid'],
                'request_hash' => $requestHash,
                'chat_id' => $chat?->chat_id,
                'path' => $upload['path'],
                'disk' => $upload['disk'],
                'size' => (int) $data['size'],
                'mime' => $data['mime'],
                'extension' => strtolower($data['extension']),
                'expires_at' => $upload['expires_at'],
                'upload' => $upload,
            ];
            Cache::put($this->sessionKey($request, $upload['path'], $data['operation']), $session, now()->addHours(24));
            Cache::put($idempotencyKey, $session, now()->addHours(24));

            return $session;
        });

        return response()->json(['data' => $this->presentUpload($session)]);
    }

    public function complete(Request $request, R2DirectUploadService $r2)
    {
        $data = $request->validate([
            'operation' => ['required', Rule::in(['profile_avatar', 'profile_cover', 'group_avatar'])],
            'uid' => ['required', 'string', 'max:512'],
            'parts' => ['sometimes', 'array', 'max:10000'],
            'parts.*.part_number' => ['required_with:parts', 'integer', 'min:1', 'max:10000', 'distinct'],
            'parts.*.etag' => ['required_with:parts', 'string', 'max:255'],
        ]);
        $key = $this->sessionKey($request, $data['uid'], $data['operation']);
        return Cache::lock($key.':complete-lock', 180)->block(10, function () use ($request, $data, $r2, $key) {
            $session = Cache::get($key);
            abort_unless(is_array($session)
                && hash_equals((string) $request->user()->id, (string) ($session['user_id'] ?? ''))
                && hash_equals($data['operation'], (string) ($session['operation'] ?? ''))
                && hash_equals((string) $data['uid'], (string) ($session['path'] ?? '')), 404, 'Upload session was not found.');

            if (! empty($session['completed'])) {
                return response()->json(['data' => $session['completed']]);
            }

            if ($data['operation'] === 'group_avatar') {
                $this->authorizedGroup($request, (string) $session['chat_id']);
            }
            $upload = $session['upload'] ?? [];
            if (($upload['upload_type'] ?? 'raw') === 'multipart') {
                abort_unless(array_key_exists('parts', $data), 422, 'Multipart upload parts are required.');
                $r2->completeMultipartUpload(
                    $session['path'], (string) ($upload['upload_id'] ?? ''), $data['parts'],
                    $session['disk'], count($upload['parts'] ?? [])
                );
            }
            $actual = $r2->verifyUploadedObject($session['path'], $session['disk'], $session['size'], self::MAX_IMAGE_BYTES);
            if ($actual['mime'] !== strtolower($session['mime'])) {
                throw ValidationException::withMessages(['file' => 'Uploaded image type does not match the selected image.']);
            }

            $prefix = match ($data['operation']) {
                'profile_avatar' => 'uploads/users/avatars',
                'profile_cover' => 'uploads/users/covers',
                'group_avatar' => 'uploads/groups/avatars',
            };
            $published = $r2->publishUploadedObject(
                $session['path'], $prefix, $session['extension'], $session['mime'], $session['disk']
            );
            $result = $this->applyAsset($request, $session, $published);
            $session['completed'] = $result;
            Cache::put($key, $session, now()->addHours(24));
            Cache::put($this->createKey($request, $data['operation'], (string) $session['client_uid']), $session, now()->addHours(24));

            return response()->json(['data' => $result]);
        });
    }

    private function applyAsset(Request $request, array $session, array $published): array
    {
        $disk = $published['disk'];
        $path = $published['path'];
        $fileDelete = app(FileDeleteService::class);

        if ($session['operation'] === 'group_avatar') {
            $chat = $this->authorizedGroup($request, (string) $session['chat_id']);
            $group = $chat->group;
            $oldPath = $group->avatar;
            $oldDisk = $group->avatar_disk ?: static_storage_disk();
            $oldWasDefault = $group->hasDefaultAvatar();
            $group->update(['avatar' => $path, 'avatar_disk' => $disk]);
            if (filled($oldPath) && ! $oldWasDefault && ($oldPath !== $path || $oldDisk !== $disk)) {
                $fileDelete->setStorageDisk($oldDisk)->deleteFile($oldPath);
            }

            return ['avatar_url' => storage_url($path, $disk)];
        }

        $user = $request->user();
        $field = $session['operation'] === 'profile_avatar' ? 'avatar' : 'cover';
        $diskField = $field.'_disk';
        $oldPath = $user->{$field};
        $oldDisk = $user->{$diskField} ?: static_storage_disk();
        $user->update([$field => $path, $diskField => $disk]);
        $isDefault = $field === 'avatar' ? $oldPath === config('user.avatar') : $oldPath === config('user.cover');
        if (filled($oldPath) && ! $isDefault && ($oldPath !== $path || $oldDisk !== $disk)) {
            $fileDelete->setStorageDisk($oldDisk)->deleteFile($oldPath);
        }

        return [$field.'_url' => storage_url($path, $disk)];
    }

    private function authorizedGroup(Request $request, string $chatId): Chat
    {
        $chat = Chat::query()->where('chat_id', $chatId)->where('type', ChatType::GROUP)
            ->whereHas('participants', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('group')->first();
        abort_unless($chat?->group, 404, 'Group was not found.');
        return $chat;
    }

    private function sessionKey(Request $request, string $uid, string $operation): string
    {
        return 'r2-direct-asset:'.hash('sha256', (string) $request->user()->id.'|'.$operation.'|'.$uid);
    }

    private function createKey(Request $request, string $operation, string $clientUid): string
    {
        return 'r2-direct-asset-create:'.hash('sha256', (string) $request->user()->id.'|'.$operation.'|'.$clientUid);
    }

    private function presentUpload(array $session): array
    {
        if (! empty($session['completed'])) {
            return ['uid' => $session['path'], 'completed' => true, 'result' => $session['completed']];
        }

        $upload = $session['upload'] ?? [];
        return [
            'uid' => $session['path'],
            'upload_url' => $upload['upload_url'] ?? null,
            'upload_method' => $upload['upload_method'] ?? 'PUT',
            'upload_headers' => $upload['upload_headers'] ?? [],
            'upload_type' => $upload['upload_type'] ?? 'raw',
            'upload_concurrency' => $upload['upload_concurrency'] ?? 1,
            'parts' => $upload['parts'] ?? [],
            'expires_at' => $upload['expires_at'] ?? $session['expires_at'] ?? null,
        ];
    }
}
