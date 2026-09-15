<?php

namespace App\Http\Controllers\Api\User\Story;

use Exception;
use App\Models\Media;
use App\Models\Post;
use App\Models\StoryFrame;
use App\Models\StoryMusicTrack;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Jobs\User\Story\ExtractOriginalAudioFromMedia;
use App\Support\StoryMusic\OriginalAudioEligibility;
use App\Traits\Http\Api\SupportsApiResponses;

class StoryMusicController extends Controller
{
    use SupportsApiResponses;

    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'tab' => ['nullable', 'string', 'max:40'],
            'mood' => ['nullable', 'string', 'max:60'],
            'genre' => ['nullable', 'string', 'max:60'],
            'sort' => ['nullable', 'string', 'in:default,trending,newest,oldest,title'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . config('story_music.max_per_page')],
        ]);

        $tracksQuery = StoryMusicTrack::query()
            ->active()
            ->when($request->filled('tab'), function ($query) use ($request) {
                $query->where('collection', $this->normalizeCollection((string) $request->input('tab')));
            })
            ->when($request->filled('mood'), function ($query) use ($request) {
                $query->where('mood', (string) $request->input('mood'));
            })
            ->when($request->filled('genre'), function ($query) use ($request) {
                $query->where('genre', (string) $request->input('genre'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . addcslashes((string) $request->input('search'), '\\%_') . '%';

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('title', 'like', $search)
                        ->orWhere('artist', 'like', $search)
                        ->orWhere('mood', 'like', $search)
                        ->orWhere('genre', 'like', $search);
                });
            });

        $this->applySort($tracksQuery, (string) $request->input('sort', 'default'));

        $tracks = $tracksQuery->paginate($request->integer('per_page', 20));

        return $this->responseSuccess([
            'data' => $tracks->through(fn (StoryMusicTrack $track) => $this->trackPayload($track)),
        ]);
    }

    public function show(StoryMusicTrack $track)
    {
        if(! $track->isAvailable()) {
            return $this->responseResourceNotFoundError('StoryMusicTrack', $track->id);
        }

        return $this->responseSuccess([
            'data' => $this->trackPayload($track, true),
        ]);
    }

    public function playUrl(StoryMusicTrack $track)
    {
        if(! $track->isAvailable()) {
            return $this->responseResourceNotFoundError('StoryMusicTrack', $track->id);
        }

        $track->increment('usage_count');

        return $this->responseSuccess([
            'data' => [
                'id' => $track->id,
                'play_url' => $this->temporaryFileUrl($track->disk, $track->audio_path),
                'expires_in_minutes' => config('story_music.signed_url_minutes'),
            ],
        ]);
    }

    public function publishOriginalAudio(Request $request, Media $media)
    {
        $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'mood' => ['nullable', 'string', 'max:60'],
            'genre' => ['nullable', 'string', 'max:60'],
            'allow_reuse' => ['nullable', 'boolean'],
            'story_music_category' => [OriginalAudioEligibility::categoryValidationRule()],
            'original_audio_category' => [OriginalAudioEligibility::categoryValidationRule()],
            'upload_category' => [OriginalAudioEligibility::categoryValidationRule()],
            'content_category' => [OriginalAudioEligibility::categoryValidationRule()],
        ]);

        if(! (bool) config('story_music.original_audio.enabled', true)) {
            return $this->responseValidationError([
                'message' => 'Original audio extraction is disabled.',
            ]);
        }

        if(! $this->userOwnsMedia($request, $media)) {
            return $this->responseError([
                'message' => 'You can only publish original audio from your own videos.',
            ], 403);
        }

        if(! $media->type?->isVideo()) {
            return $this->responseValidationError([
                'message' => 'Only video media can be converted to original audio.',
            ]);
        }

        $metadata = array_merge($media->metadata ?? [], OriginalAudioEligibility::storyMusicMetadataFromRequest($request));
        $category = OriginalAudioEligibility::categoryFor($media) ?: OriginalAudioEligibility::normalizeCategory((string) data_get($metadata, 'story_music.category')) ?: 'story_music_source';
        $metadata['story_music'] = array_merge((array) data_get($metadata, 'story_music', []), [
            'allow_reuse' => (bool) $request->boolean('allow_reuse', true),
            'category' => $category,
            'auto_extract' => true,
            'title' => (string) ($request->input('title') ?: data_get($metadata, 'story_music.title') ?: 'Original audio'),
            'mood' => $request->input('mood'),
            'genre' => $request->input('genre'),
            'consent_recorded_at' => now()->toIso8601String(),
            'consent_user_id' => $request->user()->id,
            'original_audio_status' => 'queued',
        ]);

        $media->metadata = $metadata;
        $media->save();

        if($media->status?->isProcessed()) {
            ExtractOriginalAudioFromMedia::dispatch($media->id, false, true)
                ->onQueue(config('media.queues.audio'));
        }

        return $this->responseSuccess([
            'data' => [
                'media_id' => $media->id,
                'status' => $media->status?->isProcessed() ? 'queued' : 'waiting_for_video_processing',
            ],
        ]);
    }

    private function trackPayload(StoryMusicTrack $track, bool $includePlayUrl = false): array
    {
        $payload = [
            'id' => $track->id,
            'slug' => $track->slug,
            'title' => $track->title,
            'artist' => $track->artist,
            'source' => $track->source,
            'source_url' => $track->source_url,
            'license_url' => $track->license_url,
            'license_type' => $track->license_type,
            'duration_seconds' => $track->duration_seconds,
            'expires_at' => $track->expires_at?->toIso8601String(),
            'mood' => $track->mood,
            'genre' => $track->genre,
            'collection' => $track->collection,
            'tags' => $track->tags ?? [],
            'cover_url' => $track->cover_path ? $this->temporaryFileUrl($track->disk, $track->cover_path) : null,
            'play_url_endpoint' => route('api.story.music.play-url', ['track' => $track->id], false),
            'origin' => [
                'user_id' => $track->user_id,
                'media_id' => $track->source_media_id,
                'media_type' => $track->source_media_type,
            ],
        ];

        if($includePlayUrl) {
            $payload['play_url'] = $this->temporaryFileUrl($track->disk, $track->audio_path);
            $payload['expires_in_minutes'] = config('story_music.signed_url_minutes');
        }

        return $payload;
    }

    private function temporaryFileUrl(string $disk, string $path): ?string
    {
        try {
            return Storage::disk($disk)->temporaryUrl(
                $path,
                now()->addMinutes(config('story_music.signed_url_minutes'))
            );
        }
        catch (Exception) {
            try {
                return Storage::disk($disk)->url($path);
            }
            catch (Exception) {
                return null;
            }
        }
    }

    private function normalizeCollection(string $collection): string
    {
        $collection = str($collection)->lower()->replace(['-', ' '], '_')->toString();

        if(in_array($collection, config('story_music.collections'), true)) {
            return $collection;
        }

        return 'for_you';
    }

    private function applySort($query, string $sort): void
    {
        match ($sort) {
            'trending' => $query->orderByDesc('usage_count')->orderByDesc('created_at'),
            'newest' => $query->orderByDesc('created_at'),
            'oldest' => $query->orderBy('created_at'),
            'title' => $query->orderBy('title'),
            default => $query->orderBy('sort_order')->orderByDesc('usage_count')->orderBy('title'),
        };
    }

    private function userOwnsMedia(Request $request, Media $media): bool
    {
        $media->loadMissing('mediaable');

        if($media->mediaable instanceof Post) {
            return $media->mediaable->user_id === $request->user()->id;
        }

        if($media->mediaable instanceof StoryFrame) {
            return $media->mediaable->story?->user_id === $request->user()->id;
        }

        return false;
    }
}
