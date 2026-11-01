<?php

namespace Tests\Feature;

use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Enums\Post\PostStatus;
use App\Enums\Post\PostType;
use App\Enums\User\UserStatus;
use App\Http\Resources\User\Media\MediaResource;
use App\Jobs\User\Timeline\GeneratePostVideoHls;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Services\Filesystem\Upload\VideoUploadService;
use App\Services\Media\ReelsHlsRollout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ReelsHlsProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_r2_hls_url_is_only_exposed_to_the_enabled_allowlist_and_internal_signature_is_hidden(): void
    {
        Storage::fake('r2_final');
        config(['media.reels_hls.enabled' => true, 'media.reels_hls.user_ids' => []]);
        $owner = $this->user();
        $post = $this->createPost($owner);
        $media = $this->video($post, [
            'playback_state' => 'ready',
            'background_processing_state' => 'processed',
            'playback' => [
                'hls' => 'https://cdn.example.test/master.m3u8',
                'hls_state' => 'ready',
                'hls_source_signature' => 'private-signature',
                'renditions' => [['height' => 360, 'state' => 'ready']],
            ],
        ]);

        $viewer = $this->user();
        $hidden = $this->resourceFor($media, $viewer);
        $this->assertStringContainsString('uploads/posts/source.mp4', $hidden['source_url']);
        $this->assertArrayNotHasKey('hls', $hidden['metadata']['playback']);
        $this->assertArrayNotHasKey('hls_source_signature', $hidden['metadata']['playback']);
        $this->assertSame('ready', $hidden['metadata']['playback_state']);
        $this->assertSame('processed', $hidden['metadata']['background_processing_state']);
        $this->assertSame('ready', $hidden['metadata']['playback']['renditions'][0]['state']);

        config(['media.reels_hls.user_ids' => [(string) $viewer->id]]);
        $visible = $this->resourceFor($media, $viewer);
        $this->assertSame('https://cdn.example.test/master.m3u8', $visible['metadata']['playback']['hls']);
        $this->assertArrayNotHasKey('hls_source_signature', $visible['metadata']['playback']);

        config(['media.reels_hls.user_ids' => ['*']]);
        $wildcardVisible = $this->resourceFor($media, $this->user());
        $this->assertSame('https://cdn.example.test/master.m3u8', $wildcardVisible['metadata']['playback']['hls']);

        config(['media.reels_hls.enabled' => false]);
        $disabled = $this->resourceFor($media, $viewer);
        $this->assertArrayNotHasKey('hls', $disabled['metadata']['playback']);
    }

    public function test_cloudflare_stream_playback_is_unchanged_by_the_r2_hls_rollout_gate(): void
    {
        Storage::fake('r2_final');
        config(['media.reels_hls.enabled' => false, 'media.reels_hls.user_ids' => []]);
        $owner = $this->user();
        $post = $this->createPost($owner);
        $media = $this->video($post, [
            'provider' => 'cloudflare_stream',
            'playback' => ['hls' => 'https://videodelivery.net/uid/manifest/video.m3u8'],
        ]);

        $resource = $this->resourceFor($media, $owner);
        $this->assertSame('https://videodelivery.net/uid/manifest/video.m3u8', $resource['metadata']['playback']['hls']);
    }

    public function test_low_resolution_r2_source_is_marked_skipped_while_mp4_stays_playback_ready(): void
    {
        Event::fake();
        Storage::fake('r2_final');
        Storage::disk('r2_final')->put('uploads/posts/source.mp4', 'original mp4');
        config(['media.reels_hls.enabled' => true]);
        $media = $this->video($this->createPost($this->user()), [
            'provider' => 'r2',
            'playback_state' => 'ready',
            'background_processing_state' => 'queued',
            'background_processing_dispatched_at' => now()->subMinute()->toIso8601String(),
        ]);

        $probe = Mockery::mock(VideoUploadService::class);
        $probe->shouldReceive('getVideoDimensions')->once()->andReturn(['width' => 320, 'height' => 180]);

        (new GeneratePostVideoHls((int) $media->id))->handle($probe, app(ReelsHlsRollout::class));

        $media->refresh();
        $this->assertSame(MediaStatus::PROCESSED, $media->status);
        $this->assertSame('uploads/posts/source.mp4', $media->source_path);
        $this->assertSame('ready', data_get($media->metadata, 'playback_state'));
        $this->assertSame('skipped', data_get($media->metadata, 'background_processing_state'));
        $this->assertSame('skipped', data_get($media->metadata, 'playback.hls_state'));
        $this->assertNull(data_get($media->metadata, 'playback.hls'));
        Storage::disk('r2_final')->assertExists('uploads/posts/source.mp4');
    }

    public function test_terminal_encoding_failure_keeps_mp4_ready_and_records_a_retryable_background_failure(): void
    {
        Event::fake();
        Storage::fake('r2_final');
        Storage::disk('r2_final')->put('uploads/posts/source.mp4', 'original mp4');
        config(['media.reels_hls.enabled' => true, 'ffmpeg.ffmpeg_path' => '/missing/ffmpeg']);
        $media = $this->video($this->createPost($this->user()), [
            'provider' => 'r2',
            'playback_state' => 'ready',
            'background_processing_state' => 'queued',
            'background_processing_dispatched_at' => now()->subMinute()->toIso8601String(),
        ]);

        $probe = Mockery::mock(VideoUploadService::class);
        $probe->shouldReceive('getVideoDimensions')->once()->andReturn(['width' => 640, 'height' => 360]);
        $job = new GeneratePostVideoHls((int) $media->id);
        try {
            $job->handle($probe, app(ReelsHlsRollout::class));
            $this->fail('Encoding should fail when FFmpeg is unavailable.');
        } catch (RuntimeException $exception) {
            $job->failed($exception);
        }

        $media->refresh();
        $this->assertSame('ready', data_get($media->metadata, 'playback_state'));
        $this->assertSame('failed', data_get($media->metadata, 'background_processing_state'));
        $this->assertNotEmpty(data_get($media->metadata, 'background_processing_error'));
        $this->assertNull(data_get($media->metadata, 'playback.hls'));
        $this->assertSame('uploads/posts/source.mp4', $media->source_path);
        Storage::disk('r2_final')->assertExists('uploads/posts/source.mp4');
        $this->assertSame([30, 120, 600, 1800], $job->backoff());
    }

    public function test_stale_processing_video_is_requeued_without_changing_its_mp4_source(): void
    {
        Queue::fake();
        Storage::fake('r2_final');
        config(['media.reels_hls.enabled' => true]);
        $media = $this->video($this->createPost($this->user()), [
            'provider' => 'r2',
            'playback_state' => 'ready',
            'background_processing_state' => 'processing',
            'background_processing_updated_at' => now()->subHours(3)->toIso8601String(),
            'background_processing_dispatched_at' => now()->subHours(3)->toIso8601String(),
            'playback' => ['hls' => null, 'hls_state' => 'processing'],
        ]);

        $this->artisan('media:reconcile-reel-hls')->assertSuccessful();

        $media->refresh();
        $this->assertSame('uploads/posts/source.mp4', $media->source_path);
        $this->assertSame('ready', data_get($media->metadata, 'playback_state'));
        $this->assertSame('queued', data_get($media->metadata, 'background_processing_state'));
        Queue::assertPushed(GeneratePostVideoHls::class, fn (GeneratePostVideoHls $job): bool => $job->mediaId === (int) $media->id);
    }

    public function test_performance_playback_metrics_discard_identifying_fields(): void
    {
        $logger = Mockery::mock();
        $logger->shouldReceive('info')->once()->with('client_performance_batch', Mockery::on(function (array $context): bool {
            $event = $context['events'][0] ?? [];

            return ($event['metric'] ?? null) === 'media_first_frame'
                && ($event['network'] ?? null) === '3g'
                && ! array_key_exists('user_id', $event)
                && ! array_key_exists('post_id', $event)
                && ! array_key_exists('device_id', $event)
                && ! array_key_exists('media_url', $event);
        }));
        Log::shouldReceive('channel')->once()->with('performance')->andReturn($logger);

        $this->postJson('/api/performance/events', [
            'events' => [[
                'metric' => 'media_first_frame',
                'value' => 935,
                'category' => 'explore',
                'network' => '3g',
                'user_id' => 42,
                'post_id' => 81,
                'device_id' => 'persistent-id',
                'media_url' => 'https://cdn.example.test/private.mp4',
            ]],
        ])->assertNoContent();
    }

    private function resourceFor(Media $media, User $viewer): array
    {
        $request = Request::create('/api/timeline', 'GET');
        $request->setUserResolver(fn () => $viewer);

        return (new MediaResource($media))->toArray($request);
    }

    private function video(Post $post, array $metadata = []): Media
    {
        Storage::disk('r2_final')->put('uploads/posts/source.mp4', 'original mp4');

        return $post->media()->create([
            'type' => MediaType::VIDEO,
            'status' => MediaStatus::PROCESSED,
            'source_path' => 'uploads/posts/source.mp4',
            'disk' => 'r2_final',
            'mime' => 'video/mp4',
            'extension' => 'mp4',
            'size' => Storage::disk('r2_final')->size('uploads/posts/source.mp4'),
            'metadata' => array_merge(['provider' => 'r2'], $metadata),
        ]);
    }

    private function createPost(User $user): Post
    {
        return Post::create([
            'user_id' => $user->id,
            'content' => 'Reel test',
            'status' => PostStatus::ACTIVE,
            'type' => PostType::TEXT,
            'text_language' => 'en',
            'comments_count' => 0,
        ]);
    }

    private function user(): User
    {
        $username = 'reel_hls_'.Str::lower(Str::random(10));

        return User::create([
            'first_name' => 'Reel', 'last_name' => 'Tester', 'username' => $username,
            'caption' => '@'.$username, 'email' => $username.'@example.com', 'password' => bcrypt('password'),
            'phone' => '', 'website' => '', 'bio' => '', 'gender' => 'male', 'language' => 'en',
            'last_active' => now()->timestamp, 'tips' => [], 'email_verified_at' => now(),
            'role' => 'user', 'theme' => 'light', 'status' => UserStatus::ACTIVE, 'type' => 'author',
            'publications_count' => 0, 'followers_count' => 0, 'following_count' => 0,
        ]);
    }
}
