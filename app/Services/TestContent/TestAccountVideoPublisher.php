<?php

namespace App\Services\TestContent;

use App\Constants\Filesystem;
use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Enums\Post\PostStatus;
use App\Enums\Post\PostType;
use App\Models\Post;
use App\Models\TestContentPublication;
use App\Models\User;
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use App\Services\Filesystem\Upload\ImageUploadService;
use App\Services\Filesystem\Upload\VideoThumbnailService;
use App\Services\Filesystem\Upload\VideoUploadService;
use App\Services\Timeline\TopicExtractionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TestAccountVideoPublisher
{
    private const SUPPORTED_EXTENSIONS = ['mp4', 'mov', 'm4v', 'avi', 'webm'];

    private const COPY = [
        [
            'title' => 'A short video update',
            'content' => 'A quick clip shared from this testing profile. #testvideo #dailyclip',
        ],
        [
            'title' => 'A moment captured on video',
            'content' => 'A little movement, a little context, and a clearer look at the moment. #testvideo #videostory',
        ],
        [
            'title' => 'A closer video look',
            'content' => 'A simple visual update that felt worth posting today. #testvideo #sharedclip',
        ],
        [
            'title' => 'A visual clip from the day',
            'content' => 'A short video note added for this test account feed. #testvideo #visualnote',
        ],
        [
            'title' => 'A fresh clip in focus',
            'content' => 'A new video post with a little atmosphere and a little motion. #testvideo #focusclip',
        ],
    ];

    public function __construct(
        private readonly VideoUploadService $videoUploadService,
        private readonly VideoThumbnailService $videoThumbnailService,
        private readonly ImageUploadService $imageUploadService,
        private readonly RoundRobinService $roundRobinService,
        private readonly TopicExtractionService $topicExtractionService,
    ) {
    }

    public function eligibleUsers(): Builder
    {
        return User::query()
            ->active()
            ->whereRaw('LOWER(email) LIKE ?', ['%.test']);
    }

    public function alreadyPublishedCount(string $campaignKey): int
    {
        return TestContentPublication::query()
            ->where('campaign_key', $campaignKey)
            ->where('status', 'published')
            ->whereNotNull('post_id')
            ->count();
    }

    /**
     * @param array<int, string> $sourceDirectories
     * @return array{user_ids: array<int, int>, source_files: array<int, string>, eligible_count: int, source_count: int}
     */
    public function previewForDirectories(
        array $sourceDirectories,
        int $limit = 0,
        bool $randomizeUsers = false,
        ?string $selectionSeed = null,
    ): array
    {
        $sourceFiles = $this->sourceFilesForDirectories($sourceDirectories);
        $eligibleUserIds = $this->eligibleUsers()
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($randomizeUsers) {
            $eligibleUserIds = $this->stableShuffle($eligibleUserIds, $selectionSeed ?: 'test-content-video-publisher');
        }

        $targetCount = min(count($eligibleUserIds), count($sourceFiles));

        if ($limit > 0) {
            $targetCount = min($targetCount, $limit);
        }

        return [
            'user_ids' => array_slice($eligibleUserIds, 0, $targetCount),
            'source_files' => array_slice($sourceFiles, 0, $targetCount),
            'eligible_count' => count($eligibleUserIds),
            'source_count' => count($sourceFiles),
        ];
    }

    /**
     * @param array<int, int> $userIds
     * @param array<int, string> $sourceFiles
     * @return array{published: int, skipped: int, failed: int}
     */
    public function publish(string $campaignKey, array $userIds, array $sourceFiles, ?callable $onProgress = null): array
    {
        $summary = [
            'published' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach (array_chunk(array_keys($userIds), 25) as $positions) {
            $batchUserIds = array_map(fn (int $position) => $userIds[$position], $positions);
            $users = User::query()
                ->whereIn('id', $batchUserIds)
                ->get()
                ->keyBy('id');

            foreach ($positions as $position) {
                $user = $users->get($userIds[$position]);
                $sourceFile = $sourceFiles[$position];

                if (! $user) {
                    $summary['failed']++;
                    $onProgress?->__invoke();
                    continue;
                }

                try {
                    $published = $this->publishForUser($user, $campaignKey, $sourceFile);
                    $summary[$published ? 'published' : 'skipped']++;
                } catch (Throwable $exception) {
                    report($exception);
                    $this->markFailed($user, $campaignKey, $sourceFile, $exception);
                    $summary['failed']++;
                }

                $onProgress?->__invoke();
            }
        }

        return $summary;
    }

    /**
     * @return array<int, string>
     */
    public function sourceFiles(string $sourceDirectory): array
    {
        if (! is_dir($sourceDirectory)) {
            return [];
        }

        return collect(File::allFiles($sourceDirectory))
            ->filter(function (\SplFileInfo $file) {
                return in_array(strtolower($file->getExtension()), self::SUPPORTED_EXTENSIONS, true);
            })
            ->sortBy(fn (\SplFileInfo $file) => $file->getRelativePathname())
            ->map(fn (\SplFileInfo $file) => $file->getPathname())
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $sourceDirectories
     * @return array<int, string>
     */
    public function sourceFilesForDirectories(array $sourceDirectories): array
    {
        $files = [];
        $seen = [];

        foreach ($sourceDirectories as $sourceDirectory) {
            foreach ($this->sourceFiles($sourceDirectory) as $sourceFile) {
                $identity = realpath($sourceFile) ?: $sourceFile;

                if (isset($seen[$identity])) {
                    continue;
                }

                $seen[$identity] = true;
                $files[] = $sourceFile;
            }
        }

        return $files;
    }

    private function publishForUser(User $user, string $campaignKey, string $sourceFile): bool
    {
        if (! is_file($sourceFile)) {
            throw new \RuntimeException('The assigned source video no longer exists.');
        }

        $uploadedVideo = null;
        $uploadedThumbnail = null;
        $tempVideoPath = null;
        $tempThumbnailAbsolutePath = null;

        try {
            return DB::transaction(function () use (
                $user,
                $campaignKey,
                $sourceFile,
                &$uploadedVideo,
                &$uploadedThumbnail,
                &$tempVideoPath,
                &$tempThumbnailAbsolutePath,
            ) {
                $publication = TestContentPublication::query()
                    ->lockForUpdate()
                    ->firstOrCreate([
                        'campaign_key' => $campaignKey,
                        'user_id' => $user->id,
                    ], [
                        'content_key' => $this->contentKey($sourceFile),
                        'status' => 'reserved',
                    ]);

                if ($publication->status === 'published' && $publication->post_id) {
                    return false;
                }

                $copy = $this->copyFor($sourceFile);
                $targetDisk = $this->roundRobinService->getNextDisk();
                $extension = strtolower(pathinfo($sourceFile, PATHINFO_EXTENSION) ?: 'mp4');
                $tempVideoPath = $this->copySourceVideoToTemporaryLocalPath($sourceFile, $extension);

                $durationSeconds = (int) $this->videoUploadService->getVideoDuration($tempVideoPath);
                $dimensions = $this->videoUploadService->getVideoDimensions($tempVideoPath);
                $tempThumbnailAbsolutePath = $this->videoThumbnailService->generateThumbnail($tempVideoPath);

                $uploadedThumbnail = $this->imageUploadService
                    ->load($tempThumbnailAbsolutePath)
                    ->setNamespace(Filesystem::mediaNamespace('posts/video_thumbnails'))
                    ->setStorageDisk($targetDisk)
                    ->watermark()
                    ->compress((int) config('post.processing.thumbnail.compress_rate'))
                    ->upload();

                $uploadedVideo = $this->uploadVideoFromLocalTemp($tempVideoPath, $targetDisk, $extension);

                $post = Post::query()->create([
                    'user_id' => $user->id,
                    'title' => $copy['title'],
                    'content' => $copy['content'],
                    'status' => PostStatus::ACTIVE,
                    'type' => PostType::VIDEO,
                    'text_language' => '',
                    'is_ai_generated' => true,
                ]);

                $post->media()->create([
                    'source_path' => $uploadedVideo['video_path'],
                    'type' => MediaType::VIDEO,
                    'status' => MediaStatus::PROCESSED,
                    'disk' => $uploadedVideo['disk'],
                    'extension' => $extension,
                    'mime' => $this->mimeFor($extension),
                    'size' => $uploadedVideo['video_size'],
                    'thumbnail_path' => $uploadedThumbnail['image_path'],
                    'thumbnail_size' => $uploadedThumbnail['image_size'],
                    'thumbnail_disk' => $uploadedThumbnail['disk'],
                    'metadata' => [
                        'duration' => parse_duration($durationSeconds),
                        'duration_seconds' => $durationSeconds,
                        'dimensions' => $dimensions,
                        'aspect_ratio' => $this->aspectRatio($dimensions),
                        'is_portrait' => $this->isPortrait($dimensions),
                        'test_content_campaign' => $campaignKey,
                        'test_content_source' => basename($sourceFile),
                    ],
                ]);

                $post->text_language = $post->getContentLanguage();
                $post->save();

                $this->topicExtractionService->syncPostTopics($post);
                User::query()->whereKey($user->id)->increment('publications_count');

                $publication->update([
                    'post_id' => $post->id,
                    'content_key' => $this->contentKey($sourceFile),
                    'status' => 'published',
                    'error_message' => null,
                    'published_at' => now(),
                ]);

                return true;
            }, 1);
        } catch (Throwable $exception) {
            if ($uploadedVideo) {
                Storage::disk($uploadedVideo['disk'])->delete($uploadedVideo['video_path']);
            }

            if ($uploadedThumbnail) {
                Storage::disk($uploadedThumbnail['disk'])->delete($uploadedThumbnail['image_path']);
            }

            throw $exception;
        } finally {
            if ($tempVideoPath && Storage::disk('local')->exists($tempVideoPath)) {
                Storage::disk('local')->delete($tempVideoPath);
            }

            if ($tempThumbnailAbsolutePath && is_file($tempThumbnailAbsolutePath)) {
                @unlink($tempThumbnailAbsolutePath);
            }
        }
    }

    /**
     * @return array{title: string, content: string}
     */
    private function copyFor(string $sourceFile): array
    {
        return self::COPY[crc32($sourceFile) % count(self::COPY)];
    }

    private function contentKey(string $sourceFile): string
    {
        return 'video:' . sha1($sourceFile . '|' . filesize($sourceFile) . '|' . filemtime($sourceFile));
    }

    private function markFailed(User $user, string $campaignKey, string $sourceFile, Throwable $exception): void
    {
        TestContentPublication::query()->updateOrCreate([
            'campaign_key' => $campaignKey,
            'user_id' => $user->id,
        ], [
            'content_key' => $this->contentKey($sourceFile),
            'status' => 'failed',
            'error_message' => mb_substr($exception->getMessage(), 0, 5000),
        ]);
    }

    private function copySourceVideoToTemporaryLocalPath(string $sourceFile, string $extension): string
    {
        $relativePath = $this->videoUploadService->generateVideoTemporaryFilePath($extension);
        $absolutePath = storage_local_path($relativePath);

        File::ensureDirectoryExists(dirname($absolutePath));

        if (! @copy($sourceFile, $absolutePath)) {
            throw new \RuntimeException("Video source could not be copied to local temporary storage: {$sourceFile}");
        }

        return $relativePath;
    }

    /**
     * @return array{disk: string, video_path: string, video_size: int}
     */
    private function uploadVideoFromLocalTemp(string $relativeLocalPath, string $targetDisk, string $extension): array
    {
        $absoluteLocalPath = storage_local_path($relativeLocalPath);
        $videoPath = Filesystem::mediaNamespace('posts/videos') . '/' . \Illuminate\Support\Str::uuid() . '.' . $extension;
        $stream = fopen($absoluteLocalPath, 'rb');

        if (! is_resource($stream)) {
            throw new \RuntimeException("Processed video source ({$absoluteLocalPath}) could not be opened.");
        }

        $options = [
            'visibility' => 'public',
            'ContentType' => $this->mimeFor($extension),
        ];

        if ($cacheControl = config('media.cache.control')) {
            $options['CacheControl'] = $cacheControl;
        }

        try {
            $stored = Storage::disk($targetDisk)->put($videoPath, $stream, $options);
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            throw new \RuntimeException("Processed video uploading to disk ({$targetDisk}) failed.");
        }

        return [
            'disk' => $targetDisk,
            'video_path' => $videoPath,
            'video_size' => Storage::disk($targetDisk)->size($videoPath),
        ];
    }

    private function mimeFor(string $extension): string
    {
        return match (strtolower($extension)) {
            'mp4', 'm4v' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'webm' => 'video/webm',
            default => 'application/octet-stream',
        };
    }

    private function aspectRatio(array $dimensions): ?float
    {
        $width = (int) ($dimensions['width'] ?? 0);
        $height = (int) ($dimensions['height'] ?? 0);

        return ($width > 0 && $height > 0) ? round($width / $height, 6) : null;
    }

    private function isPortrait(array $dimensions): bool
    {
        $width = (int) ($dimensions['width'] ?? 0);
        $height = (int) ($dimensions['height'] ?? 0);

        return $width > 0 && $height > 0 && $width < $height;
    }

    /**
     * @param array<int, int> $values
     * @return array<int, int>
     */
    private function stableShuffle(array $values, string $seed): array
    {
        $weightedValues = array_map(function (int $value) use ($seed) {
            return [
                'value' => $value,
                'weight' => hash('sha256', $seed . '|' . $value),
            ];
        }, $values);

        usort($weightedValues, fn (array $left, array $right) => $left['weight'] <=> $right['weight']);

        return array_column($weightedValues, 'value');
    }
}
