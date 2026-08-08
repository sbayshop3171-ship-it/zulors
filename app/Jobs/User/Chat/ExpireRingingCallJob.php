<?php

namespace App\Jobs\User\Chat;

use App\Enums\Call\CallStatus;
use App\Events\User\Chat\CallSessionEvent;
use App\Models\CallSession;
use App\Notifications\User\Call\MissedCallNotification;
use App\Services\Calls\CallLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExpireRingingCallJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $callUuid) {}

    public function handle(CallLifecycleService $calls): void
    {
        $callSession = CallSession::query()
            ->with(['chat', 'initiator', 'receiver'])
            ->where('call_uuid', $this->callUuid)
            ->first();

        if(empty($callSession)
            || $callSession->status !== CallStatus::RINGING
            || ($callSession->expires_at && now()->lt($callSession->expires_at))) {
            return;
        }

        $calls->finalize($callSession, CallStatus::MISSED, 'no_answer', $callSession->initiator_id);
        $callSession = $callSession->fresh(['chat', 'initiator', 'receiver']);

        try {
            event(new CallSessionEvent('call.ended', $callSession, [
                'reason' => 'no_answer',
            ]));

            $callSession->receiver?->notify(new MissedCallNotification($callSession));
        }
        catch(Throwable $exception) {
            // Timeout cleanup should not fail because push or realtime is temporarily unavailable.
        }
    }
}
