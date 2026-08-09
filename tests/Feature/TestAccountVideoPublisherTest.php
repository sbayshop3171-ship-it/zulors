<?php

namespace Tests\Feature;

use App\Enums\User\UserStatus;
use App\Models\Post;
use App\Models\TestContentPublication;
use App\Models\User;
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use App\Services\Filesystem\Upload\ImageUploadService;
use App\Services\Filesystem\Upload\VideoThumbnailService;
use App\Services\Filesystem\Upload\VideoUploadService;
use App\Services\TestContent\TestAccountVideoPublisher;
use App\Services\Timeline\TopicExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TestAccountVideoPublisherTest extends TestCase
{
    use RefreshDatabase;

    private string $sourceDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceDirectory = storage_path('framework/testing/test-video-import-' . uniqid());
        File::ensureDirectoryExists($this->sourceDirectory . '/one');
        File::ensureDirectoryExists($this->sourceDirectory . '/two');
        File::put($this->sourceDirectory . '/one/second.webm', 'video-two');
        File::put($this->sourceDirectory . '/two/first.mp4', 'video-one');
        File::put($this->sourceDirectory . '/ignored.txt', 'test');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sourceDirectory);

        parent::tearDown();
    }

    public function test_preview_only_targets_active_test_accounts_and_supported_videos(): void
    {
        $first = $this->createUser('video-test-one', 'video-test-one@gmail.test');
        $second = $this->createUser('video-test-two', 'video-test-two@gmail.test');
        $this->createUser('video-real-user', 'video-real@example.com');
        $this->createUser('video-inactive-test', 'video-inactive@gmail.test', UserStatus::BLOCKED);

        $preview = app(TestAccountVideoPublisher::class)->previewForDirectories([$this->sourceDirectory]);

        $this->assertSame(2, $preview['source_count']);
        $this->assertSame(2, $preview['eligible_count']);
        $this->assertSame([$first->id, $second->id], $preview['user_ids']);
        $this->assertCount(2, $preview['source_files']);
        $this->assertStringEndsWith('one/second.webm', $preview['source_files'][0]);
        $this->assertStringEndsWith('two/first.mp4', $preview['source_files'][1]);
    }

    public function test_multiple_source_directories_are_merged_in_order_without_duplicates(): void
    {
        $this->createUser('video-gallery-one', 'video-gallery-one@gmail.test');
        $this->createUser('video-gallery-two', 'video-gallery-two@gmail.test');
        $this->createUser('video-gallery-three', 'video-gallery-three@gmail.test');

        $secondDirectory = storage_path('framework/testing/test-video-import-second-' . uniqid());
        File::ensureDirectoryExists($secondDirectory);
        File::put($secondDirectory . '/third.mov', 'video-three');

        try {
            $preview = app(TestAccountVideoPublisher::class)->previewForDirectories([
                $this->sourceDirectory,
                $secondDirectory,
                $this->sourceDirectory,
            ]);

            $this->assertSame(3, $preview['source_count']);
            $this->assertCount(3, $preview['user_ids']);
            $this->assertStringEndsWith('one/second.webm', $preview['source_files'][0]);
            $this->assertStringEndsWith('two/first.mp4', $preview['source_files'][1]);
            $this->assertStringEndsWith('third.mov', $preview['source_files'][2]);
        } finally {
            File::deleteDirectory($secondDirectory);
        }
    }

    public function test_preview_can_select_test_accounts_in_stable_random_order(): void
    {
        $users = collect(range(1, 8))->map(function (int $index) {
            return $this->createUser("video-random-{$index}", "video-random-{$index}@gmail.test");
        });

        foreach (range(3, 8) as $index) {
            File::put($this->sourceDirectory . "/random-{$index}.mp4", "video-{$index}");
        }

        $orderedPreview = app(TestAccountVideoPublisher::class)->previewForDirectories([$this->sourceDirectory]);
        $firstRandomPreview = app(TestAccountVideoPublisher::class)->previewForDirectories(
            [$this->sourceDirectory],
            0,
            true,
            'stable-test-seed',
        );
        $secondRandomPreview = app(TestAccountVideoPublisher::class)->previewForDirectories(
            [$this->sourceDirectory],
            0,
            true,
            'stable-test-seed',
        );

        $this->assertSame($users->pluck('id')->all(), $orderedPreview['user_ids']);
        $this->assertSame($firstRandomPreview['user_ids'], $secondRandomPreview['user_ids']);
        $this->assertEqualsCanonicalizing($orderedPreview['user_ids'], $firstRandomPreview['user_ids']);
        $this->assertNotSame($orderedPreview['user_ids'], $firstRandomPreview['user_ids']);
    }

    public function test_command_dry_run_requires_explicit_confirmation_before_writing(): void
    {
        $this->createUser('video-command-one', 'video-command-one@gmail.test');

        $this->artisan("test-content:publish-videos --source={$this->sourceDirectory} --limit=1 --dry-run")
            ->expectsOutput('Video posts targeted in this run: 1')
            ->expectsOutput('Dry run complete. No posts or media were created.')
            ->assertExitCode(0);

        $this->artisan("test-content:publish-videos --source={$this->sourceDirectory} --limit=1")
            ->expectsOutput('Nothing was published. Re-run with --confirm=ALL_TEST_VIDEO_POSTS after checking the target count.')
            ->assertExitCode(1);
    }

    public function test_command_can_preview_a_publish_shard(): void
    {
        foreach (range(1, 5) as $index) {
            $this->createUser("video-shard-{$index}", "video-shard-{$index}@gmail.test");
            File::put($this->sourceDirectory . "/shard-{$index}.mp4", "video-{$index}");
        }

        $this->artisan("test-content:publish-videos --source={$this->sourceDirectory} --shards=3 --shard=1 --dry-run")
            ->expectsOutput('Video posts targeted in this run: 2')
            ->expectsOutput('Shard: 2 of 3')
            ->expectsOutput('Dry run complete. No posts or media were created.')
            ->assertExitCode(0);

        $this->artisan("test-content:publish-videos --source={$this->sourceDirectory} --shards=3 --shard=3 --dry-run")
            ->expectsOutput('The --shard value must be less than --shards.')
            ->assertExitCode(1);
    }

    public function test_publish_creates_one_processed_video_post_for_a_test_user(): void
    {
        Storage::fake('public');

        $user = $this->createUser('video-publish-one', 'video-publish-one@gmail.test');
        $sourceFile = $this->sourceDirectory . '/two/first.mp4';
        $thumbnailPath = storage_path('framework/testing/generated-thumb-' . uniqid() . '.jpg');
        File::put($thumbnailPath, 'thumbnail');

        $this->mock(RoundRobinService::class, function ($mock) {
            $mock->shouldReceive('getNextDisk')->once()->andReturn('public');
        });

        $this->mock(VideoUploadService::class, function ($mock) {
            $mock->shouldReceive('generateVideoTemporaryFilePath')->once()->with('mp4')->andReturn('tmp/videos/fake-test-video.mp4');
            $mock->shouldReceive('getVideoDuration')->once()->with('tmp/videos/fake-test-video.mp4')->andReturn(17);
            $mock->shouldReceive('getVideoDimensions')->once()->with('tmp/videos/fake-test-video.mp4')->andReturn([
                'width' => 1080,
                'height' => 1920,
            ]);
        });

        $this->mock(VideoThumbnailService::class, function ($mock) use ($thumbnailPath) {
            $mock->shouldReceive('generateThumbnail')->once()->with('tmp/videos/fake-test-video.mp4')->andReturn($thumbnailPath);
        });

        $this->mock(ImageUploadService::class, function ($mock) {
            $mock->shouldReceive('load')->once()->andReturnSelf();
            $mock->shouldReceive('setNamespace')->once()->andReturnSelf();
            $mock->shouldReceive('setStorageDisk')->once()->with('public')->andReturnSelf();
            $mock->shouldReceive('watermark')->once()->andReturnSelf();
            $mock->shouldReceive('compress')->once()->andReturnSelf();
            $mock->shouldReceive('upload')->once()->andReturn([
                'disk' => 'public',
                'image_path' => 'media/posts/video_thumbnails/test-thumb.jpg',
                'image_size' => 321,
            ]);
        });

        $this->mock(TopicExtractionService::class, function ($mock) {
            $mock->shouldReceive('syncPostTopics')->once();
        });

        try {
            $summary = app(TestAccountVideoPublisher::class)->publish(
                'video-test-campaign',
                [$user->id],
                [$sourceFile],
            );

            $this->assertSame([
                'published' => 1,
                'skipped' => 0,
                'failed' => 0,
            ], $summary);

            $post = Post::query()->firstOrFail();
            $media = $post->media()->firstOrFail();
            $publication = TestContentPublication::query()->firstOrFail();

            $this->assertTrue($post->type->isVideo());
            $this->assertSame(\App\Enums\Post\PostStatus::ACTIVE, $post->status);
            $this->assertTrue($media->type->isVideo());
            $this->assertTrue($media->status->isProcessed());
            $this->assertSame('public', $media->disk);
            $this->assertSame('public', $media->thumbnail_disk);
            $this->assertSame($post->id, $publication->post_id);
            $this->assertSame('published', $publication->status);
            Storage::disk('public')->assertExists($media->source_path);
        } finally {
            @unlink($thumbnailPath);
        }
    }

    private function createUser(string $username, string $email, UserStatus $status = UserStatus::ACTIVE): User
    {
        return User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'Account',
            'username' => $username,
            'caption' => '@' . $username,
            'email' => $email,
            'phone' => '',
            'website' => '',
            'bio' => '',
            'country' => null,
            'city' => null,
            'birth_day' => null,
            'birth_month' => null,
            'birth_year' => null,
            'age' => null,
            'gender' => 'male',
            'last_active' => now()->timestamp,
            'language' => 'en',
            'avatar' => null,
            'cover' => null,
            'verified' => false,
            'tips' => [],
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => 'user',
            'theme' => 'light',
            'publications_count' => 0,
            'followers_count' => 0,
            'following_count' => 0,
            'status' => $status,
            'type' => 'author',
        ]);
    }
}
