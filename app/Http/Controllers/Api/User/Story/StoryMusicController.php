<?php

namespace App\Http\Controllers\Api\User\Story;

use Exception;
use App\Models\StoryMusicTrack;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . config('story_music.max_per_page')],
        ]);

        $tracks = StoryMusicTrack::query()
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
                $search = '%' . str_replace('%', '\\%', (string) $request->input('search')) . '%';

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('title', 'like', $search)
                        ->orWhere('artist', 'like', $search)
                        ->orWhere('mood', 'like', $search)
                        ->orWhere('genre', 'like', $search);
                });
            })
            ->orderBy('sort_order')
            ->orderByDesc('usage_count')
            ->orderBy('title')
            ->paginate($request->integer('per_page', 20));

        return $this->responseSuccess([
            'data' => $tracks->through(fn (StoryMusicTrack $track) => $this->trackPayload($track)),
        ]);
    }

    public function show(StoryMusicTrack $track)
    {
        if(! $track->is_active) {
            return $this->responseResourceNotFoundError('StoryMusicTrack', $track->id);
        }

        return $this->responseSuccess([
            'data' => $this->trackPayload($track, true),
        ]);
    }

    public function playUrl(StoryMusicTrack $track)
    {
        if(! $track->is_active) {
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
            'mood' => $track->mood,
            'genre' => $track->genre,
            'collection' => $track->collection,
            'tags' => $track->tags ?? [],
            'cover_url' => $track->cover_path ? $this->temporaryFileUrl($track->disk, $track->cover_path) : null,
            'play_url_endpoint' => route('api.story.music.play-url', ['track' => $track->id], false),
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
}
