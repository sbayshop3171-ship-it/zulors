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
use App\Services\Filesystem\Base64Image\Base64ImageService;
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use App\Services\Filesystem\Upload\ImageUploadService;
use App\Services\Timeline\TopicExtractionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TestAccountImagePublisher
{
    private const SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

    private const COPY = [
        [
            'title' => 'A visual note for today',
            'content' => 'A small moment, shared with a little more attention. #testgallery #visualstory',
        ],
        [
            'title' => 'A closer look at the details',
            'content' => 'There is often something useful in the details we slow down long enough to notice. #testgallery #perspective',
        ],
        [
            'title' => 'A moment in focus',
            'content' => 'A simple visual update from this test profile. #testgallery #dailyshare',
        ],
        [
            'title' => 'Saved from the day',
            'content' => 'A visual that felt worth keeping in the conversation. #testgallery #sharedmoment',
        ],
        [
            'title' => 'A fresh perspective',
            'content' => 'A little color, a little context, and a new point of view. #testgallery #visualnote',
        ],
    ];

    public function __construct(
        private readonly ImageUploadService $imageUploadService,
        private readonly RoundRobinService $roundRobinService,
        private readonly Base64ImageService $base64ImageService,
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
     * @return array{user_ids: array<int, int>, source_files: array<int, string>, eligible_count: int, source_count: int}
     */
    public function preview(string $sourceDirectory, int $limit = 0): array
    {
        $sourceFiles = $this->sourceFiles($sourceDirectory);
        $eligibleUserIds = $this->eligibleUsers()
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $targetCount = min(count($eligibleUserIds), count($sourceFiles));

        if($limit > 0) {
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

        foreach(array_chunk(array_keys($userIds), 50) as $positions) {
            $batchUserIds = array_map(fn (int $position) => $userIds[$position], $positions);
            $users = User::query()
                ->whereIn('id', $batchUserIds)
                ->get()
                ->keyBy('id');

            foreach($positions as $position) {
                $user = $users->get($userIds[$position]);
                $sourceFile = $sourceFiles[$position];

                if(! $user) {
                    $summary['failed']++;
                    $onProgress?->__invoke();
                    continue;
                }

                try {
                    $published = $this->publishForUser($user, $campaignKey, $sourceFile);
                    $summary[$published ? 'published' : 'skipped']++;
                }
                catch(Throwable $exception) {
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
        if(! is_dir($sourceDirectory)) {
            return [];
        }

        return collect(File::allFiles($sourceDirectory))
            ->filter(function(\SplFileInfo $file) {
                return in_array(strtolower($file->getExtension()), self::SUPPORTED_EXTENSIONS, true);
            })
            ->sortBy(fn (\SplFileInfo $file) => $file->getRelativePathname())
            ->map(fn (\SplFileInfo $file) => $file->getPathname())
            ->values()
            ->all();
    }

    private function publishForUser(User $user, string $campaignKey, string $sourceFile): bool
    {
        if(! is_file($sourceFile)) {
            throw new \RuntimeException('The assigned source image no longer exists.');
        }

        $uploadedImage = null;

        try {
            return DB::transaction(function() use ($user, $campaignKey, $sourceFile, &$uploadedImage) {
                $publication = TestContentPublication::query()
                    ->lockForUpdate()
                    ->firstOrCreate([
                        'campaign_key' => $campaignKey,
                        'user_id' => $user->id,
                    ], [
                        'content_key' => $this->contentKey($sourceFile),
                        'status' => 'reserved',
                    ]);

                if($publication->status === 'published' && $publication->post_id) {
                    return false;
                }

                $copy = $this->copyFor($sourceFile);
                $post = Post::query()->create([
                    'user_id' => $user->id,
                    'title' => $copy['title'],
                    'content' => $copy['content'],
                    'status' => PostStatus::ACTIVE,
                    'type' => PostType::IMAGE,
                    'text_language' => '',
                    'is_ai_generated' => true,
                ]);

                $imageDisk = $this->roundRobinService->getNextDisk();
                $uploadedImage = $this->imageUploadService
                    ->load($sourceFile)
                    ->setNamespace(Filesystem::mediaNamespace('post/images'))
                    ->setStorageDisk($imageDisk)
                    ->scaleDownToFit(config('media.images.max_width'), config('media.images.max_height'))
                    ->watermark()
                    ->compress(config('post.processing.image.compress_rate'))
                    ->upload();
                $lqip = $this->base64ImageService->load($sourceFile)->getBase64();
                $previewLqip = $this->base64ImageService
                    ->load($sourceFile)
                    ->setScaleWidth(256)
                    ->setBlurRadius(0)
                    ->getBase64();

                $post->media()->create([
                    'source_path' => $uploadedImage['image_path'],
                    'type' => MediaType::IMAGE,
                    'status' => MediaStatus::PROCESSED,
                    'disk' => $uploadedImage['disk'],
                    'extension' => pathinfo($uploadedImage['image_path'], PATHINFO_EXTENSION),
                    'mime' => $this->mimeFor(pathinfo($uploadedImage['image_path'], PATHINFO_EXTENSION)),
                    'size' => $uploadedImage['image_size'],
                    'lqip_base64' => $lqip,
                    'metadata' => [
                        'test_content_campaign' => $campaignKey,
                    ],
                ]);

                $post->preview_lqip_base64 = $previewLqip;
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
        }
        catch(Throwable $exception) {
            if($uploadedImage) {
                Storage::disk($uploadedImage['disk'])->delete($uploadedImage['image_path']);
            }

            throw $exception;
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
        return 'image:' . sha1($sourceFile . '|' . filesize($sourceFile));
    }

    private function mimeFor(string $extension): string
    {
        return match($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            default => 'application/octet-stream',
        };
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
}
