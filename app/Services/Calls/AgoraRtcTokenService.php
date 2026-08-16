<?php

namespace App\Services\Calls;

use App\Models\CallSession;
use BoogieFromZk\AgoraToken\RtcTokenBuilder2;
use Illuminate\Support\Str;
use RuntimeException;

class AgoraRtcTokenService
{
    private const MAX_UID = 4294967295;
    private const SUPPORTED_AREA_CODES = [
        'GLOBAL',
        'CHINA',
        'NORTH_AMERICA',
        'EUROPE',
        'ASIA',
        'JAPAN',
        'INDIA',
    ];
    private const SUPPORTED_AUDIO_ENCODER_PROFILES = [
        'speech_low_quality',
        'speech_standard',
        'music_standard',
        'standard_stereo',
        'high_quality',
        'high_quality_stereo',
    ];

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
            'area_code' => $this->areaCode(),
            'excluded_area' => $this->excludedArea($this->areaCode()),
            'audio_encoder_profile' => $this->audioEncoderProfile(),
            'audio_bitrate_kbps' => $this->audioBitrateKbps(),
            'audio_bitrate_floor_kbps' => $this->audioBitrateFloorKbps(),
            'audio_sample_rate' => $this->audioSampleRate(),
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

    private function areaCode(): string
    {
        return $this->normalizeAreaCode((string) config('services.calls.agora.area_code', 'GLOBAL')) ?? 'GLOBAL';
    }

    private function excludedArea(string $areaCode): ?string
    {
        $excludedArea = $this->normalizeAreaCode((string) config('services.calls.agora.excluded_area', ''));

        if($excludedArea === null || $areaCode !== 'GLOBAL' || $excludedArea === 'GLOBAL') {
            return null;
        }

        return $excludedArea;
    }

    private function audioEncoderProfile(): string
    {
        $profile = Str::lower(trim((string) config('services.calls.agora.audio_encoder_profile', 'speech_standard')));

        return in_array($profile, self::SUPPORTED_AUDIO_ENCODER_PROFILES, true)
            ? $profile
            : 'speech_standard';
    }

    private function audioBitrateKbps(): int
    {
        return max(16, min(32, (int) config('services.calls.agora.audio_bitrate_kbps', 24)));
    }

    private function audioBitrateFloorKbps(): int
    {
        return max(12, min($this->audioBitrateKbps(), (int) config('services.calls.agora.audio_bitrate_floor_kbps', 16)));
    }

    private function audioSampleRate(): int
    {
        $sampleRate = (int) config('services.calls.agora.audio_sample_rate', 32000);

        return in_array($sampleRate, [16000, 32000, 48000], true)
            ? $sampleRate
            : 16000;
    }

    private function normalizeAreaCode(string $areaCode): ?string
    {
        $normalizedAreaCode = Str::upper(str_replace(['-', ' '], '_', trim($areaCode)));

        if(empty($normalizedAreaCode)) {
            return null;
        }

        return in_array($normalizedAreaCode, self::SUPPORTED_AREA_CODES, true)
            ? $normalizedAreaCode
            : null;
    }
}
