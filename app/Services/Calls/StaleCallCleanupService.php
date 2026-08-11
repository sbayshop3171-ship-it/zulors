<?php

namespace App\Services\Calls;

use App\Enums\Call\CallStatus;
use App\Models\CallSession;
use Throwable;

class StaleCallCleanupService
{
    public const RING_TIMEOUT_SECONDS = 40;
    public const HANDSHAKE_TIMEOUT_SECONDS = 45;
    public const HEARTBEAT_TIMEOUT_SECONDS = 75;

    public function __construct(private CallLifecycleService $calls) {}

    public function cleanup(array $userIds = [], int $limit = 100): int
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        $cleanedCount = 0;

        $query = CallSession::query()
            ->whereIn('status', CallStatus::activeValues())
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->where('status', CallStatus::RINGING->value)
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<=', now());
                })
                    ->orWhere(function ($query) {
                        $threshold = now()->subSeconds(self::HANDSHAKE_TIMEOUT_SECONDS);

                        $query->whereIn('status', [CallStatus::ACCEPTED->value, CallStatus::CONNECTING->value])
                            ->whereNull('connected_at')
                            ->where(function ($query) use ($threshold) {
                                $query->where('answered_at', '<=', $threshold)
                                    ->orWhere(function ($query) use ($threshold) {
                                        $query->whereNull('answered_at')
                                            ->where('started_at', '<=', $threshold);
                                    });
                            });
                    })
                    ->orWhere(function ($query) {
                        $threshold = now()->subSeconds(self::HEARTBEAT_TIMEOUT_SECONDS);

                        $query->where('status', CallStatus::CONNECTED->value)
                            ->whereNotNull('connected_at')
                            ->where('connected_at', '<=', $threshold)
                            ->where('updated_at', '<=', $threshold);
                    });
            });

        if(! empty($userIds)) {
            $query->whereHas('participants', fn ($query) => $query->whereIn('user_id', $userIds));
        }

        $query->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (CallSession $callSession) use (&$cleanedCount) {
                try {
                    $beforeStatus = $callSession->status;

                    if($callSession->status === CallStatus::RINGING) {
                        $this->calls->finalize($callSession, CallStatus::MISSED, 'no_answer', $callSession->initiator_id);
                    }
                    else {
                        $this->calls->finalize($callSession, CallStatus::FAILED, 'connection_timeout');
                    }

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
}
