<?php

namespace App\Services\Calls;

use App\Models\CallSession;
use BoogieFromZk\AgoraToken\RtcTokenBuilder2;
use Illuminate\Support\Str;
use RuntimeException;

class AgoraRtcTokenService
{
    private const MAX_UID = 4294967295;

    public function enabled(): bool
    {
        return $this->providerAllowsAgora()
            && filled($this->appId())
            && filled($this->appCertificate());
    }

    public function provider(): string
    {
        return (string) config('services.calls.media_provider', 'auto');
    }

    public function make(CallSession $callSession, int $userId): array
    {
        if(! $this->enabled()) {
            throw new RuntimeException('Agora RTC is not configured.');
        }

        $ttlSeconds = $this->ttlSeconds();
        $expiresAt = now()->addSeconds($ttlSeconds);
        $channelName = $this->channelName($callSession);
        $uid = $this->uidFor($callSession, $userId);
        $token = RtcTokenBuilder2::buildTokenWithUid(
            $this->appId(),
            $this->appCertificate(),
            $channelName,
            $uid,
            RtcTokenBuilder2::ROLE_PUBLISHER,
            $ttlSeconds,
            $ttlSeconds
        );

        return [
            'provider' => 'agora',
            'app_id' => $this->appId(),
            'channel' => $channelName,
            'uid' => $uid,
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'token_ttl_seconds' => $ttlSeconds,
        ];
    }

    public function channelName(CallSession $callSession): string
    {
        return 'zulors_call_' . Str::replace('-', '', (string) $callSession->call_uuid);
    }

    public function uidFor(CallSession $callSession, int $userId): int
    {
        if($userId > 0 && $userId <= self::MAX_UID) {
            return $userId;
        }

        $uid = (int) sprintf('%u', crc32("zulors:{$callSession->call_uuid}:{$userId}"));

        return $uid > 0 ? $uid : 1;
    }

    private function providerAllowsAgora(): bool
    {
        return in_array($this->provider(), ['auto', 'agora'], true);
    }

    private function appId(): string
    {
        return (string) config('services.calls.agora.app_id', '');
    }

    private function appCertificate(): string
    {
        return (string) config('services.calls.agora.app_certificate', '');
    }

    private function ttlSeconds(): int
    {
        return max(300, min(86400, (int) config('services.calls.agora.token_ttl_seconds', 3600)));
    }
}
