<?php

namespace App\Enums\Call;

enum CallStatus: string
{
    case RINGING = 'ringing';
    case ACCEPTED = 'accepted';
    case CONNECTING = 'connecting';
    case CONNECTED = 'connected';
    case ENDED = 'ended';
    case MISSED = 'missed';
    case DECLINED = 'declined';
    case BUSY = 'busy';
    case FAILED = 'failed';

    public function isActive(): bool
    {
        return in_array($this, [
            self::RINGING,
            self::ACCEPTED,
            self::CONNECTING,
            self::CONNECTED,
        ], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::ENDED,
            self::MISSED,
            self::DECLINED,
            self::BUSY,
            self::FAILED,
        ], true);
    }

    public static function activeValues(): array
    {
        return array_map(fn (self $status) => $status->value, [
            self::RINGING,
            self::ACCEPTED,
            self::CONNECTING,
            self::CONNECTED,
        ]);
    }
}
