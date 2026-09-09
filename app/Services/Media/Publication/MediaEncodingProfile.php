<?php

namespace App\Services\Media\Publication;

use FFMpeg\Format\Video\X264;
use InvalidArgumentException;

final class MediaEncodingProfile
{
    private const BALANCED_V1 = [
        'name' => 'balanced_v1',
        'image_quality' => 84,
        'image_max_edge' => 2048,
        'max_image_bytes' => 20971520,
        'image_max_pixels' => 40000000,
        'image_memory_bytes' => 536870912,
        'video_crf' => 24,
        'video_preset' => 'ultrafast',
        'video_max_fps' => 30,
        'audio_bitrate' => 128,
        'max_video_bytes' => 1073741824,
        'max_video_duration' => 600,
        'story_max_duration' => 60,
        'chat_square_size' => 720,
    ];

    public static function x264(int|string $crf, string $preset, int $audioBitrate, array $additionalParameters = []): X264
    {
        return (new X264('aac', 'libx264'))
            ->setKiloBitrate(0)
            ->setAudioKiloBitrate($audioBitrate)
            ->setAdditionalParameters(array_merge([
                '-preset', $preset, '-crf', (string) $crf,
                '-pix_fmt', 'yuv420p', '-movflags', '+faststart',
            ], $additionalParameters));
    }

    /** Capture this array when creating a publication, never again during a retry. */
    public static function defaults(): array
    {
        return self::resolve(array_replace(self::BALANCED_V1, [
            'max_image_bytes' => (int) config('media.publications.image_max_bytes', 20971520),
            'image_max_pixels' => (int) config('media.publications.image_max_pixels', 40000000),
            'max_video_bytes' => (int) config('media.uploads.video.max_bytes', 1073741824),
            'max_video_duration' => (int) config('media.uploads.video.max_duration_seconds', 600),
            'story_max_duration' => (int) config('story.video_clip_size', 60),
            'chat_square_size' => (int) config('chat.processing.video.square_size', 720),
        ]));
    }

    public static function resolve(array $snapshot): array
    {
        // Missing optional fields use versioned constants, not changing runtime settings.
        $profile = array_replace(self::BALANCED_V1, $snapshot);

        if ($profile['name'] !== 'balanced_v1') {
            throw new InvalidArgumentException('Unsupported publication encoding profile.');
        }

        foreach (self::BALANCED_V1 as $key => $value) {
            if (is_int($value) && (! is_numeric($profile[$key]) || ! is_finite((float) $profile[$key])
                || (float) $profile[$key] != (int) $profile[$key] || (int) $profile[$key] < 1)) {
                throw new InvalidArgumentException("Invalid publication encoding setting: {$key}.");
            }
            if (is_int($value)) {
                $profile[$key] = (int) $profile[$key];
            }
        }

        if ($profile['image_quality'] > 100 || $profile['image_max_edge'] > 2048
            || $profile['image_max_pixels'] > 40000000 || $profile['video_max_fps'] > 30
            || $profile['video_crf'] > 51 || $profile['chat_square_size'] > 720
            || $profile['chat_square_size'] % 2 !== 0
            || ! in_array($profile['video_preset'], ['ultrafast', 'superfast', 'veryfast', 'faster', 'fast', 'medium', 'slow', 'slower', 'veryslow'], true)) {
            throw new InvalidArgumentException('Invalid publication encoding profile limits.');
        }

        return $profile;
    }
}
