<?php

namespace App\Enums\Call;

enum CallMediaType: string
{
    case AUDIO = 'audio';
    case VIDEO = 'video';

    public function isAudio(): bool
    {
        return $this === self::AUDIO;
    }

    public function isVideo(): bool
    {
        return $this === self::VIDEO;
    }
}
