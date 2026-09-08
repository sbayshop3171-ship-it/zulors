<?php

namespace Tests\Feature;

use App\Services\Media\Cloudflare\R2DirectUploadService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageHygieneTest extends TestCase
{
    public function test_lifecycle_can_be_previewed_and_applied_while_direct_uploads_are_disabled(): void
    {
        $client = \Mockery::mock(\Aws\S3\S3Client::class);
        $unrelated = ['ID' => 'existing-policy', 'Status' => 'Enabled', 'Filter' => ['Prefix' => 'old/'], 'Expiration' => ['Days' => 7]];
        $client->shouldReceive('getBucketLifecycleConfiguration')->twice()->with(['Bucket' => 'media-temp'])
            ->andReturn(new \Aws\Result(['Rules' => [$unrelated]]));
        $service = $this->lifecycleService($client);
        $this->assertFalse($service->isConfigured());
        $rules = $service->configureTempLifecycle(3, false);
        $this->assertSame($unrelated, $rules[0]);
        $this->assertSame(3, $rules[1]['Expiration']['Days']);
        $this->assertSame(1, $rules[1]['AbortIncompleteMultipartUpload']['DaysAfterInitiation']);
        $client->shouldReceive('putBucketLifecycleConfiguration')->once()
            ->with(['Bucket' => 'media-temp', 'LifecycleConfiguration' => ['Rules' => $rules]])->andReturn(new \Aws\Result());
        $this->assertSame($rules, $service->configureTempLifecycle(3, true));
        $this->assertFalse(config('media.cloudflare.r2.direct_upload_enabled'));
    }

    public function test_lifecycle_permission_failure_does_not_replace_unknown_rules(): void
    {
        $client = \Mockery::mock(\Aws\S3\S3Client::class);
        $client->shouldReceive('getBucketLifecycleConfiguration')->once()->andThrow(
            new \Aws\S3\Exception\S3Exception('Access denied', new \Aws\Command('GetBucketLifecycleConfiguration'), ['code' => 'AccessDenied'])
        );
        $client->shouldNotReceive('putBucketLifecycleConfiguration');
        $service = $this->lifecycleService($client);
        $this->expectException(\Aws\S3\Exception\S3Exception::class);
        $service->configureTempLifecycle(3, true);
    }

    public function test_lifecycle_cannot_expire_the_final_bucket(): void
    {
        $client = \Mockery::mock(\Aws\S3\S3Client::class);
        $client->shouldNotReceive('getBucketLifecycleConfiguration');
        $service = $this->lifecycleService($client);
        config(['filesystems.disks.r2_temp.bucket' => 'media-final']);
        $this->expectExceptionMessage('Separate R2 temp and final buckets must be configured.');
        $service->configureTempLifecycle(3, true);
    }

    private function lifecycleService(\Aws\S3\S3Client $client): R2DirectUploadService
    {
        config(['media.cloudflare.r2.direct_upload_enabled' => false,
            'media.cloudflare.r2.temp_disk' => 'r2_temp', 'media.cloudflare.r2.final_disk' => 'r2_final']);
        foreach(['r2_temp' => 'media-temp', 'r2_final' => 'media-final'] as $disk => $bucket) {
            config(["filesystems.disks.{$disk}" => ['enabled' => true, 'bucket' => $bucket,
                'endpoint' => 'https://r2.example.test', 'key' => 'test-key', 'secret' => 'test-secret']]);
        }
        return new class($client) extends R2DirectUploadService {
            public function __construct(private \Aws\S3\S3Client $client) {}
            protected function s3Client(string $disk): \Aws\S3\S3Client { return $this->client; }
        };
    }

    public function test_cleanup_removes_only_stale_temp_objects_and_aborts_multipart(): void
    {
        Storage::fake('local');
        Storage::fake('r2_temp');
        Storage::fake('r2_final');
        config(['filesystems.disks.r2_temp.enabled' => true, 'media.cloudflare.r2.temp_disk' => 'r2_temp',
            'media.cloudflare.r2.temp_prefix' => 'tmp/direct/videos']);
        $old = 'tmp/direct/videos/old.mp4';
        $new = 'tmp/direct/videos/new.mp4';
        Storage::disk('r2_temp')->put($old, 'old');
        Storage::disk('r2_temp')->put($new, 'new');
        Storage::disk('r2_final')->put($old, 'final');
        touch(Storage::disk('r2_temp')->path($old), now()->subDays(4)->timestamp);
        $this->mock(R2DirectUploadService::class, function ($mock) {
            $mock->shouldReceive('abortStaleMultipartUploads')->once()
                ->with('r2_temp', 'tmp/direct/videos', \Mockery::type('int'))->andReturn(1);
        });
        $this->artisan('media:cleanup-temp', ['--hours' => 72])->assertSuccessful();
        Storage::disk('r2_temp')->assertMissing($old);
        Storage::disk('r2_temp')->assertExists($new);
        Storage::disk('r2_final')->assertExists($old);
    }

    public function test_uploaded_object_size_must_match_and_raw_publication_is_disabled(): void
    {
        Storage::fake('r2_temp');
        Storage::disk('r2_temp')->put('sample', 'video');
        $service = new R2DirectUploadService();
        $this->assertSame(5, $service->verifyUploadedVideo('sample', 'r2_temp', 5));
        try {
            $service->verifyUploadedVideo('sample', 'r2_temp', 6);
            $this->fail('Mismatched upload must be rejected.');
        }
        catch (\Exception $e) {
            $this->assertStringContainsString('size does not match', $e->getMessage());
        }
        $this->expectException(\LogicException::class);
        $service->publishUploadedVideo('sample');
    }
}
