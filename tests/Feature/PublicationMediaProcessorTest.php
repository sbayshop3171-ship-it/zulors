<?php

namespace Tests\Feature;

use App\Models\MediaPublication;
use App\Models\MediaPublicationItem;
use App\Services\Media\Cloudflare\R2DirectUploadService;
use App\Services\Media\Publication\PublicationMediaProcessor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class PublicationMediaProcessorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('r2_temp');
        Storage::fake('r2_final');
    }

    public function test_images_are_promoted_unchanged_without_reading_media_bytes_in_the_processor(): void
    {
        $item = $this->item('post', 'image', 'image/jpeg', 'camera.jpg', 'original-jpeg-bytes');
        $outputPath = $this->targetPath($item, 'jpg');
        $r2 = $this->mock(R2DirectUploadService::class);
        $r2->shouldReceive('publishUploadedObject')->once()
            ->with($item->upload['path'], $this->targetPrefix($item), 'jpg', 'image/jpeg', 'r2_temp')
            ->andReturn(['disk' => 'r2_final', 'path' => $outputPath, 'size' => $item->size]);

        $output = app(PublicationMediaProcessor::class)->process($item);

        $this->assertSame('image/jpeg', $output['mime']);
        $this->assertSame('jpg', $output['extension']);
        $this->assertSame($item->size, $output['size']);
        $this->assertSame('r2_final', $output['disk']);
        $this->assertSame('original_direct', $output['metadata']['processing_mode']);
        $this->assertNull($output['metadata']['optimized_size']);
        Storage::disk('r2_temp')->assertExists($item->upload['path']);
    }

    public function test_long_video_is_promoted_unchanged_and_keeps_declared_presentation_metadata(): void
    {
        $item = $this->item('post', 'video', 'video/mp4', 'one-hour.mp4', str_repeat('v', 1024), [
            'duration_seconds' => 3600, 'width' => 1920, 'height' => 1080,
        ]);
        $outputPath = $this->targetPath($item, 'mp4');
        $r2 = $this->mock(R2DirectUploadService::class);
        $r2->shouldReceive('publishUploadedObject')->once()
            ->with($item->upload['path'], $this->targetPrefix($item), 'mp4', 'video/mp4', 'r2_temp')
            ->andReturn(['disk' => 'r2_final', 'path' => $outputPath, 'size' => $item->size]);

        $output = app(PublicationMediaProcessor::class)->process($item);

        $this->assertSame('video/mp4', $output['mime']);
        $this->assertSame(1024, $output['size']);
        $this->assertSame(3600.0, $output['metadata']['duration_seconds']);
        $this->assertSame(['width' => 1920, 'height' => 1080], $output['metadata']['dimensions']);
        $this->assertSame('skipped', $output['metadata']['background_processing_state']);
        $this->assertNull($output['metadata']['optimized_size']);
    }

    public function test_documents_remain_byte_for_byte_unchanged(): void
    {
        $bytes = "%PDF-1.7\nsource document";
        $item = $this->item('chat', 'document', 'application/pdf', 'attachment.pdf', $bytes);
        $outputPath = $this->targetPath($item, 'pdf');
        $r2 = $this->mock(R2DirectUploadService::class);
        $r2->shouldReceive('publishUploadedObject')->once()
            ->with($item->upload['path'], $this->targetPrefix($item), 'pdf', 'application/pdf', 'r2_temp')
            ->andReturn(['disk' => 'r2_final', 'path' => $outputPath, 'size' => strlen($bytes)]);

        $output = app(PublicationMediaProcessor::class)->process($item);

        $this->assertSame('application/pdf', $output['mime']);
        $this->assertSame(strlen($bytes), $output['size']);
        $this->assertSame('original_direct', $output['metadata']['processing_mode']);
    }

    public function test_invalid_size_or_mime_is_rejected_before_r2_promotion(): void
    {
        $item = $this->item('post', 'video', 'video/mp4', 'bad.mp4', 'source');
        $r2 = $this->mock(R2DirectUploadService::class);
        $r2->shouldNotReceive('publishUploadedObject');

        $item->size++;
        try {
            app(PublicationMediaProcessor::class)->process($item);
            $this->fail('A mismatched declared size must be rejected.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('does not match', $error->getMessage());
        }

        $item->size--;
        $item->mime = 'application/octet-stream';
        try {
            app(PublicationMediaProcessor::class)->process($item);
            $this->fail('A disallowed declared media type must be rejected.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Unsupported publication media type', $error->getMessage());
        }
    }

    private function item(string $kind, string $type, string $mime, string $name, string $bytes, array $metadata = []): MediaPublicationItem
    {
        $publicationId = (string) Str::uuid();
        $itemId = (string) Str::uuid();
        $path = 'tmp/direct/media/'.$itemId.'.'.pathinfo($name, PATHINFO_EXTENSION);
        Storage::disk('r2_temp')->put($path, $bytes);
        $publication = new MediaPublication(['id' => $publicationId, 'kind' => $kind, 'status' => 'processing']);
        $item = new MediaPublicationItem([
            'id' => $itemId, 'publication_id' => $publicationId, 'type' => $type,
            'name' => $name, 'mime' => $mime, 'size' => strlen($bytes), 'generation' => 1,
            'status' => 'uploaded', 'metadata' => $metadata,
            'upload' => ['path' => $path, 'disk' => 'r2_temp'],
        ]);
        $item->setRelation('publication', $publication);

        return $item;
    }

    private function targetPrefix(MediaPublicationItem $item): string
    {
        return "uploads/publications/{$item->publication_id}/{$item->id}/{$item->generation}";
    }

    private function targetPath(MediaPublicationItem $item, string $extension): string
    {
        return $this->targetPrefix($item).'/'.hash('sha256', 'r2_temp|'.config('filesystems.disks.r2_temp.bucket').'|'.$item->upload['path']).'.'.$extension;
    }
}
