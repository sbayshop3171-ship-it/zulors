<?php

namespace App\Services\Calls;

use App\Enums\Call\CallStatus;
use App\Models\CallParticipant;
use App\Models\CallSession;
use Illuminate\Support\Carbon;
use Throwable;

class StaleCallCleanupService
{
    public const RING_TIMEOUT_SECONDS = 40;
    public const HANDSHAKE_TIMEOUT_SECONDS = 40;
    public const HEARTBEAT_TIMEOUT_SECONDS = 40;
    public const NATIVE_ENGINE_TIMEOUT_GRACE_SECONDS = 30;
    public const BACKGROUND_NATIVE_TIMEOUT_GRACE_SECONDS = 20;

    public function __construct(private CallLifecycleService $calls) {}

    public function cleanup(array $userIds = [], int $limit = 100): int
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        $cleanedCount = 0;

        $query = CallSession::query()
            ->whereIn('status', CallStatus::activeValues())
            ->with(['participants']);

        if(! empty($userIds)) {
            $query->whereHas('participants', fn ($query) => $query->whereIn('user_id', $userIds));
        }

        $now = now();

        $query->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (CallSession $callSession) use (&$cleanedCount, $now) {
                try {
                    $resolution = $this->staleResolution($callSession, $now);

                    if(empty($resolution)) {
                        return;
                    }

                    $beforeStatus = $callSession->status;

                    $this->calls->finalize(
                        $callSession,
                        $resolution['status'],
                        $resolution['reason'] ?? null,
                        $resolution['actor_user_id'] ?? null
                    );

                    $freshCallSession = $callSession->fresh();

                    if($freshCallSession?->status->isFinal() && $freshCallSession->status !== $beforeStatus) {
                        $cleanedCount++;
                    }
                }
                catch(Throwable $exception) {
                    // Cleanup must never block a fresh call attempt, heartbeat, or scheduler tick.
                }
            });

        return $cleanedCount;
    }

    public function isStale(CallSession $callSession, ?Carbon $now = null): bool
    {
        return ! empty($this->staleResolution($callSession, $now));
    }

    public function staleResolution(CallSession $callSession, ?Carbon $now = null): ?array
    {
        if(! $callSession->status?->isActive()) {
            return null;
        }

        $now = $now ?: now();

        if($callSession->status === CallStatus::RINGING) {
            if(! $this->ringingExpired($callSession, $now)) {
                return null;
            }

            return [
                'status' => CallStatus::MISSED,
                'reason' => 'no_answer',
                'actor_user_id' => $callSession->initiator_id,
            ];
        }

        if(in_array($callSession->status, [CallStatus::ACCEPTED, CallStatus::CONNECTING], true)) {
            if(! $this->handshakeExpired($callSession, $now)) {
                return null;
            }

            return [
                'status' => CallStatus::FAILED,
                'reason' => 'connection_timeout',
            ];
        }

        if($callSession->status === CallStatus::CONNECTED && $this->connectedSessionExpired($callSession, $now)) {
            return [
                'status' => CallStatus::FAILED,
                'reason' => 'connection_timeout',
            ];
        }

        return null;
    }

    private function ringingExpired(CallSession $callSession, Carbon $now): bool
    {
        if($callSession->expires_at?->lte($now)) {
            return true;
        }

        if(empty($callSession->expires_at) && $callSession->started_at?->lte($now->copy()->subSeconds(self::RING_TIMEOUT_SECONDS))) {
            return true;
        }

        return false;
    }

    private function handshakeExpired(CallSession $callSession, Carbon $now): bool
    {
        $threshold = $now->copy()->subSeconds($this->handshakeTimeoutSeconds());
        $startedAt = $callSession->answered_at ?: $callSession->started_at;

        return empty($startedAt) || $startedAt->lte($threshold);
    }

    private function connectedSessionExpired(CallSession $callSession, Carbon $now): bool
    {
        $connectedAt = $callSession->connected_at;

        if(empty($connectedAt)) {
            return true;
        }

        $threshold = $now->copy()->subSeconds($this->heartbeatTimeoutSeconds());

        if($connectedAt->gt($threshold)) {
            return false;
        }

        $participants = $callSession->relationLoaded('participants')
            ? $callSession->participants
            : $callSession->participants()->get();

        if($participants->count() < 2) {
            return true;
        }

        return $participants->contains(fn (CallParticipant $participant) => $this->participantHeartbeatExpired($participant, $now));
    }

    private function handshakeTimeoutSeconds(): int
    {
        return max(
            self::HANDSHAKE_TIMEOUT_SECONDS,
            min(120, (int) config('services.calls.agora.reconnect_grace_seconds', 60))
        );
    }

    private function heartbeatTimeoutSeconds(): int
    {
        return max(
            self::HEARTBEAT_TIMEOUT_SECONDS,
            min(120, (int) config('services.calls.agora.reconnect_grace_seconds', 60))
        );
    }

    private function participantHeartbeatExpired(CallParticipant $participant, Carbon $now): bool
    {
        if(! empty($participant->left_at)) {
            return true;
        }

        $threshold = $now->copy()->subSeconds($this->participantHeartbeatTimeoutSeconds($participant));
        $lastSeenAt = $this->participantLastSeenAt($participant);

        return empty($lastSeenAt) || $lastSeenAt->lte($threshold);
    }

    private function participantHeartbeatTimeoutSeconds(CallParticipant $participant): int
    {
        $metadata = $participant->metadata ?: [];
        $timeoutSeconds = $this->heartbeatTimeoutSeconds();
        $networkState = $this->normalizeMetadataString(
            data_get($metadata, 'heartbeat.network_state', data_get($metadata, 'network_state'))
        );
        $callEngine = $this->normalizeMetadataString(
            data_get($metadata, 'heartbeat.call_engine', data_get($metadata, 'call_engine'))
        );
        $appVisibility = $this->normalizeMetadataString(
            data_get($metadata, 'heartbeat.app_visibility', data_get($metadata, 'app_visibility'))
        );

        if($networkState === 'reconnecting') {
            $timeoutSeconds = max($timeoutSeconds, $this->reconnectingHeartbeatTimeoutSeconds());
        }

        if($callEngine === 'android-native') {
            $timeoutSeconds = max($timeoutSeconds, $this->nativeHeartbeatTimeoutSeconds());

            if(in_array($appVisibility, ['hidden', 'background'], true)) {
                $timeoutSeconds = max($timeoutSeconds, $this->backgroundNativeHeartbeatTimeoutSeconds());
            }
        }

        return $timeoutSeconds;
    }

    private function participantLastSeenAt(CallParticipant $participant): ?Carbon
    {
        $metadata = $participant->metadata ?: [];
        $callEngine = $this->normalizeMetadataString(
            data_get($metadata, 'heartbeat.call_engine', data_get($metadata, 'call_engine'))
        );
        $engineActivityAt = $this->parseTimestamp(
            data_get($metadata, 'heartbeat.engine_activity_at', data_get($metadata, 'engine_activity_at'))
        );

        if($callEngine === 'android-native' && ! empty($engineActivityAt)) {
            return $engineActivityAt;
        }

        return $this->parseTimestamp(data_get($metadata, 'heartbeat_at'))
            ?: $engineActivityAt
            ?: $this->parseTimestamp(data_get($metadata, 'media_connected_at'))
            ?: $participant->joined_at;
    }

    private function reconnectingHeartbeatTimeoutSeconds(): int
    {
        return max(
            $this->heartbeatTimeoutSeconds(),
            min(180, (int) config('services.calls.agora.reconnect_grace_seconds', 60) + self::NATIVE_ENGINE_TIMEOUT_GRACE_SECONDS)
        );
    }

    private function nativeHeartbeatTimeoutSeconds(): int
    {
        return max(
            $this->heartbeatTimeoutSeconds(),
            min(180, (int) config('services.calls.agora.reconnect_grace_seconds', 60) + self::NATIVE_ENGINE_TIMEOUT_GRACE_SECONDS)
        );
    }

    private function backgroundNativeHeartbeatTimeoutSeconds(): int
    {
        return max(
            $this->nativeHeartbeatTimeoutSeconds(),
            min(180, $this->nativeHeartbeatTimeoutSeconds() + self::BACKGROUND_NATIVE_TIMEOUT_GRACE_SECONDS)
        );
    }

    private function normalizeMetadataString(mixed $value): ?string
    {
        if(! is_scalar($value)) {
            return null;
        }

        $normalizedValue = strtolower(trim((string) $value));

        return $normalizedValue !== ''
            ? $normalizedValue
            : null;
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if(empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        }
        catch(Throwable $exception) {
            return null;
        }
    }
}
