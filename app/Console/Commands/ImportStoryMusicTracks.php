<?php

namespace App\Console\Commands;

use App\Models\StoryMusicTrack;
use App\Support\StoryMusic\StoryMusicSearchIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SplFileObject;

class ImportStoryMusicTracks extends Command
{
    protected $signature = 'story-music:import
        {--catalog=database/data/story_music/music_catalog.csv : CSV catalog path}
        {--source-dir=storage/app/story-music-library/sources : Folder containing audio and cover files}
        {--disk= : Filesystem disk to upload to}
        {--dry-run : Validate catalog without uploading or writing database rows}
        {--force : Replace existing R2 objects and database rows}';

    protected $description = 'Upload curated story music files to R2 and sync story music catalog metadata.';

    public function handle(): int
    {
        $catalogPath = $this->absolutePath((string) $this->option('catalog'));
        $sourceDir = rtrim($this->absolutePath((string) $this->option('source-dir')), DIRECTORY_SEPARATOR);
        $disk = (string) ($this->option('disk') ?: config('story_music.disk'));
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if(! file_exists($catalogPath)) {
            $this->error("Catalog CSV not found: {$catalogPath}");
            return self::FAILURE;
        }

        if(! is_dir($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");
            return self::FAILURE;
        }

        if(! config("filesystems.disks.{$disk}")) {
            $this->error("Filesystem disk [{$disk}] is not configured.");
            return self::FAILURE;
        }

        if(! $dryRun && ! (bool) config("filesystems.disks.{$disk}.enabled", true)) {
            $this->error("Filesystem disk [{$disk}] is disabled. Set R2_MUSIC_ENABLED=true.");
            return self::FAILURE;
        }

        $rows = $this->readCsv($catalogPath);

        if(empty($rows)) {
            $this->warn('No music tracks found in catalog.');
            return self::SUCCESS;
        }

        $imported = 0;

        foreach($rows as $rowNumber => $row) {
            $track = $this->normalizeRow($row, $rowNumber + 2);

            if($track === null) {
                continue;
            }

            $audioPath = $this->resolveSourceFile($sourceDir, $track['audio_file']);
            $coverPath = filled($track['cover_file']) ? $this->resolveSourceFile($sourceDir, $track['cover_file']) : null;

            if(! file_exists($audioPath)) {
                $this->error("Audio file not found on row {$track['_row']}: {$audioPath}");
                return self::FAILURE;
            }

            if($coverPath !== null && ! file_exists($coverPath)) {
                $this->error("Cover file not found on row {$track['_row']}: {$coverPath}");
                return self::FAILURE;
            }

            $remoteBasePath = trim(config('story_music.prefix'), '/') . '/' . $track['source_key'] . '/' . $track['slug'];
            $remoteAudioPath = "{$remoteBasePath}/audio." . strtolower(pathinfo($audioPath, PATHINFO_EXTENSION));
            $remoteCoverPath = $coverPath ? "{$remoteBasePath}/cover." . strtolower(pathinfo($coverPath, PATHINFO_EXTENSION)) : null;

            if($dryRun) {
                $this->line("DRY RUN row {$track['_row']}: {$track['title']} -> {$remoteAudioPath}");
                $imported++;
                continue;
            }

            if(! $force && StoryMusicTrack::where('slug', $track['slug'])->exists()) {
                $this->warn("Skipped existing track [{$track['slug']}]. Use --force to replace it.");
                continue;
            }

            $this->uploadFile($disk, $remoteAudioPath, $audioPath, $force);

            if($coverPath && $remoteCoverPath) {
                $this->uploadFile($disk, $remoteCoverPath, $coverPath, $force);
            }

            StoryMusicTrack::updateOrCreate(
                ['slug' => $track['slug']],
                [
                    'title' => $track['title'],
                    'artist' => $track['artist'],
                    'source' => $track['source'],
                    'source_url' => $track['source_url'],
                    'license_url' => $track['license_url'],
                    'license_type' => $track['license_type'],
                    'disk' => $disk,
                    'audio_path' => $remoteAudioPath,
                    'cover_path' => $remoteCoverPath,
                    'duration_seconds' => $track['duration_seconds'],
                    'audio_quality_score' => 100,
                    'mood' => $track['mood'],
                    'genre' => $track['genre'],
                    'collection' => $track['collection'],
                    'tags' => $track['tags'],
                    'search_text' => StoryMusicSearchIndex::build([
                        $track['title'],
                        $track['artist'],
                        $track['source'],
                        $track['mood'],
                        $track['genre'],
                        $track['collection'],
                        $track['tags'],
                    ]),
                    'sort_order' => $track['sort_order'],
                    'is_active' => $track['is_active'],
                    'meta' => [
                        'imported_from' => $catalogPath,
                        'original_audio_file' => $track['audio_file'],
                        'original_cover_file' => $track['cover_file'],
                    ],
                ]
            );

            $this->info("Imported {$track['title']} -> {$remoteAudioPath}");
            $imported++;
        }

        $this->info("Done. Imported/validated {$imported} track(s).");

        return self::SUCCESS;
    }

    private function readCsv(string $catalogPath): array
    {
        $file = new SplFileObject($catalogPath);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $headers = [];
        $rows = [];

        foreach($file as $index => $columns) {
            if($columns === [null] || $columns === false) {
                continue;
            }

            if($index === 0) {
                $headers = array_map(fn ($header) => trim((string) $header), $columns);
                continue;
            }

            $row = [];

            foreach($headers as $columnIndex => $header) {
                $row[$header] = trim((string) ($columns[$columnIndex] ?? ''));
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function normalizeRow(array $row, int $rowNumber): ?array
    {
        if(blank(implode('', $row))) {
            return null;
        }

        $licenseType = strtolower((string) data_get($row, 'license_type'));
        $blockedLicenseFragments = ['nc', 'nd', 'non-commercial', 'no-derivatives'];

        foreach($blockedLicenseFragments as $blocked) {
            if(str_contains($licenseType, $blocked)) {
                $this->error("Blocked license type on row {$rowNumber}: {$licenseType}");
                return null;
            }
        }

        if(! in_array($licenseType, config('story_music.license_types'), true)) {
            $this->error("Unsupported license_type on row {$rowNumber}: {$licenseType}");
            return null;
        }

        $title = (string) data_get($row, 'title');
        $source = (string) data_get($row, 'source');
        $sourceUrl = (string) data_get($row, 'source_url');
        $licenseUrl = (string) data_get($row, 'license_url');
        $audioFile = (string) data_get($row, 'audio_file');

        foreach(['title' => $title, 'source' => $source, 'source_url' => $sourceUrl, 'license_url' => $licenseUrl, 'audio_file' => $audioFile] as $field => $value) {
            if(blank($value)) {
                $this->error("Missing {$field} on row {$rowNumber}.");
                return null;
            }
        }

        $collection = $this->normalizeCollection((string) data_get($row, 'collection', 'for_you'));

        return [
            '_row' => $rowNumber,
            'slug' => Str::slug((string) data_get($row, 'slug') ?: "{$source}-{$title}"),
            'title' => $title,
            'artist' => (string) data_get($row, 'artist') ?: null,
            'source' => $source,
            'source_key' => Str::slug($source) ?: 'curated',
            'source_url' => $sourceUrl,
            'license_url' => $licenseUrl,
            'license_type' => $licenseType,
            'audio_file' => $audioFile,
            'cover_file' => (string) data_get($row, 'cover_file'),
            'duration_seconds' => filled(data_get($row, 'duration_seconds')) ? (int) data_get($row, 'duration_seconds') : null,
            'mood' => (string) data_get($row, 'mood') ?: null,
            'genre' => (string) data_get($row, 'genre') ?: null,
            'collection' => $collection,
            'tags' => $this->parseTags((string) data_get($row, 'tags')),
            'sort_order' => filled(data_get($row, 'sort_order')) ? (int) data_get($row, 'sort_order') : 0,
            'is_active' => ! in_array(strtolower((string) data_get($row, 'is_active', '1')), ['0', 'false', 'no'], true),
        ];
    }

    private function normalizeCollection(string $collection): string
    {
        $collection = Str::of($collection)->lower()->replace(['-', ' '], '_')->toString();

        if(in_array($collection, config('story_music.collections'), true)) {
            return $collection;
        }

        return 'for_you';
    }

    private function parseTags(string $tags): array
    {
        return collect(explode('|', $tags))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->values()
            ->all();
    }

    private function resolveSourceFile(string $sourceDir, string $path): string
    {
        if(str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return $sourceDir . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    private function uploadFile(string $disk, string $remotePath, string $localPath, bool $force): void
    {
        if(! $force && Storage::disk($disk)->exists($remotePath)) {
            $this->warn("Skipped existing R2 object: {$remotePath}");
            return;
        }

        $stream = fopen($localPath, 'rb');

        try {
            Storage::disk($disk)->put($remotePath, $stream, [
                'visibility' => 'private',
                'ContentType' => mime_content_type($localPath) ?: 'application/octet-stream',
            ]);
        }
        finally {
            if(is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function absolutePath(string $path): string
    {
        if(str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }
}
