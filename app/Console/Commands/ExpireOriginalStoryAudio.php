<?php

namespace App\Console\Commands;

use App\Database\Configs\Table;
use App\Models\StoryMusicTrack;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ExpireOriginalStoryAudio extends Command
{
    protected $signature = 'story-music:expire-original-audio
        {--limit=500 : Maximum expired tracks to process}
        {--delete-files : Delete expired MP3 files from storage}
        {--dry-run : Show expired tracks without changing anything}';

    protected $description = 'Deactivate expired user-uploaded original audio tracks.';

    public function handle(): int
    {
        if(! Schema::hasTable(Table::STORY_MUSIC_TRACKS) || ! Schema::hasColumn(Table::STORY_MUSIC_TRACKS, 'expires_at')) {
            $this->warn('Story music expiration is not migrated yet.');
            return self::SUCCESS;
        }

        $tracks = StoryMusicTrack::query()
            ->where('source', 'user_upload')
            ->where('collection', 'original_audio')
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if($tracks->isEmpty()) {
            $this->info('No expired original-audio tracks found.');
            return self::SUCCESS;
        }

        foreach($tracks as $track) {
            $this->line("track={$track->id} media={$track->source_media_id} expired_at={$track->expires_at?->toIso8601String()}");

            if($this->option('dry-run')) {
                continue;
            }

            if($this->option('delete-files')) {
                $this->deleteTrackFile($track);
            }

            $meta = $track->meta ?? [];
            $meta['expired_at'] = now()->toIso8601String();

            $track->forceFill([
                'is_active' => false,
                'review_status' => 'expired',
                'meta' => $meta,
            ])->save();
        }

        $this->info($this->option('dry-run')
            ? "Dry run complete. {$tracks->count()} expired track(s)."
            : "Expired {$tracks->count()} original-audio track(s)."
        );

        return self::SUCCESS;
    }

    private function deleteTrackFile(StoryMusicTrack $track): void
    {
        if(blank($track->audio_path)) {
            return;
        }

        try {
            Storage::disk($track->disk)->delete($track->audio_path);
        }
        catch (\Throwable $e) {
            $this->warn("Could not delete audio file for track {$track->id}: {$e->getMessage()}");
        }
    }
}
