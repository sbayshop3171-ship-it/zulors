<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\MediaPublication;
use App\Services\Media\Publication\PublicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MediaPublicationController extends Controller
{
    public function __construct(private PublicationService $service) {}

    public function capabilities(Request $request)
    {
        return response()->json(['data' => [
            'enabled' => $this->service->enabled($request->user()), 'user_id' => $request->user()->id,
            'kinds' => array_values(config('media.publications.kinds')), 'upload_concurrency' => $this->service->uploadConcurrency(),
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
        $queuedAt = hrtime(true);
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
            'items.*.type' => ['required', Rule::in(['image', 'video', 'audio', 'document'])],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.mime' => ['required', 'string', 'max:128'],
            'items.*.size' => ['required', 'integer', 'min:1'],
            'items.*.duration_seconds' => ['nullable', 'numeric', 'gt:0'],
            'items.*.width' => ['nullable', 'integer', 'min:1'], 'items.*.height' => ['nullable', 'integer', 'min:1'],
        ]);
        $data['content'] = $data['content'] ?? '';
        $data['privacy'] = $data['privacy'] ?? 'all';
        $items = collect($data['items']);
        if(($data['kind'] !== 'post' && $items->count() !== 1)
            || ($items->contains(fn ($item) => $item['type'] !== 'image') && $items->count() !== 1)
            || ($data['kind'] === 'story' && $items->contains(fn ($item) => ! in_array($item['type'], ['image', 'video'], true)))) {
            throw ValidationException::withMessages(['items' => 'Choose one media file, or up to 10 photos for a post.']);
        }
        if(mb_strlen($data['content']) > (int) config($data['kind'] === 'chat' ? 'chat.message.validation.content.max' : $data['kind'].'.validation.content.max', 2200)) {
            throw ValidationException::withMessages(['content' => 'Caption exceeds the allowed length.']);
        }
        foreach($data['items'] as $index => $item) {
            $video = $item['type'] === 'video';
            $allowed = match ($item['type']) {
                'video' => ['video/mp4', 'video/quicktime', 'video/webm', 'video/avi', 'video/x-msvideo', 'video/mpeg', 'video/x-m4v', 'video/3gpp', 'video/3gpp2', 'video/x-matroska'],
                'image' => ['image/jpeg', 'image/png', 'image/webp'],
                'audio' => $data['kind'] === 'chat'
                    ? ['audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/mp3', 'audio/ogg', 'audio/webm', 'audio/wav', 'audio/x-m4a', 'audio/x-wav', 'video/webm']
                    : ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/webm', 'video/webm'],
                'document' => $data['kind'] === 'chat'
                    ? ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain', 'application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed', 'application/vnd.rar']
                    : ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                default => [],
            };
            $limit = match ($item['type']) {
                'video' => (int) config('media.uploads.video.max_bytes'),
                'image' => min((int) config('media.publications.image_max_bytes', 20971520), 1024 * (int) config($data['kind'] === 'chat' ? 'chat.validation.message.media.max' : $data['kind'].'.validation.image.max')),
                'audio' => 1024 * (int) config($data['kind'] === 'chat' ? 'chat.validation.message.media.max' : 'post.validation.audio.max', 24576),
                'document' => 1024 * (int) config($data['kind'] === 'chat' ? 'chat.validation.message.media.max' : 'post.validation.document.max', 24576),
            };
            if(! in_array($item['mime'], $allowed, true) || ! $this->mimeMatchesExtension($item['type'], $item['mime'], $item['name']) || $item['size'] > $limit) {
                throw ValidationException::withMessages(["items.$index" => 'Unsupported media format or file exceeds the upload limit.']);
            }
            // Browser/device metadata can be missing for valid camera formats. The
            // media processor probes the uploaded bytes and enforces the real limit.
            if($video && ! empty($item['duration_seconds']) && $item['duration_seconds'] > config('media.uploads.video.max_duration_seconds')) {
                throw ValidationException::withMessages(["items.$index.duration_seconds" => 'Video duration exceeds the upload limit.']);
            }
            if($item['type'] === 'audio' && ! empty($item['duration_seconds']) && $item['duration_seconds'] > 3600) {
                throw ValidationException::withMessages(["items.$index.duration_seconds" => 'Audio duration exceeds the upload limit.']);
            }
        }
        $publication = $this->service->create($request->user(), $data);

        if($items->contains('type', 'video')) {
            Log::info('media_publication.video_queued', [
                'kind' => $data['kind'],
                'items' => $items->count(),
                'bytes' => (int) $items->sum('size'),
                'queue_ack_ms' => max(0, (int) ((hrtime(true) - $queuedAt) / 1_000_000)),
            ]);
        }

        return response()->json(['data' => $this->present($publication)], 201);
    }

    public function resume(Request $request, string $id, string $itemId)
    {
        $publication = $this->owned($request, $id);
        $item = $publication->items->firstWhere('id', $itemId);
        try {
            $result = $this->service->resume($publication, $itemId);
        }
        catch (\Throwable $error) {
            if($item?->type === 'video') {
                Log::warning('media_publication.video_upload_resume_failed', [
                    'kind' => $publication->kind,
                    'bytes' => (int) $item->size,
                    'error_type' => $error::class,
                ]);
            }

            throw $error;
        }

        if($item?->type === 'video') {
            Log::info('media_publication.video_upload_resumed', [
                'kind' => $publication->kind,
                'bytes' => (int) $item->size,
                'generation' => (int) $result['generation'],
                'completed_parts' => count($result['completed_parts'] ?? []),
            ]);
        }

        return response()->json(['data' => $result]);
    }

    public function complete(Request $request, string $id, string $itemId)
    {
        $data = $request->validate([
            'generation' => ['required', 'integer', 'min:1'], 'parts' => ['present', 'array', 'max:10000'],
            'parts.*.part_number' => ['required', 'integer', 'min:1', 'max:10000', 'distinct'],
            'parts.*.etag' => ['required', 'string', 'max:255'],
        ]);
        $publication = $this->owned($request, $id);
        $item = $publication->items->firstWhere('id', $itemId);
        $startedAt = data_get($item?->upload, 'created_at');
        try {
            $completed = $this->service->complete($publication, $itemId, $data['generation'], $data['parts']);
        }
        catch (\Throwable $error) {
            if($item?->type === 'video') {
                Log::warning('media_publication.video_upload_completion_failed', [
                    'kind' => $publication->kind,
                    'bytes' => (int) $item->size,
                    'parts' => count($data['parts']),
                    'error_type' => $error::class,
                ]);
            }

            throw $error;
        }

        if($item?->type === 'video') {
            Log::info('media_publication.video_upload_completed', [
                'kind' => $publication->kind,
                'bytes' => (int) $item->size,
                'parts' => count($data['parts']),
                'upload_session_seconds' => $startedAt ? max(0, (int) now()->diffInSeconds(\Illuminate\Support\Carbon::parse($startedAt))) : null,
            ]);
        }

        return response()->json(['data' => $this->present($completed)]);
    }

    public function retry(Request $request, string $id)
    {
        $publication = $this->owned($request, $id);
        $videoItems = $publication->items->where('type', 'video');
        $retried = $this->service->retry($publication);

        if($videoItems->isNotEmpty()) {
            Log::info('media_publication.video_retry_requested', [
                'kind' => $publication->kind,
                'items' => $videoItems->count(),
                'bytes' => (int) $videoItems->sum('size'),
            ]);
        }

        return response()->json(['data' => $this->present($retried)]);
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

    private function mimeMatchesExtension(string $type, string $mime, string $name): bool
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = match ($type) {
            'image' => ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp']],
            'video' => [
                'video/mp4' => ['mp4'], 'video/quicktime' => ['mov'], 'video/webm' => ['webm'],
                'video/avi' => ['avi'], 'video/x-msvideo' => ['avi'], 'video/mpeg' => ['mpeg', 'mpg'],
                'video/x-m4v' => ['m4v'], 'video/3gpp' => ['3gp'], 'video/3gpp2' => ['3g2'], 'video/x-matroska' => ['mkv'],
            ],
            'audio' => [
                'audio/aac' => ['aac'], 'audio/mp4' => ['m4a', 'mp4'], 'audio/mpeg' => ['mp3', 'mpeg'],
                'audio/mp3' => ['mp3'], 'audio/ogg' => ['ogg'], 'audio/webm' => ['webm'],
                'audio/wav' => ['wav'], 'audio/x-m4a' => ['m4a'], 'audio/x-wav' => ['wav'], 'video/webm' => ['webm'],
            ],
            'document' => [
                'application/pdf' => ['pdf'], 'application/msword' => ['doc'],
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
                'application/vnd.ms-powerpoint' => ['ppt'],
                'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
                'application/vnd.ms-excel' => ['xls'],
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
                'text/plain' => ['txt'], 'application/zip' => ['zip'], 'application/x-zip-compressed' => ['zip'],
                'application/x-rar-compressed' => ['rar'], 'application/vnd.rar' => ['rar'],
            ],
            default => [],
        };

        return in_array($extension, $allowed[$mime] ?? [], true);
    }
}
