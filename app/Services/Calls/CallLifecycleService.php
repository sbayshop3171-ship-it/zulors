<?php

namespace App\Services\Calls;

use App\Enums\Call\CallStatus;
use App\Enums\Chat\MessageType;
use App\Events\User\Chat\MessageReceivedEvent;
use App\Models\CallSession;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Throwable;

class CallLifecycleService
{
    public function finalize(CallSession $callSession, CallStatus $status, ?string $reason = null, ?int $actorUserId = null): ?Message
    {
        return DB::transaction(function () use ($callSession, $status, $reason, $actorUserId) {
            $lockedCallSession = CallSession::query()
                ->whereKey($callSession->id)
                ->lockForUpdate()
                ->first();

            if(empty($lockedCallSession) || $lockedCallSession->status->isFinal()) {
                return null;
            }

            $endedAt = now();

            $lockedCallSession->forceFill([
                'status' => $status,
                'end_reason' => $reason,
                'ended_at' => $endedAt,
            ])->save();

            $lockedCallSession->participants()->update([
                'status' => $status,
                'left_at' => $endedAt,
            ]);

            return $this->createCallMessage($lockedCallSession->fresh(['chat.participants', 'initiator', 'receiver']), $actorUserId);
        });
    }

    public function createCallMessage(CallSession $callSession, ?int $actorUserId = null): ?Message
    {
        $metadata = $callSession->metadata ?: [];

        if(! empty($metadata['message_id'])) {
            return null;
        }

        $chatData = $callSession->chat;
        $messageUserId = $actorUserId ?: $callSession->initiator_id;
        $participantId = $chatData->participants()->where('user_id', $messageUserId)->value('id')
            ?: $chatData->participants()->where('user_id', $callSession->initiator_id)->value('id');

        if(empty($participantId)) {
            return null;
        }

        $message = $chatData->messages()->create([
            'chat_uuid' => $chatData->chat_id,
            'user_id' => $messageUserId,
            'participant_id' => $participantId,
            'content' => $this->messageText($callSession),
            'type' => MessageType::CALL,
            'text_language' => 'en',
        ]);

        $metadata['message_id'] = $message->id;
        $callSession->forceFill([
            'metadata' => $metadata,
        ])->save();

        $chatData->update([
            'last_activity' => now(),
        ]);

        $message = $this->loadRealtimeRelations($message);

        try {
            DB::afterCommit(function () use ($message) {
                event(new MessageReceivedEvent($message));
            });
        }
        catch(Throwable $exception) {
            // Call history should be saved even if realtime delivery is unavailable.
        }

        return $message;
    }

    private function messageText(CallSession $callSession): string
    {
        if($callSession->status === CallStatus::MISSED) {
            return 'Missed voice call';
        }

        if($callSession->status === CallStatus::DECLINED) {
            return 'Voice call declined';
        }

        if($callSession->status === CallStatus::FAILED) {
            return 'Voice call failed';
        }

        if($callSession->status === CallStatus::BUSY) {
            return 'Voice call busy';
        }

        $duration = $this->durationText($callSession);

        return $duration ? "Voice call ended · {$duration}" : 'Voice call ended';
    }

    private function durationText(CallSession $callSession): ?string
    {
        if(empty($callSession->connected_at) || empty($callSession->ended_at)) {
            return null;
        }

        $seconds = max(0, $callSession->connected_at->diffInSeconds($callSession->ended_at));
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d', $minutes, $remainingSeconds);
    }

    private function loadRealtimeRelations(Message $messageData): Message
    {
        return $messageData->load([
            'reactions',
            'media',
            'participant',
            'user:id,first_name,last_name,username,avatar,verified',
            'parent.user:id,first_name,last_name,username,avatar,verified',
            'parent.participant',
            'parent.media',
            'parent.linkSnapshot',
            'linkSnapshot'
        ]);
    }
}
