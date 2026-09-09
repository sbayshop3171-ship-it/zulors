<?php

namespace Tests\Feature;

use App\Models\MediaPublication;
use App\Models\MediaPublicationItem;
use App\Services\Filesystem\FFMpeg\FFMpegService;
use App\Services\Media\Publication\MediaEncodingProfile;
use App\Services\Media\Publication\PublicationMediaProcessor;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PublicationMediaProcessorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ParallelTesting::resolveTokenUsing(fn () => 'publication-processor-'.getmypid());
        Storage::fake('local');
        Storage::fake('r2_temp');
        Storage::fake('r2_final');
        config(['media.publications.ffmpeg_threads' => 1, 'story.video_clip_size' => 60,
            'brand.images_watermark_enabled' => false, 'brand.videos_watermark_enabled' => false]);
    }

    public static function imageKinds(): array
    {
        return [
            'post' => ['post', 2048, 1024],
            'story' => ['story', 1080, 1920],
            'chat' => ['chat', 2048, 1024],
        ];
    }

    #[DataProvider('imageKinds')]
    public function test_images_are_real_webp_with_bounded_dimensions_and_raw_retained(string $kind, int $width, int $height): void
    {
        $item = $this->imageItem($kind, 2560, 1280);
        $before = $item->getAttributes();
        $output = app(PublicationMediaProcessor::class)->process($item);

        $this->assertSame('image/webp', $output['mime']);
        $this->assertSame('webp', $output['extension']);
        $this->assertSame($this->prefix($item).'/image.webp', $output['source_path']);
        $this->assertArtifact($output['source_path'], 'image/webp', $output['size']);
        $info = getimagesize(Storage::disk('r2_final')->path($output['source_path']));
        $this->assertSame([$width, $height], [$info[0], $info[1]]);
        $this->assertSame(['width' => $width, 'height' => $height], $output['metadata']['dimensions']);
        $this->assertSame($item->size, $output['metadata']['original_size']);
        $this->assertSame($output['size'], $output['metadata']['optimized_size']);
        $this->assertSame($before, $item->getAttributes());
        $this->assertRawRetained($item);

        $again = app(PublicationMediaProcessor::class)->process($item);
        $this->assertSame($output, $again);
        $this->assertCount(1, Storage::disk('r2_final')->allFiles());
    }

    public function test_image_orientation_is_applied_and_exif_is_stripped(): void
    {
        $item = $this->imageItem('post', 80, 40);
        $image = imagecreatefrompng(Storage::disk('r2_temp')->path($item->upload['path']));
        ob_start();
        imagejpeg($image);
        $jpeg = ob_get_clean();
        imagedestroy($image);
        $exif = "Exif\0\0II".pack('vVv', 42, 8, 1).pack('vvVv', 0x0112, 3, 1, 6)."\0\0".pack('V', 0);
        $comment = 'private-camera-location';
        $jpeg = substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif
            ."\xff\xfe".pack('n', strlen($comment) + 2).$comment.substr($jpeg, 2);
        Storage::disk('r2_temp')->put($item->upload['path'], $jpeg);
        $item->size = strlen($jpeg);

        $output = app(PublicationMediaProcessor::class)->process($item);
        $this->assertSame(['width' => 40, 'height' => 80], $output['metadata']['dimensions']);
        $bytes = Storage::disk('r2_final')->get($output['source_path']);
        $this->assertStringNotContainsString('EXIF', $bytes);
        $this->assertStringNotContainsString($comment, $bytes);
        $this->assertRawRetained($item);
    }

    #[DataProvider('imageKinds')]
    public function test_configured_image_watermarks_apply_after_sizing_for_all_kinds(string $kind, int $width, int $height): void
    {
        $this->makeWatermarkFixture();
        $item = $this->imageItem($kind, 2560, 1280);
        foreach ([false, true] as $enabled) {
            config(['brand.images_watermark_enabled' => $enabled]);
            $output = app(PublicationMediaProcessor::class)->process($item);
            $image = imagecreatefromwebp(Storage::disk('r2_final')->path($output['source_path']));
            $this->assertSame([$width, $height], [imagesx($image), imagesy($image)]);
            $color = imagecolorsforindex($image, imagecolorat($image, $width - 12, $height - 12));
            $enabled ? $this->assertGreaterThan(180, $color['green']) : $this->assertLessThan(60, $color['green']);
            imagedestroy($image);
        }
        $this->assertRawRetained($item);
    }

    public function test_gif_bytes_are_rejected_even_when_declared_as_png_for_all_kinds(): void
    {
        foreach (['post', 'story', 'chat'] as $kind) {
            $item = $this->imageItem($kind, 20, 20);
            $image = imagecreatefrompng(Storage::disk('r2_temp')->path($item->upload['path']));
            ob_start();
            imagegif($image);
            $gif = ob_get_clean();
            imagedestroy($image);
            Storage::disk('r2_temp')->put($item->upload['path'], $gif);
            $item->size = strlen($gif);
            $this->assertRejected($item, 'Unsupported or invalid publication image');
            $this->assertSame($gif, Storage::disk('r2_temp')->get($item->upload['path']));
        }
    }

    public function test_static_webp_bytes_remain_supported(): void
    {
        $item = $this->imageItem('post', 80, 40);
        $image = imagecreatefrompng(Storage::disk('r2_temp')->path($item->upload['path']));
        ob_start();
        imagewebp($image);
        $webp = ob_get_clean();
        imagedestroy($image);
        Storage::disk('r2_temp')->put($item->upload['path'], $webp);
        $item->size = strlen($webp);
        $item->mime = 'image/webp';
        $output = app(PublicationMediaProcessor::class)->process($item);
        $this->assertArtifact($output['source_path'], 'image/webp', $output['size']);
        $this->assertSame(['width' => 80, 'height' => 40], $output['metadata']['dimensions']);
        $this->assertRawRetained($item);
    }

    public static function animatedFormats(): array
    {
        return [['gif'], ['apng'], ['webp']];
    }

    #[DataProvider('animatedFormats')]
    public function test_real_animated_images_are_rejected_before_flattening(string $format): void
    {
        $path = 'raw/'.Str::uuid().'.'.$format;
        Storage::disk('r2_temp')->makeDirectory('raw');
        if ($format === 'webp') {
            $encoder = (new ExecutableFinder())->find('img2webp');
            if (! $encoder) {
                $this->markTestSkipped('img2webp is required for the animated WebP fixture.');
            }
            $first = $this->imageItem('post', 20, 20);
            $second = $this->imageItem('post', 20, 20);
            $image = imagecreatetruecolor(20, 20);
            imagepng($image, Storage::disk('r2_temp')->path($second->upload['path']));
            imagedestroy($image);
            $command = [$encoder, '-loop', '0', '-d', '100', Storage::disk('r2_temp')->path($first->upload['path']),
                '-d', '100', Storage::disk('r2_temp')->path($second->upload['path']), '-o', Storage::disk('r2_temp')->path($path)];
        } else {
            $encoder = (new ExecutableFinder())->find('ffmpeg');
            if (! $encoder) {
                $this->markTestSkipped('FFmpeg is required for animated image fixtures.');
            }
            $command = [$encoder, '-v', 'error', '-y', '-f', 'lavfi', '-i', 'testsrc2=size=20x20:rate=2',
                '-frames:v', '2', '-threads', '1', '-f', $format, Storage::disk('r2_temp')->path($path)];
        }
        (new Process($command))->setTimeout(30)->mustRun();
        $item = $this->item('post', 'image', $path);
        $item->name = 'claimed-still-image.png';
        $this->assertRejected($item, $format === 'gif' ? 'Unsupported or invalid publication image' : 'Animated images');
    }

    public function test_small_story_image_is_centered_on_canvas_without_upscaling(): void
    {
        $item = $this->imageItem('story', 80, 40);
        $output = app(PublicationMediaProcessor::class)->process($item);
        $image = imagecreatefromwebp(Storage::disk('r2_final')->path($output['source_path']));
        $outside = imagecolorsforindex($image, imagecolorat($image, 480, 960));
        $inside = imagecolorsforindex($image, imagecolorat($image, 515, 960));
        $this->assertLessThan(10, $outside['red']);
        $this->assertGreaterThan(180, $inside['red']);
        imagedestroy($image);
    }

    public function test_image_pixel_guard_rejects_a_bomb_header_before_decode(): void
    {
        $item = $this->imageItem('post', 20, 20);
        $bytes = Storage::disk('r2_temp')->get($item->upload['path']);
        $bytes = substr_replace($bytes, pack('NN', 10000, 5000), 16, 8);
        $bytes = substr_replace($bytes, pack('N', crc32(substr($bytes, 12, 17))), 29, 4);
        Storage::disk('r2_temp')->put($item->upload['path'], $bytes);
        $this->assertRejected($item, 'decoded pixel limit');
    }

    public function test_image_memory_guard_runs_before_decode(): void
    {
        $item = $this->imageItem('post', 100, 100);
        $item->publication->profile = array_replace($item->publication->profile, ['image_memory_bytes' => 1048576]);
        $this->assertRejected($item, 'decoded memory limit');
    }

    public function test_corrupt_image_is_never_published_as_the_original(): void
    {
        $item = $this->imageItem('post', 20, 20);
        Storage::disk('r2_temp')->put($item->upload['path'], str_repeat('x', $item->size));
        $this->assertRejected($item, 'invalid publication image');
    }

    public function test_declared_size_mismatch_and_size_limit_are_rejected(): void
    {
        $item = $this->imageItem('post', 20, 20);
        $item->size++;
        $this->assertRejected($item, 'does not match');
        $item->size--;
        $item->publication->profile = array_replace($item->publication->profile, ['max_image_bytes' => $item->size - 1]);
        $this->assertRejected($item, 'size limit');
    }

    public function test_stream_is_bounded_when_remote_object_grows_after_size_check(): void
    {
        $item = $this->imageItem('post', 20, 20);
        $disk = Storage::disk('r2_temp');
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $disk->get($item->upload['path']).str_repeat('x', 8192));
        rewind($stream);
        $mock = \Mockery::mock(FilesystemAdapter::class);
        $mock->shouldReceive('size')->once()->with($item->upload['path'])->andReturn($item->size);
        $mock->shouldReceive('readStream')->once()->with($item->upload['path'])->andReturn($stream);
        $mock->shouldNotReceive('delete');
        Storage::set('r2_temp', $mock);

        try {
            app(PublicationMediaProcessor::class)->process($item);
            $this->fail('A changing source must be rejected.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('does not match', $error->getMessage());
        } finally {
            Storage::set('r2_temp', $disk);
        }
        $this->assertFalse(is_resource($stream));
        $this->assertRawRetained($item);
        $this->assertSame([], Storage::disk('r2_final')->allFiles());
    }

    public static function videoKinds(): array
    {
        return [
            'post' => ['post', 320, 180],
            'story' => ['story', 1080, 1920],
            'chat' => ['chat', 720, 720],
        ];
    }

    #[DataProvider('videoKinds')]
    public function test_real_ffmpeg_optimizes_all_kinds_and_preserves_lower_fps(string $kind, int $width, int $height): void
    {
        $item = $this->videoItem($kind);
        $before = $item->getAttributes();
        $output = app(PublicationMediaProcessor::class)->process($item);
        $this->assertSame($this->prefix($item).'/optimized.mp4', $output['source_path']);
        $this->assertSame($this->prefix($item).'/poster.webp', $output['thumbnail_path']);
        $this->assertSame('video/mp4', $output['mime']);
        $this->assertSame('r2_final', $output['disk']);
        $this->assertSame('r2_final', $output['thumbnail_disk']);
        $this->assertArtifact($output['source_path'], 'video/mp4', $output['size']);
        $this->assertArtifact($output['thumbnail_path'], 'image/webp', $output['thumbnail_size']);
        $this->assertSame(['width' => $width, 'height' => $height], $output['metadata']['dimensions']);
        $this->assertEqualsWithDelta(12, $output['metadata']['fps'], 0.01);
        $this->assertEqualsWithDelta(2, $output['metadata']['duration_seconds'], 0.1);
        $this->assertSame($item->size, $output['metadata']['original_size']);
        $this->assertLessThan($item->size, $output['size']);
        $probe = app(FFMpegService::class)->getFFProbe();
        $path = Storage::disk('r2_final')->path($output['source_path']);
        $streams = $probe->streams($path);
        $this->assertSame('h264', $streams->videos()->first()->get('codec_name'));
        $this->assertSame('yuv420p', $streams->videos()->first()->get('pix_fmt'));
        $this->assertSame('aac', $streams->audios()->first()->get('codec_name'));
        $this->assertLessThanOrEqual(140000, (int) $streams->audios()->first()->get('bit_rate'));
        $this->assertStringNotContainsString('private-camera-location', (string) json_encode($probe->format($path)->all()));
        $this->assertFastStart($path);
        $this->assertSame($before, $item->getAttributes());
        $this->assertRawRetained($item);
    }

    public function test_high_fps_video_is_capped_and_video_without_audio_is_supported(): void
    {
        $item = $this->videoItem('post', 60, false);
        $output = app(PublicationMediaProcessor::class)->process($item);
        $this->assertEqualsWithDelta(30, $output['metadata']['fps'], 0.01);
        $this->assertRawRetained($item);
    }

    #[DataProvider('videoKinds')]
    public function test_video_watermark_policy_applies_only_to_posts_and_reaches_optimized_poster(string $kind, int $width, int $height): void
    {
        $this->makeWatermarkFixture();
        config(['brand.videos_watermark_enabled' => true]);
        $item = $this->videoItem($kind, 12, false, true);
        $output = app(PublicationMediaProcessor::class)->process($item);
        $framePath = Storage::disk('local')->path('verified-frame.png');
        app(FFMpegService::class)->getFFMpeg()->open(Storage::disk('r2_final')->path($output['source_path']))
            ->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(0))->save($framePath);
        $frame = imagecreatefrompng($framePath);
        $poster = imagecreatefromwebp(Storage::disk('r2_final')->path($output['thumbnail_path']));
        $this->assertSame([$width, $height], [imagesx($frame), imagesy($frame)]);
        foreach ([$frame, $poster] as $image) {
            $color = imagecolorsforindex($image, imagecolorat($image, 38, 48));
            $kind === 'post' ? $this->assertGreaterThan(180, $color['green']) : $this->assertLessThan(60, $color['green']);
            imagedestroy($image);
        }
        $this->assertRawRetained($item);
    }

    #[DataProvider('videoKinds')]
    public function test_image_watermark_flag_affects_only_post_video_thumbnails(string $kind, int $width, int $height): void
    {
        $this->makeWatermarkFixture();
        config(['brand.images_watermark_enabled' => true]);
        $item = $this->videoItem($kind, 12, false, true);
        $output = app(PublicationMediaProcessor::class)->process($item);
        $framePath = Storage::disk('local')->path('verified-frame.png');
        app(FFMpegService::class)->getFFMpeg()->open(Storage::disk('r2_final')->path($output['source_path']))
            ->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(0))->save($framePath);
        $frame = imagecreatefrompng($framePath);
        $color = imagecolorsforindex($frame, imagecolorat($frame, $width - 12, $height - 12));
        $this->assertLessThan(60, $color['green']);
        imagedestroy($frame);
        $poster = imagecreatefromwebp(Storage::disk('r2_final')->path($output['thumbnail_path']));
        $color = imagecolorsforindex($poster, imagecolorat($poster, $width - 12, $height - 12));
        $kind === 'post' ? $this->assertGreaterThan(180, $color['green']) : $this->assertLessThan(60, $color['green']);
        imagedestroy($poster);
        $this->assertRawRetained($item);
    }

    public function test_large_portrait_video_fits_the_target_without_distortion(): void
    {
        $item = $this->videoItem('post', 2, false, false, 1200, 2200);
        $output = app(PublicationMediaProcessor::class)->process($item);
        $dimensions = $output['metadata']['dimensions'];
        $this->assertLessThanOrEqual(1080, $dimensions['width']);
        $this->assertSame(1920, $dimensions['height']);
        $this->assertSame(0, $dimensions['width'] % 2);
        $this->assertEqualsWithDelta(1200 / 2200, $output['metadata']['aspect_ratio'], 0.001);
        $this->assertTrue($output['metadata']['is_portrait']);
        $this->assertRawRetained($item);
    }

    public function test_variable_frame_rate_bursts_are_capped_without_duplicating_slow_frames(): void
    {
        $binary = (new ExecutableFinder())->find('ffmpeg');
        $probe = (new ExecutableFinder())->find('ffprobe');
        if (! $binary || ! $probe) {
            $this->markTestSkipped('Real FFmpeg and FFprobe are required.');
        }
        $rawPath = 'raw/'.Str::uuid().'.mp4';
        Storage::disk('r2_temp')->makeDirectory('raw');
        (new Process([$binary, '-v', 'error', '-y', '-f', 'lavfi', '-i',
            'testsrc2=size=320x180:rate=60:duration=0.5[a];testsrc2=size=320x180:rate=6:duration=1.5[b];[a][b]concat=n=2:v=1:a=0',
            '-c:v', 'libx264', '-threads', '1', '-fps_mode', 'vfr', Storage::disk('r2_temp')->path($rawPath)]))
            ->setTimeout(30)->mustRun();
        $item = $this->item('post', 'video', $rawPath);
        $output = app(PublicationMediaProcessor::class)->process($item);
        $result = (new Process([$probe, '-v', 'error', '-select_streams', 'v:0', '-show_frames',
            '-show_entries', 'frame=best_effort_timestamp_time', '-of', 'json',
            Storage::disk('r2_final')->path($output['source_path'])]))->mustRun();
        $frames = json_decode($result->getOutput(), true, flags: JSON_THROW_ON_ERROR)['frames'];
        $this->assertLessThanOrEqual(39, count($frames));
        $this->assertGreaterThan(10, count($frames));
        for ($index = 1; $index < count($frames); $index++) {
            $interval = (float) $frames[$index]['best_effort_timestamp_time'] - (float) $frames[$index - 1]['best_effort_timestamp_time'];
            $this->assertGreaterThanOrEqual(1 / 30 - 0.001, $interval);
        }
        $this->assertRawRetained($item);
    }

    public function test_non_square_source_pixels_keep_display_aspect_ratio_without_upscaling(): void
    {
        $item = $this->videoItem('post', 12, false, false, 320, 180, '2/1');
        $output = app(PublicationMediaProcessor::class)->process($item);
        $this->assertEqualsWithDelta(640 / 180, $output['metadata']['aspect_ratio'], 0.05);
        $this->assertLessThanOrEqual(320, $output['metadata']['dimensions']['width']);
        $this->assertLessThanOrEqual(180, $output['metadata']['dimensions']['height']);
        $stream = app(FFMpegService::class)->getFFProbe()
            ->streams(Storage::disk('r2_final')->path($output['source_path']))->videos()->first();
        $this->assertSame('1:1', $stream->get('sample_aspect_ratio'));
        $this->assertRawRetained($item);
    }

    public function test_story_clip_and_poster_use_selected_segment_and_snapshot_maximum(): void
    {
        $item = $this->videoItem('story', 12, false, true);
        $item->publication->payload = ['clip_start_seconds' => 1, 'clip_duration_seconds' => 1];
        $item->publication->profile = array_replace($item->publication->profile, ['story_max_duration' => 1]);
        config(['story.video_clip_size' => 60]);
        $output = app(PublicationMediaProcessor::class)->process($item);
        $this->assertEqualsWithDelta(1, $output['metadata']['duration_seconds'], 0.1);
        $this->assertSame(1.0, $output['metadata']['clip_start_seconds']);
        $poster = imagecreatefromwebp(Storage::disk('r2_final')->path($output['thumbnail_path']));
        $center = imagecolorsforindex($poster, imagecolorat($poster, 540, 960));
        $outside = imagecolorsforindex($poster, imagecolorat($poster, 300, 960));
        $this->assertGreaterThan(180, $center['blue']);
        $this->assertLessThan(20, $center['red']);
        $this->assertLessThan(10, $outside['blue']);
        imagedestroy($poster);
        $this->assertRawRetained($item);
    }

    public function test_story_duration_is_clamped_to_snapshotted_maximum(): void
    {
        $item = $this->videoItem('story', 12, false);
        $item->publication->profile = array_replace($item->publication->profile, ['story_max_duration' => 1]);
        $output = app(PublicationMediaProcessor::class)->process($item);
        $this->assertEqualsWithDelta(1, $output['metadata']['duration_seconds'], 0.1);
        $this->assertEquals(1, $output['metadata']['clip_duration_seconds']);
    }

    public function test_video_limits_and_clip_are_checked_against_real_source(): void
    {
        $item = $this->videoItem('post', 12, false);
        $profile = $item->publication->profile;
        $item->publication->profile = array_replace($profile, ['max_video_bytes' => $item->size - 1]);
        $this->assertRejected($item, 'size limit');
        $item->publication->profile = array_replace($profile, ['max_video_duration' => 1]);
        $this->assertRejected($item, 'duration exceeds');
        $item->publication->profile = $profile;
        foreach ([['clip_start_seconds' => 3], ['clip_start_seconds' => -1], ['clip_duration_seconds' => 0],
            ['clip_start_seconds' => 1, 'clip_duration_seconds' => 2]] as $payload) {
            $item->publication->payload = $payload;
            $this->assertRejected($item, 'clip');
        }
    }

    public function test_repeated_video_processing_overwrites_same_generation_and_new_generation_is_separate(): void
    {
        $item = $this->videoItem('post', 12, false);
        $processor = app(PublicationMediaProcessor::class);
        $first = $processor->process($item);
        $second = $processor->process($item);
        $this->assertSame($first, $second);
        $this->assertCount(2, Storage::disk('r2_final')->allFiles());
        $item->generation++;
        $third = $processor->process($item);
        $this->assertNotSame($first['source_path'], $third['source_path']);
        $this->assertCount(4, Storage::disk('r2_final')->allFiles());
        $this->assertRawRetained($item);
    }

    public function test_failed_final_write_cleans_scratch_and_preserves_raw_for_retry(): void
    {
        $item = $this->imageItem('post', 20, 20);
        $disk = Storage::disk('r2_final');
        $mock = \Mockery::mock(FilesystemAdapter::class);
        $mock->shouldReceive('put')->once()->andReturn(false);
        Storage::set('r2_final', $mock);
        try {
            app(PublicationMediaProcessor::class)->process($item);
            $this->fail('A failed final write must fail processing.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Unable to store', $error->getMessage());
        } finally {
            Storage::set('r2_final', $disk);
        }
        $this->assertRawRetained($item);
        $this->assertSame([], $disk->allFiles());
        $output = app(PublicationMediaProcessor::class)->process($item);
        $this->assertArtifact($output['source_path'], 'image/webp', $output['size']);
    }

    public function test_snapshot_does_not_change_when_runtime_encoding_limits_change(): void
    {
        $profile = MediaEncodingProfile::defaults();
        config(['media.uploads.video.max_bytes' => 1, 'media.uploads.video.max_duration_seconds' => 1,
            'media.publications.image_max_pixels' => 100, 'story.video_clip_size' => 1]);
        $this->assertSame($profile, MediaEncodingProfile::resolve($profile));
        $this->assertSame(100, MediaEncodingProfile::defaults()['image_max_pixels']);
        $this->assertSame(84, $profile['image_quality']);
        $this->assertSame(24, $profile['video_crf']);
        $this->assertSame('veryfast', $profile['video_preset']);
    }

    public function test_shared_x264_factory_honors_explicit_legacy_settings(): void
    {
        $format = MediaEncodingProfile::x264('18', 'medium', 192, ['-vf', 'crop=720:720']);
        $parameters = [];
        foreach (array_chunk($format->getAdditionalParameters(), 2) as [$key, $value]) {
            $parameters[$key] = $value;
        }
        $this->assertSame('18', $parameters['-crf']);
        $this->assertSame('medium', $parameters['-preset']);
        $this->assertSame('crop=720:720', $parameters['-vf']);
        $this->assertSame('yuv420p', $parameters['-pix_fmt']);
        $this->assertSame('+faststart', $parameters['-movflags']);
        $this->assertArrayNotHasKey('-threads', $parameters);
        $this->assertArrayNotHasKey('-fps_mode', $parameters);
        $this->assertSame(192, $format->getAudioKiloBitrate());
        $this->assertSame('aac', $format->getAudioCodec());
        $this->assertSame('libx264', $format->getVideoCodec());
        $this->assertSame(0, $format->getKiloBitrate());
        $this->assertSame(1, $format->getPasses());
    }

    private function imageItem(string $kind, int $width, int $height): MediaPublicationItem
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD with WebP is required.');
        }
        $path = 'raw/'.Str::uuid().'.png';
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, (int) ($width / 2), $height, imagecolorallocate($image, 230, 30, 20));
        imagefilledrectangle($image, (int) ($width / 2), 0, $width, $height, imagecolorallocate($image, 20, 30, 230));
        Storage::disk('r2_temp')->makeDirectory('raw');
        imagepng($image, Storage::disk('r2_temp')->path($path));
        imagedestroy($image);

        return $this->item($kind, 'image', $path);
    }

    private function makeWatermarkFixture(): void
    {
        Storage::disk('local')->makeDirectory('watermarks');
        $this->app->usePublicPath(Storage::disk('local')->path('watermarks'));
        $image = imagecreatetruecolor(16, 16);
        imagefill($image, 0, 0, imagecolorallocate($image, 0, 255, 0));
        imagepng($image, public_path('marker.png'));
        imagedestroy($image);
        config(['assets.watermark.local_path' => 'marker.png',
            'assets.watermark.image' => ['position' => 'bottom-right', 'padding' => 4],
            'assets.watermark.video' => ['position' => 'absolute', 'x' => 30, 'y' => 40]]);
    }

    private function videoItem(string $kind, int $fps = 12, bool $audio = true, bool $twoColors = false,
        int $width = 320, int $height = 180, string $sar = '1/1'): MediaPublicationItem
    {
        $binary = (new ExecutableFinder())->find('ffmpeg');
        if (! $binary || ! (new ExecutableFinder())->find('ffprobe')) {
            $this->markTestSkipped('Real FFmpeg and FFprobe are required.');
        }
        $path = 'raw/'.Str::uuid().($sar === '1/1' ? '.avi' : '.mov');
        Storage::disk('r2_temp')->makeDirectory('raw');
        $source = $twoColors
            ? "color=red:size=320x180:rate={$fps}:duration=1[r];color=blue:size=320x180:rate={$fps}:duration=1[b];[r][b]concat=n=2:v=1:a=0"
            : "testsrc2=size={$width}x{$height}:rate={$fps},setsar={$sar}";
        $command = [$binary, '-v', 'error', '-y', '-f', 'lavfi', '-i', $source];
        if ($audio) {
            array_push($command, '-f', 'lavfi', '-i', 'sine=frequency=440:sample_rate=44100', '-c:a', 'pcm_s16le');
        }
        array_push($command, '-t', '2', '-c:v', $sar === '1/1' ? 'rawvideo' : 'libx264', '-pix_fmt', 'yuv420p', '-threads', '1',
            '-metadata', 'comment=private-camera-location', Storage::disk('r2_temp')->path($path));
        (new Process($command))->setTimeout(30)->mustRun();

        return $this->item($kind, 'video', $path);
    }

    private function item(string $kind, string $type, string $path): MediaPublicationItem
    {
        $publication = new MediaPublication([
            'id' => (string) Str::uuid(), 'kind' => $kind, 'status' => 'processing',
            'profile' => MediaEncodingProfile::defaults(), 'payload' => [],
        ]);
        $item = new MediaPublicationItem([
            'id' => (string) Str::uuid(), 'publication_id' => $publication->id, 'type' => $type,
            'name' => basename($path), 'mime' => $type === 'image' ? 'image/png' : 'video/x-msvideo',
            'size' => Storage::disk('r2_temp')->size($path), 'generation' => 1, 'status' => 'processing',
            'upload' => ['path' => $path, 'disk' => 'r2_temp', 'final_disk' => 'r2_final'],
            'metadata' => ['duration_seconds' => 0.1, 'width' => 1, 'height' => 1],
        ]);

        return $item->setRelation('publication', $publication);
    }

    private function prefix(MediaPublicationItem $item): string
    {
        return "uploads/publications/{$item->publication_id}/{$item->id}/{$item->generation}";
    }

    private function assertArtifact(string $path, string $mime, int $size): void
    {
        $disk = Storage::disk('r2_final');
        $disk->assertExists($path);
        $this->assertGreaterThan(0, $size);
        $this->assertSame($size, $disk->size($path));
        $this->assertSame($mime, (new \finfo(FILEINFO_MIME_TYPE))->file($disk->path($path)));
    }

    private function assertRawRetained(MediaPublicationItem $item): void
    {
        Storage::disk('r2_temp')->assertExists($item->upload['path']);
        $this->assertSame([], Storage::disk('local')->allFiles('tmp/publications'));
        $this->assertSame([], Storage::disk('local')->allDirectories('tmp/publications'));
        $this->assertSame('processing', $item->status);
        $this->assertSame('processing', $item->publication->status);
    }

    private function assertRejected(MediaPublicationItem $item, string $message): void
    {
        try {
            app(PublicationMediaProcessor::class)->process($item);
            $this->fail('Invalid media must be rejected.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString($message, $error->getMessage());
        }
        $this->assertRawRetained($item);
        $this->assertSame([], Storage::disk('r2_final')->allFiles());
    }

    private function assertFastStart(string $path): void
    {
        $bytes = file_get_contents($path);
        $atoms = [];
        for ($offset = 0; $offset + 8 <= strlen($bytes);) {
            $header = unpack('Nsize/a4type', substr($bytes, $offset, 8));
            $atoms[] = $header['type'];
            if ($header['size'] < 8) {
                break;
            }
            $offset += $header['size'];
        }
        $this->assertContains('moov', $atoms);
        $this->assertContains('mdat', $atoms);
        $this->assertLessThan(array_search('mdat', $atoms, true), array_search('moov', $atoms, true));
    }
}
