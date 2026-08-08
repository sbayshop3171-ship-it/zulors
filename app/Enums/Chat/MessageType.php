<?php

namespace App\Enums\Chat;

enum MessageType: string
{
    case TEXT = 'text';
    case AUDIO = 'audio';
    case IMAGE = 'image';
    case VIDEO = 'video';
    case VIDEO_CIRCLE = 'video_circle';
    case DOCUMENT = 'document';
    case LOCATION = 'location';
    case CALL = 'call';

    public function isText():bool
    {
        return $this == self::TEXT;
    }

    public function isVideo():bool
    {
        return in_array($this, [self::VIDEO, self::VIDEO_CIRCLE], true);
    }

    public function isVideoCircle():bool
    {
        return $this == self::VIDEO_CIRCLE;
    }

    public function isImage():bool
    {
        return $this == self::IMAGE;
    }

    public function isAudio():bool
    {
        return $this == self::AUDIO;
    }

    public function isDocument():bool
    {
        return $this == self::DOCUMENT;
    }

    public function isLocation():bool
    {
        return $this == self::LOCATION;
    }

    public function isCall():bool
    {
        return $this == self::CALL;
    }
}
