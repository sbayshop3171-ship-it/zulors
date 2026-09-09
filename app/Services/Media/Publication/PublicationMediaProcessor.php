<?php

namespace App\Services\Media\Publication;

use App\Models\MediaPublicationItem;
use App\Services\Filesystem\FFMpeg\FFMpegService;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Media\Video;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;

class PublicationMediaProcessor
{
    public function process(MediaPublicationItem $item): array
    {
        $publication = $item->publication;
        $kind = $publication?->kind;
        if (! in_array($kind, ['post', 'story', 'chat'], true) || ! in_array($item->type, ['image', 'video'], true)) {
            throw new RuntimeException('Unsupported publication media kind or type.');
        }

        $profile = MediaEncodingProfile::resolve($publication->profile ?? []);
        $upload = $item->upload ?? [];
        $sourceDisk = $upload['disk'] ?? null;
        $sourcePath = $upload['path'] ?? null;
        $finalDisk = $upload['final_disk'] ?? null;
        if (! is_string($sourceDisk) || $sourceDisk === '' || ! is_string($sourcePath) || $sourcePath === ''
            || ! is_string($finalDisk) || $finalDisk === '') {
            throw new RuntimeException('Publication media storage descriptor is incomplete.');
        }

        $prefix = $this->finalPrefix($publication->getKey(), $item->getKey(), $item->generation);
        if ($sourceDisk === $finalDisk && str_starts_with($sourcePath, $prefix.'/')) {
            throw new RuntimeException('Publication output must not overwrite its raw source.');
        }

        $limit = $profile[$item->type === 'image' ? 'max_image_bytes' : 'max_video_bytes'];
        $size = (int) $item->size;
        if ($size < 1 || $size > $limit) {
            throw new RuntimeException('Publication media exceeds the upload size limit or is empty.');
        }
        if (Storage::disk($sourceDisk)->size($sourcePath) !== $size) {
            throw new RuntimeException('Uploaded media size does not match the declared size.');
        }

        $scratch = 'tmp/publications/'.Str::uuid();
        $local = Storage::disk('local');
        if (! $local->makeDirectory($scratch)) {
            throw new RuntimeException('Unable to create publication media scratch directory.');
        }

        try {
            $source = $local->path($scratch.'/source');
            $this->copySource($sourceDisk, $sourcePath, $source, $size);
            $artifacts = $item->type === 'image'
                ? $this->processImage($source, $local->path($scratch), $kind, $profile)
                : $this->processVideo($source, $local->path($scratch), $kind, $publication->payload ?? [], $profile);

            $extension = $item->type === 'image' ? 'webp' : 'mp4';
            $mime = $item->type === 'image' ? 'image/webp' : 'video/mp4';
            $path = $prefix.($item->type === 'image' ? '/image.webp' : '/optimized.mp4');
            $optimizedSize = $this->storeArtifact($finalDisk, $path, $artifacts['media'], $mime);
            $output = [
                'source_path' => $path,
                'disk' => $finalDisk,
                'extension' => $extension,
                'mime' => $mime,
                'size' => $optimizedSize,
                'metadata' => array_merge($artifacts['metadata'], [
                    'provider' => 'r2',
                    'encoding_profile' => $profile['name'],
                    'original_size' => $size,
                    'optimized_size' => $optimizedSize,
                    'optimization_ratio' => max(0, min(100, (int) round((1 - $optimizedSize / $size) * 100))),
                    'processing_state' => 'processed',
                    'processing_progress' => 100,
                ]),
            ];

            if (isset($artifacts['poster'])) {
                $output['thumbnail_path'] = $prefix.'/poster.webp';
                $output['thumbnail_disk'] = $finalDisk;
                $output['thumbnail_size'] = $this->storeArtifact($finalDisk, $output['thumbnail_path'], $artifacts['poster'], 'image/webp');
            }

            return $output;
        } finally {
            // Raw storage and item state belong to the parent transaction and cleanup worker.
            $local->deleteDirectory($scratch);
        }
    }

    private function finalPrefix(mixed $publicationId, mixed $itemId, mixed $generation): string
    {
        foreach ([$publicationId, $itemId, $generation] as $segment) {
            if (! is_scalar($segment) || ! preg_match('/\A[a-zA-Z0-9_-]+\z/', (string) $segment)) {
                throw new RuntimeException('Invalid publication media output identity.');
            }
        }

        return "uploads/publications/{$publicationId}/{$itemId}/{$generation}";
    }

    private function copySource(string $disk, string $path, string $destination, int $size): void
    {
        $input = Storage::disk($disk)->readStream($path);
        if (! is_resource($input)) {
            throw new RuntimeException('Unable to read uploaded publication media.');
        }

        $output = null;
        try {
            $output = fopen($destination, 'xb');
            if (! is_resource($output)) {
                throw new RuntimeException('Unable to open publication media scratch file.');
            }
            // Bound the copy even if the object changed after its size was checked.
            $copied = stream_copy_to_stream($input, $output, $size + 1);
            if ($copied !== $size || ! fflush($output) || fstat($output)['size'] !== $size) {
                throw new RuntimeException('Uploaded media size does not match the declared size.');
            }
        } finally {
            fclose($input);
            if (is_resource($output)) {
                fclose($output);
            }
        }
    }

    private function processImage(string $source, string $scratch, string $kind, array $profile): array
    {
        $this->guardImage($source, $profile);
        $manager = $this->imageManager();
        $image = $manager->read($source);
        $originalDimensions = ['width' => $image->width(), 'height' => $image->height()];
        $edge = $profile['image_max_edge'];
        if ($kind === 'story') {
            $image->scaleDown(min(1080, $edge), min(1920, $edge));
        } else {
            $image->scaleDown($edge, $edge);
        }
        $image = $this->watermarkImage($image, $profile);
        $path = $scratch.'/media.webp';
        $image->toWebp($profile['image_quality'])->save($path);

        return [
            'media' => $path,
            'metadata' => array_merge($this->presentation($image->width(), $image->height()), [
                'original_dimensions' => $originalDimensions,
            ]),
        ];
    }

    private function imageManager(): ImageManager
    {
        return new ImageManager(new Driver(), autoOrientation: true, decodeAnimation: false, strip: true);
    }

    private function guardImage(string $path, array $profile): void
    {
        $info = @getimagesize($path);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException('Unsupported or invalid publication image.');
        }

        $pixels = (float) $info[0] * (float) $info[1];
        if ($pixels < 1 || $pixels > $profile['image_max_pixels']) {
            throw new RuntimeException('Publication image exceeds the decoded pixel limit.');
        }
        $this->rejectAnimation($path, $info[2]);

        // Account for decode, orientation, resampling, canvas, and encoder buffers before GD allocates.
        $estimate = $pixels * 12 + filesize($path) * 2 + 1080 * 1920 * 8 + 16777216;
        $memoryLimit = ini_parse_quantity(ini_get('memory_limit'));
        $available = $memoryLimit > 0 ? max(0, $memoryLimit - memory_get_usage(true)) : PHP_INT_MAX;
        if ($estimate > min($profile['image_memory_bytes'], $available)) {
            throw new RuntimeException('Publication image exceeds the decoded memory limit.');
        }
    }

    private function rejectAnimation(string $path, int $type): void
    {
        if (! in_array($type, [IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return;
        }
        $stream = fopen($path, 'rb');
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to inspect publication image.');
        }
        try {
            $size = filesize($path);
            $offset = $type === IMAGETYPE_PNG ? 8 : 12;
            // Inspect container chunks without decoding or allocating animation frames.
            while ($offset + 8 <= $size) {
                if (fseek($stream, $offset) !== 0 || strlen($header = fread($stream, 8)) !== 8) {
                    throw new RuntimeException('Invalid publication image container.');
                }
                $chunk = unpack($type === IMAGETYPE_PNG ? 'Nlength/a4type' : 'a4type/Vlength', $header);
                $padding = $type === IMAGETYPE_PNG ? 4 : $chunk['length'] % 2;
                $offset += 8 + $chunk['length'] + $padding;
                if ($offset > $size) {
                    throw new RuntimeException('Invalid publication image container.');
                }
                if (($type === IMAGETYPE_PNG && in_array($chunk['type'], ['acTL', 'fcTL', 'fdAT'], true))
                    || ($type === IMAGETYPE_WEBP && (in_array($chunk['type'], ['ANIM', 'ANMF'], true)
                        || ($chunk['type'] === 'VP8X' && $chunk['length'] > 0 && (ord(fread($stream, 1)) & 2))))) {
                    throw new RuntimeException('Animated images are not supported for media publications.');
                }
                if ($type === IMAGETYPE_PNG && $chunk['type'] === 'IEND') {
                    return;
                }
            }
        } finally {
            fclose($stream);
        }
    }

    private function watermarkImage(ImageInterface $image, array $profile): ImageInterface
    {
        if (! config('brand.images_watermark_enabled')) {
            return $image;
        }
        $path = public_path((string) config('assets.watermark.local_path'));
        $this->guardImage($path, $profile);
        $padding = (int) config('assets.watermark.image.padding');

        return $image->place($path, (string) config('assets.watermark.image.position'), $padding, $padding);
    }

    private function processVideo(string $source, string $scratch, string $kind, array $payload, array $profile): array
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($source);
        if (! is_string($mime) || (! str_starts_with($mime, 'video/') && ! in_array($mime, ['application/ogg', 'application/octet-stream'], true))) {
            throw new RuntimeException('Unsupported or invalid publication video.');
        }

        $service = app(FFMpegService::class);
        $probe = $service->getFFProbe();
        $stream = $probe->streams($source)->videos()->first();
        $duration = (float) $probe->format($source)->get('duration');
        if (! $stream || ! is_finite($duration) || $duration <= 0 || $duration > $profile['max_video_duration']
            || (int) $stream->get('width') < 2 || (int) $stream->get('height') < 2
            || data_get($stream->get('disposition'), 'attached_pic', 0)) {
            throw new RuntimeException('Invalid video or video duration exceeds the allowed limit.');
        }
        [$clipStart, $clipDuration] = $this->clip($duration, $kind, $payload, $profile);
        $fps = $this->frameRate($stream->get('avg_frame_rate')) ?: $this->frameRate($stream->get('r_frame_rate'));
        $variableFrameRate = abs($fps - $this->frameRate($stream->get('r_frame_rate'))) > 0.01;
        $sampleAspectRatio = $this->frameRate(str_replace(':', '/', (string) $stream->get('sample_aspect_ratio')));
        if ($fps <= 0) {
            throw new RuntimeException('Invalid publication video frame rate.');
        }

        $ffmpeg = $service->getFFMpeg();
        $video = $ffmpeg->open($source);
        if (! $video instanceof Video) {
            throw new RuntimeException('Publication source does not contain a video.');
        }
        $threads = max(1, (int) config('media.publications.ffmpeg_threads', 1));
        $format = MediaEncodingProfile::x264(
            $profile['video_crf'], $profile['video_preset'], $profile['audio_bitrate'], [
                '-map', '0:v:0', '-map', '0:a:0?', '-map_metadata', '-1', '-map_chapters', '-1',
                '-threads', (string) $threads, '-fps_mode', 'vfr',
            ]
        )->setInitialParameters(['-threads', (string) $threads, '-filter_threads', (string) $threads]);
        $video->filters()->custom($this->videoFilter($kind, $fps, $profile, $sampleAspectRatio, $variableFrameRate));
        $video->filters()->clip(TimeCode::fromSeconds($clipStart), TimeCode::fromSeconds($clipDuration));
        if ($kind === 'post' && config('brand.videos_watermark_enabled')) {
            $watermark = config('assets.watermark');
            $watermarkPath = public_path($watermark['local_path']);
            $this->guardImage($watermarkPath, $profile);
            $video->filters()->watermark($watermarkPath, [
                'position' => $watermark['video']['position'],
                'x' => $watermark['video']['x'],
                'y' => $watermark['video']['y'],
            ]);
            // PNG overlays can propagate full-range color metadata into the encoded video.
            $video->filters()->custom('scale=in_range=auto:out_range=tv,format=yuv420p');
        }
        $path = $scratch.'/media.mp4';
        $video->save($format, $path);

        $optimized = $probe->streams($path)->videos()->first();
        $optimizedDuration = (float) $probe->format($path)->get('duration');
        $optimizedFps = $this->frameRate($optimized?->get('avg_frame_rate'));
        if (! $optimized || $optimized->get('codec_name') !== 'h264' || $optimized->get('pix_fmt') !== 'yuv420p'
            || ! is_finite($optimizedDuration) || $optimizedDuration <= 0
            || abs($optimizedDuration - $clipDuration) > max(0.25, 2 / min($fps, $profile['video_max_fps']))
            || $optimizedFps > $profile['video_max_fps'] + 0.01) {
            throw new RuntimeException('Optimized publication video failed verification.');
        }

        $posterSource = $scratch.'/poster.png';
        $ffmpeg->open($path)->frame(TimeCode::fromSeconds(0))->save($posterSource);
        $this->guardImage($posterSource, $profile);
        $poster = $scratch.'/poster.webp';
        $posterImage = $this->imageManager()->read($posterSource);
        if ($kind === 'post') {
            $posterImage = $this->watermarkImage($posterImage, $profile);
        }
        $posterImage->toWebp($profile['image_quality'])->save($poster);

        return [
            'media' => $path,
            'poster' => $poster,
            'metadata' => array_merge($this->presentation((int) $optimized->get('width'), (int) $optimized->get('height')), [
                'duration' => parse_duration((int) floor($optimizedDuration)),
                'seconds' => $optimizedDuration,
                'duration_seconds' => $optimizedDuration,
                'fps' => $optimizedFps,
                'original_duration_seconds' => $duration,
                'clip_start_seconds' => $clipStart,
                'clip_duration_seconds' => $clipDuration,
            ]),
        ];
    }

    private function clip(float $duration, string $kind, array $payload, array $profile): array
    {
        $start = $payload['clip_start_seconds'] ?? 0;
        $length = $payload['clip_duration_seconds'] ?? null;
        if (! is_numeric($start) || ! is_finite((float) $start) || $start < 0 || $start >= $duration
            || ($length !== null && (! is_numeric($length) || ! is_finite((float) $length) || $length <= 0))) {
            throw new RuntimeException('Invalid publication video clip.');
        }
        $start = (float) $start;
        $length = $length === null ? $duration - $start : (float) $length;
        if ($start + $length > $duration + 0.05) {
            throw new RuntimeException('Publication video clip exceeds the actual source duration.');
        }
        $length = min($length, $duration - $start);
        if ($kind === 'story') {
            $length = min($length, $profile['story_max_duration']);
        }

        return [$start, $length];
    }

    private function videoFilter(string $kind, float $fps, array $profile, float $sampleAspectRatio, bool $variableFrameRate): string
    {
        if ($kind === 'chat') {
            $square = $profile['chat_square_size'];
            $filter = "scale={$square}:{$square}:force_original_aspect_ratio=increase,crop={$square}:{$square}";
        } else {
            $filter = "scale=w='min(iw,1080)':h='min(ih,1920)':force_original_aspect_ratio=decrease:force_divisible_by=2";
        }
        if ($sampleAspectRatio > 0 && abs($sampleAspectRatio - 1) > 0.000001) {
            // Convert anamorphic pixels to square pixels by shrinking, preserving display aspect ratio.
            $filter = "scale=w='max(2,trunc(iw*min(sar,1)/2)*2)':h='max(2,trunc(ih/max(sar,1)/2)*2)',setsar=1,".$filter;
        }
        $filter .= ',setsar=1';
        if ($variableFrameRate) {
            // Drop burst frames by timestamp without filling slower sections with duplicates.
            $maxFps = $profile['video_max_fps'];
            $filter .= ",select='isnan(prev_selected_t)+gte(t-prev_selected_t,1/{$maxFps}-0.000001)'";
        } elseif ($fps > $profile['video_max_fps']) {
            $filter .= ',fps='.$profile['video_max_fps'];
        }

        return $filter;
    }

    private function frameRate(mixed $value): float
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return 0;
        }
        $parts = explode('/', (string) $value);
        $denominator = isset($parts[1]) ? (float) $parts[1] : 1;
        $rate = $denominator > 0 ? (float) $parts[0] / $denominator : 0;

        return is_finite($rate) && $rate > 0 ? $rate : 0;
    }

    private function presentation(int $width, int $height): array
    {
        return [
            'dimensions' => ['width' => $width, 'height' => $height],
            'aspect_ratio' => round($width / $height, 6),
            'is_portrait' => $width < $height,
        ];
    }

    private function storeArtifact(string $disk, string $path, string $localPath, string $mime): int
    {
        $size = is_file($localPath) ? filesize($localPath) : 0;
        if (! $size || (new \finfo(FILEINFO_MIME_TYPE))->file($localPath) !== $mime) {
            throw new RuntimeException('Optimized publication media is missing, empty, or has an invalid MIME type.');
        }
        $stream = fopen($localPath, 'rb');
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to read optimized publication media.');
        }
        try {
            $options = ['visibility' => 'public', 'ContentType' => $mime];
            if ($cacheControl = config('media.cache.control')) {
                $options['CacheControl'] = $cacheControl;
            }
            if (! Storage::disk($disk)->put($path, $stream, $options)
                || Storage::disk($disk)->size($path) !== $size) {
                throw new RuntimeException('Unable to store optimized publication media.');
            }
        } finally {
            fclose($stream);
        }

        return $size;
    }
}
