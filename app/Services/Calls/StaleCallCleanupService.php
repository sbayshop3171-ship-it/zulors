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
        $threshold = $now->copy()->subSeconds(self::HANDSHAKE_TIMEOUT_SECONDS);
        $startedAt = $callSession->answered_at ?: $callSession->started_at;

        return empty($startedAt) || $startedAt->lte($threshold);
    }

    private function connectedSessionExpired(CallSession $callSession, Carbon $now): bool
    {
        $connectedAt = $callSession->connected_at;

        if(empty($connectedAt)) {
            return true;
        }

        $threshold = $now->copy()->subSeconds(self::HEARTBEAT_TIMEOUT_SECONDS);

        if($connectedAt->gt($threshold)) {
            return false;
        }

        $participants = $callSession->relationLoaded('participants')
            ? $callSession->participants
            : $callSession->participants()->get();

        if($participants->count() < 2) {
            return true;
        }

        return $participants->contains(fn (CallParticipant $participant) => $this->participantHeartbeatExpired($participant, $threshold));
    }

    private function participantHeartbeatExpired(CallParticipant $participant, Carbon $threshold): bool
    {
        if(! empty($participant->left_at)) {
            return true;
        }

        $metadata = $participant->metadata ?: [];
        $lastSeenAt = $this->parseTimestamp(data_get($metadata, 'heartbeat_at'))
            ?: $this->parseTimestamp(data_get($metadata, 'media_connected_at'))
            ?: $participant->joined_at;

        return empty($lastSeenAt) || $lastSeenAt->lte($threshold);
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
