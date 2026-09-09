<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\MediaPublication;
use App\Services\Media\Publication\PublicationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MediaPublicationController extends Controller
{
    public function __construct(private PublicationService $service) {}

    public function capabilities(Request $request)
    {
        return response()->json(['data' => [
            'enabled' => $this->service->enabled($request->user()), 'user_id' => $request->user()->id,
            'kinds' => array_values(config('media.publications.kinds')), 'upload_concurrency' => 2,
            'privacy_options' => ['all'],
        ]]);
    }

    public function index(Request $request)
    {
        return response()->json(['data' => MediaPublication::where('user_id', $request->user()->id)
            ->with('items')->latest()->limit(50)->get()->map(fn($p) => $this->present($p))]);
    }

    public function show(Request $request, string $id)
    {
        return response()->json(['data' => $this->present($this->owned($request, $id))]);
    }

    public function store(Request $request)
    {
        if(! $request->filled('client_uid')) $request->merge(['client_uid' => $request->header('Idempotency-Key')]);
        $data = $request->validate([
            'client_uid' => ['required', 'uuid'], 'kind' => ['required', Rule::in(['post', 'story', 'chat'])],
            'content' => ['nullable', 'string', 'max:8200'],
            // Do not silently accept privacy values the existing delivery queries cannot enforce.
            'privacy' => ['nullable', Rule::in(['all'])], 'selected_user_ids' => ['nullable', 'array', 'max:0'],
            'chat_id' => ['required_if:kind,chat', 'nullable', 'uuid'], 'parent_id' => ['nullable', 'integer', 'min:1'],
            'quoted_post_id' => ['nullable', 'integer', 'min:1'],
            'marks' => ['nullable', 'array:is_ai_generated,is_sensitive'],
            'marks.is_ai_generated' => ['boolean'], 'marks.is_sensitive' => ['boolean'],
            'clip_start_seconds' => ['nullable', 'numeric', 'min:0'],
            'clip_duration_seconds' => ['nullable', 'numeric', 'gt:0', 'max:'.config('story.video_clip_size', 60)],
            'items' => ['required', 'array', 'min:1', 'max:'.config('post.max_images_count', 10)],
            'items.*.client_uid' => ['required', 'uuid', 'distinct'],
            'items.*.type' => ['required', Rule::in(['image', 'video'])],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.mime' => ['required', 'string', 'max:128'],
            'items.*.size' => ['required', 'integer', 'min:1'],
            'items.*.duration_seconds' => ['nullable', 'numeric', 'gt:0'],
            'items.*.width' => ['nullable', 'integer', 'min:1'], 'items.*.height' => ['nullable', 'integer', 'min:1'],
        ]);
        $data['content'] = $data['content'] ?? '';
        $data['privacy'] = $data['privacy'] ?? 'all';
        $items = collect($data['items']);
        if(($data['kind'] !== 'post' && $items->count() !== 1) || ($items->contains('type', 'video') && $items->count() !== 1)) {
            throw ValidationException::withMessages(['items' => 'Choose one video, or the supported number of photos.']);
        }
        if(mb_strlen($data['content']) > (int) config($data['kind'] === 'chat' ? 'chat.message.validation.content.max' : $data['kind'].'.validation.content.max', 2200)) {
            throw ValidationException::withMessages(['content' => 'Caption exceeds the allowed length.']);
        }
        foreach($data['items'] as $index => $item) {
            $video = $item['type'] === 'video';
            $allowed = $video ? ['video/mp4', 'video/quicktime', 'video/webm', 'video/avi', 'video/x-msvideo', 'video/mpeg']
                : ['image/jpeg', 'image/png', 'image/webp'];
            $limit = $video ? min((int) config('media.uploads.video.max_bytes'), 1024 * (int) config($data['kind'] === 'chat' ? 'chat.validation.message.media.max' : $data['kind'].'.validation.video.max'))
                : min((int) config('media.publications.image_max_bytes', 20971520), 1024 * (int) config($data['kind'] === 'chat' ? 'chat.validation.message.media.max' : $data['kind'].'.validation.image.max'));
            if(! in_array($item['mime'], $allowed, true) || $item['size'] > $limit) {
                throw ValidationException::withMessages(["items.$index" => 'Unsupported media format or file exceeds the upload limit.']);
            }
            if($video && (empty($item['duration_seconds']) || $item['duration_seconds'] > config('media.uploads.video.max_duration_seconds'))) {
                throw ValidationException::withMessages(["items.$index.duration_seconds" => 'Video duration exceeds the upload limit or is unavailable.']);
            }
        }
        return response()->json(['data' => $this->present($this->service->create($request->user(), $data))], 201);
    }

    public function resume(Request $request, string $id, string $itemId)
    {
        return response()->json(['data' => $this->service->resume($this->owned($request, $id), $itemId)]);
    }

    public function complete(Request $request, string $id, string $itemId)
    {
        $data = $request->validate([
            'generation' => ['required', 'integer', 'min:1'], 'parts' => ['present', 'array', 'max:10000'],
            'parts.*.part_number' => ['required', 'integer', 'min:1', 'max:10000', 'distinct'],
            'parts.*.etag' => ['required', 'string', 'max:255'],
        ]);
        return response()->json(['data' => $this->present($this->service->complete($this->owned($request, $id), $itemId, $data['generation'], $data['parts']))]);
    }

    public function retry(Request $request, string $id)
    {
        return response()->json(['data' => $this->present($this->service->retry($this->owned($request, $id)))]);
    }

    public function destroy(Request $request, string $id)
    {
        return response()->json(['data' => $this->present($this->service->cancel($this->owned($request, $id)))]);
    }

    private function owned(Request $request, string $id): MediaPublication
    {
        return MediaPublication::where('user_id', $request->user()->id)->whereKey($id)->with('items')->firstOrFail();
    }

    private function present(MediaPublication $publication): array
    {
        return [
            'id' => $publication->id, 'client_uid' => $publication->client_uid, 'kind' => $publication->kind,
            'status' => $publication->status, 'content' => $publication->payload['content'] ?? '',
            'chat_id' => $publication->payload['chat_id'] ?? null, 'error' => $publication->error,
            'result' => $publication->result, 'created_at' => $publication->created_at->toIso8601String(),
            'items' => $publication->items->map(fn($item) => [
                'id' => $item->id, 'client_uid' => $item->client_uid, 'type' => $item->type,
                'size' => $item->size, 'status' => $item->status, 'progress' => $item->progress,
            ])->values()->all(),
        ];
    }
}
