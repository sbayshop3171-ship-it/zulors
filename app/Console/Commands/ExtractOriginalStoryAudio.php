<?php

namespace App\Console\Commands;

use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Jobs\User\Story\ExtractOriginalAudioFromMedia;
use App\Models\Media;
use App\Models\Post;
use App\Models\StoryFrame;
use App\Support\StoryMusic\OriginalAudioEligibility;
use Illuminate\Console\Command;

class ExtractOriginalStoryAudio extends Command
{
    protected $signature = 'story-music:extract-original-audio
        {--source=all : all, posts, or stories}
        {--limit=50 : Maximum videos to queue}
        {--media-id=* : Queue specific media IDs}
        {--ignore-consent : Admin-only approved batch mode for existing videos}
        {--include-uncategorized : Include processed videos without an allowed story music category}
        {--category=* : Limit to one or more story music categories}
        {--mark-category= : Mark queued media with this story music category before extracting}
        {--publish : Publish extracted tracks immediately}
        {--force : Replace existing original-audio tracks}
        {--dry-run : Show candidate videos without queueing jobs}';

    protected $description = 'Queue extraction of reusable original audio from processed video media.';

    public function handle(): int
    {
        if(! (bool) config('story_music.original_audio.enabled', true)) {
            $this->error('Original audio extraction is disabled. Set STORY_MUSIC_ORIGINAL_AUDIO_ENABLED=true.');
            return self::FAILURE;
        }

        $markCategory = OriginalAudioEligibility::normalizeCategory((string) $this->option('mark-category'));

        if($markCategory && ! OriginalAudioEligibility::isAllowedCategory($markCategory)) {
            $this->error('Invalid --mark-category. Allowed: ' . implode(', ', OriginalAudioEligibility::allowedCategories()));
            return self::FAILURE;
        }

        $query = Media::query()
            ->with('mediaable')
            ->where('type', MediaType::VIDEO->value)
            ->where('status', MediaStatus::PROCESSED->value)
            ->where('disk', '!=', 'cloudflare_stream')
            ->orderByDesc('id');

        $mediaIds = array_filter(array_map('intval', (array) $this->option('media-id')));

        if(! empty($mediaIds)) {
            $query->whereIn('id', $mediaIds);
        }
        else {
            $this->applySourceFilter($query, (string) $this->option('source'));
            $query->limit(max(1, (int) $this->option('limit')));
        }

        if(! $this->option('ignore-consent') && (bool) config('story_music.original_audio.require_consent', true)) {
            $query->where(function ($query) {
                $query->where('metadata->story_music->allow_reuse', true)
                    ->orWhere('metadata->story_music->allow_reuse', 'true')
                    ->orWhere('metadata->story_music->allow_reuse', 1)
                    ->orWhere('metadata->story_music->allow_reuse', '1');
            });
        }

        $mediaItems = $query->get(['id', 'mediaable_type', 'mediaable_id', 'source_path', 'disk', 'metadata']);
        $mediaItems = $this->filterByCategory($mediaItems);

        if($mediaItems->isEmpty()) {
            $this->warn('No processed videos matched the original-audio extraction filters.');
            return self::SUCCESS;
        }

        $ignoreCategory = (bool) $this->option('include-uncategorized');

        foreach($mediaItems as $media) {
            if($markCategory) {
                OriginalAudioEligibility::applyCategory($media, $markCategory);
            }

            if(! OriginalAudioEligibility::shouldAutoExtract($media, $ignoreCategory)) {
                $this->line("skip media={$media->id} reason=category_missing_or_not_allowed");
                continue;
            }

            $category = OriginalAudioEligibility::categoryFor($media) ?: 'uncategorized';
            $this->line("media={$media->id} type={$media->mediaable_type} disk={$media->disk} category={$category}");

            if($this->option('dry-run')) {
                continue;
            }

            if($markCategory) {
                $media->save();
            }

            ExtractOriginalAudioFromMedia::dispatch(
                $media->id,
                (bool) $this->option('force'),
                (bool) $this->option('ignore-consent'),
                $this->option('publish') ? true : null,
                $ignoreCategory
            )->onQueue(config('media.queues.audio'));
        }

        $this->info($this->option('dry-run')
            ? "Dry run complete. {$mediaItems->count()} candidate video(s)."
            : "Queued {$mediaItems->count()} original-audio extraction job(s)."
        );

        return self::SUCCESS;
    }

    private function applySourceFilter($query, string $source): void
    {
        $source = strtolower($source);

        if($source === 'posts') {
            $query->where('mediaable_type', Post::class);
            return;
        }

        if($source === 'stories') {
            $query->where('mediaable_type', StoryFrame::class);
            return;
        }

        if($source !== 'all') {
            $this->warn('Unknown source option. Using all video media.');
        }
    }

    private function filterByCategory($mediaItems)
    {
        $requestedCategories = collect((array) $this->option('category'))
            ->map(fn ($category) => OriginalAudioEligibility::normalizeCategory((string) $category))
            ->filter()
            ->values();

        if($requestedCategories->isEmpty()) {
            return $mediaItems;
        }

        return $mediaItems
            ->filter(fn (Media $media) => $requestedCategories->contains(OriginalAudioEligibility::categoryFor($media)))
            ->values();
    }
}
