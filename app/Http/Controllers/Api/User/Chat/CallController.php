<?php

namespace App\Http\Controllers\Api\User\Chat;

use App\Enums\Call\CallMediaType;
use App\Enums\Call\CallStatus;
use App\Events\User\Chat\CallSessionEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\Chat\CallSessionResource;
use App\Jobs\User\Chat\ExpireRingingCallJob;
use App\Models\CallSession;
use App\Models\Chat;
use App\Models\User;
use App\Notifications\User\Call\IncomingCallNotification;
use App\Services\Calls\CallLifecycleService;
use App\Services\Relations\BlockService;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class CallController extends Controller
{
    use SupportsApiResponses;

    public function show(string $callUuid)
    {
        $callSession = $this->resolveCallForMe($callUuid);

        if(empty($callSession)) {
            return $this->responseResourceNotFoundError('Call', $callUuid);
        }

        return $this->responseSuccess([
            'data' => [
                'call' => CallSessionResource::make($callSession),
            ],
        ]);
    }

    public function start(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'chat_id' => ['required', 'uuid'],
            'media_type' => ['nullable', Rule::enum(CallMediaType::class)],
        ]);

        if($validator->fails()) {
            return $this->throwValidationError($validator);
        }

        $chat = $this->resolveDirectChat((string) $request->input('chat_id'));

        if(empty($chat)) {
            return $this->responseResourceNotFoundError('Chat', (string) $request->input('chat_id'));
        }

        $receiver = $this->resolveReceiver($chat);

        if(empty($receiver)) {
            return $this->responseError([
                'message' => 'Call receiver is not available.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if($this->isBlockedAny($receiver)) {
            return $this->responseError([
                'message' => 'Calls are not available for this conversation.',
            ], Response::HTTP_FORBIDDEN);
        }

        if($this->hasActiveCall(me()->id) || $this->hasActiveCall($receiver->id)) {
            return $this->responseBusy($chat);
        }

        $mediaType = CallMediaType::tryFrom((string) $request->input('media_type')) ?: CallMediaType::AUDIO;
        $expiresAt = now()->addSeconds(45);

        $callSession = DB::transaction(function () use ($chat, $receiver, $mediaType, $expiresAt) {
            $callSession = CallSession::query()->create([
                'call_uuid' => (string) Str::uuid(),
                'chat_id' => $chat->id,
                'initiator_id' => me()->id,
                'receiver_id' => $receiver->id,
                'media_type' => $mediaType,
                'status' => CallStatus::RINGING,
                'started_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            $callSession->participants()->createMany([
                [
                    'user_id' => me()->id,
                    'role' => 'caller',
                    'status' => CallStatus::RINGING,
                    'joined_at' => now(),
                ],
                [
                    'user_id' => $receiver->id,
                    'role' => 'receiver',
                    'status' => CallStatus::RINGING,
                ],
            ]);

            return $callSession;
        })->load(['chat', 'initiator', 'receiver']);

        try {
            event(new CallSessionEvent('call.incoming', $callSession));
            $receiver->notify(new IncomingCallNotification($callSession));
            ExpireRingingCallJob::dispatch($callSession->call_uuid)->delay($expiresAt);
        }
        catch(Throwable $exception) {
            // Call setup should still return the session if realtime or push is temporarily unavailable.
        }

        return $this->responseSuccess([
            'data' => [
                'call' => CallSessionResource::make($callSession),
            ],
        ]);
    }

    public function answer(string $callUuid)
    {
        $callSession = $this->resolveCallForMe($callUuid);

        if(empty($callSession)) {
            return $this->responseResourceNotFoundError('Call', $callUuid);
        }

        if($callSession->receiver_id !== me()->id) {
            return $this->responseError([
                'message' => 'Only the receiver can answer this call.',
            ], Response::HTTP_FORBIDDEN);
        }

        if($callSession->status !== CallStatus::RINGING) {
            return $this->responseSuccess([
                'data' => [
                    'call' => CallSessionResource::make($callSession),
                ],
            ]);
        }

        $callSession->forceFill([
            'status' => CallStatus::ACCEPTED,
            'answered_at' => now(),
        ])->save();

        $callSession->participants()
            ->where('user_id', me()->id)
            ->update([
                'status' => CallStatus::ACCEPTED,
                'joined_at' => now(),
            ]);

        $callSession = $callSession->fresh(['chat', 'initiator', 'receiver']);
        event(new CallSessionEvent('call.answered', $callSession));

        return $this->responseSuccess([
            'data' => [
                'call' => CallSessionResource::make($callSession),
            ],
        ]);
    }

    public function decline(string $callUuid, CallLifecycleService $calls)
    {
        $callSession = $this->resolveCallForMe($callUuid);

        if(empty($callSession)) {
            return $this->responseResourceNotFoundError('Call', $callUuid);
        }

        if($callSession->receiver_id !== me()->id) {
            return $this->responseError([
                'message' => 'Only the receiver can decline this call.',
            ], Response::HTTP_FORBIDDEN);
        }

        $calls->finalize($callSession, CallStatus::DECLINED, 'declined', me()->id);
        $callSession = $callSession->fresh(['chat', 'initiator', 'receiver']);

        event(new CallSessionEvent('call.declined', $callSession, [
            'reason' => 'declined',
        ]));

        return $this->responseSuccess([
            'data' => [
                'call' => CallSessionResource::make($callSession),
            ],
        ]);
    }

    public function end(string $callUuid, CallLifecycleService $calls)
    {
        $callSession = $this->resolveCallForMe($callUuid);

        if(empty($callSession)) {
            return $this->responseResourceNotFoundError('Call', $callUuid);
        }

        $reason = $callSession->status === CallStatus::RINGING && $callSession->initiator_id === me()->id
            ? 'canceled'
            : 'user_ended';

        $calls->finalize($callSession, CallStatus::ENDED, $reason, me()->id);
        $callSession = $callSession->fresh(['chat', 'initiator', 'receiver']);

        event(new CallSessionEvent('call.ended', $callSession, [
            'reason' => $reason,
        ]));

        return $this->responseSuccess([
            'data' => [
                'call' => CallSessionResource::make($callSession),
            ],
        ]);
    }

    public function signal(Request $request, string $callUuid)
    {
        $validator = Validator::make($request->all(), [
            'signal_type' => ['required', 'string', Rule::in(['offer', 'answer', 'ice', 'candidate', 'ready', 'connected'])],
            'signal' => ['nullable', 'array'],
        ]);

        if($validator->fails()) {
            return $this->throwValidationError($validator);
        }

        $callSession = $this->resolveCallForMe($callUuid);

        if(empty($callSession)) {
            return $this->responseResourceNotFoundError('Call', $callUuid);
        }

        if(! $callSession->status->isActive()) {
            return $this->responseError([
                'message' => 'This call is no longer active.',
            ], Response::HTTP_GONE);
        }

        if(in_array($request->input('signal_type'), ['ready', 'offer', 'answer'], true)
            && $callSession->status === CallStatus::ACCEPTED) {
            $callSession->forceFill([
                'status' => CallStatus::CONNECTING,
            ])->save();
        }

        if($request->input('signal_type') === 'connected') {
            $callSession->forceFill([
                'status' => CallStatus::CONNECTED,
                'connected_at' => $callSession->connected_at ?: now(),
            ])->save();
        }

        $callSession = $callSession->fresh(['chat', 'initiator', 'receiver']);

        event(new CallSessionEvent('call.signal', $callSession, [
            'sender_user_id' => me()->id,
            'signal_type' => (string) $request->input('signal_type'),
            'signal' => $request->input('signal', []),
        ]));

        return $this->responseSuccess([
            'data' => [
                'call' => CallSessionResource::make($callSession),
            ],
        ]);
    }

    private function resolveDirectChat(string $chatUuid): ?Chat
    {
        $chat = Chat::query()
            ->where('chat_id', $chatUuid)
            ->whereHas('participants', fn ($query) => $query->where('user_id', me()->id))
            ->with(['participants.user'])
            ->first();

        if(empty($chat) || ! $chat->type->isDirect()) {
            return null;
        }

        return $chat;
    }

    private function resolveReceiver(Chat $chat): ?User
    {
        return $chat->participants
            ->first(fn ($participant) => (int) $participant->user_id !== (int) me()->id)
            ?->user;
    }

    private function resolveCallForMe(string $callUuid): ?CallSession
    {
        if(! Str::isUuid($callUuid)) {
            return null;
        }

        return CallSession::query()
            ->where('call_uuid', $callUuid)
            ->whereHas('participants', fn ($query) => $query->where('user_id', me()->id))
            ->with(['chat', 'initiator', 'receiver'])
            ->first();
    }

    private function hasActiveCall(int $userId): bool
    {
        return CallSession::query()
            ->active()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $userId))
            ->exists();
    }

    private function responseBusy(Chat $chat)
    {
        try {
            event(new CallSessionEvent('call.busy', new CallSession([
                'call_uuid' => (string) Str::uuid(),
                'chat_id' => $chat->id,
                'initiator_id' => me()->id,
                'receiver_id' => me()->id,
                'media_type' => CallMediaType::AUDIO,
                'status' => CallStatus::BUSY,
            ]), [
                'chat_id' => $chat->chat_id,
                'reason' => 'busy',
            ]));
        }
        catch(Throwable $exception) {
            // Pass.
        }

        return $this->responseError([
            'message' => 'User is busy on another call.',
            'data' => [
                'status' => CallStatus::BUSY->value,
                'reason' => 'busy',
            ],
        ], Response::HTTP_CONFLICT);
    }

    private function isBlockedAny(User $receiver): bool
    {
        try {
            return (new BlockService(me(), $receiver))->blockedAny();
        }
        catch(Throwable $exception) {
            return true;
        }
    }
}
