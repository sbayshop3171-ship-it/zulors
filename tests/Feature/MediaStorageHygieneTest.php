<?php

namespace Tests\Feature;

use App\Services\Media\Cloudflare\R2DirectUploadService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageHygieneTest extends TestCase
{
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
