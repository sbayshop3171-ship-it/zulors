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
use App\Services\Calls\AgoraRtcTokenService;
use App\Services\Calls\CallLifecycleService;
use App\Services\Calls\CallPushNotificationService;
use App\Services\Calls\StaleCallCleanupService;
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

    private const RING_TIMEOUT_SECONDS = StaleCallCleanupService::RING_TIMEOUT_SECONDS;

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

    public function iceServers()
    {
        [$iceServers, $expiresAt] = $this->makeIceServers();

        return $this->responseSuccess([
            'data' => [
                'ice_servers' => $iceServers,
                'expires_at' => $expiresAt?->toIso8601String(),
            ],
        ]);
    }

    public function mediaToken(string $callUuid, AgoraRtcTokenService $agoraTokens)
    {
        $callSession = $this->resolveCallForMe($callUuid);

        if(empty($callSession)) {
            return $this->responseResourceNotFoundError('Call', $callUuid);
        }

        $this->finalizeStaleActiveCallsForUsers([me()->id]);
        $callSession = $callSession->fresh(['chat', 'initiator', 'receiver']);

        if($callSession->status === CallStatus::RINGING) {
            return $this->responseError([
                'message' => 'Call media can only start after the call is answered.',
            ], Response::HTTP_CONFLICT);
        }

        if(! $callSession->status->isActive()) {
            return $this->responseError([
                'message' => 'This call is no longer active.',
            ], Response::HTTP_GONE);
        }

        if(! $agoraTokens->enabled()) {
            return $this->responseSuccess([
                'data' => [
                    'media' => [
                        'provider' => 'webrtc',
                        'reason' => 'agora_not_configured',
                    ],
                ],
            ]);
        }

        $media = $agoraTokens->make($callSession, me()->id);

        return $this->responseSuccess([
            'data' => [
                'media' => $media,
            ],
        ]);
    }

    public function start(Request $request, CallLifecycleService $calls)
    {
        $validator = Validator::make($request->all(), [
            'chat_id' => ['required', 'uuid'],
            'media_type' => ['nullable', Rule::in([CallMediaType::AUDIO->value])],
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

        $this->finalizeStaleActiveCallsForUsers([me()->id, $receiver->id]);

        if($this->hasActiveCall(me()->id) || $this->hasActiveCall($receiver->id)) {
            return $this->responseBusy($chat);
        }

        $mediaType = CallMediaType::AUDIO;
        $expiresAt = now()->addSeconds(self::RING_TIMEOUT_SECONDS);

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

    public function answer(string $callUuid, CallPushNotificationService $callPushNotifications)
    {
        $callSession = $this->resolveCallForMe($callUuid);

        if(empty($callSession)) {
            return $this->responseResourceNotFoundError('Call', $callUuid);
        }

        $this->finalizeStaleActiveCallsForUsers([$callSession->initiator_id, $callSession->receiver_id]);
        $callSession = $callSession->fresh(['chat', 'initiator', 'receiver']);

        if($callSession->receiver_id !== me()->id) {
            return $this->responseError([
                'message' => 'Only the receiver can answer this call.',
            ], Response::HTTP_FORBIDDEN);
        }

        if($callSession->status->isFinal()) {
            return $this->responseError([
                'message' => 'This call is no longer active.',
            ], Response::HTTP_GONE);
        }

        if($callSession->status !== CallStatus::RINGING) {
            return $this->responseSuccess([
                'data' => [
                    'call' => CallSessionResource::make($callSession),
                ],
            ]);
        }

        $callSession = DB::transaction(function () use ($callSession) {
            $lockedCallSession = CallSession::query()
                ->whereKey($callSession->id)
                ->lockForUpdate()
                ->firstOrFail();

            if($lockedCallSession->status === CallStatus::RINGING) {
                $lockedCallSession->forceFill([
                    'status' => CallStatus::ACCEPTED,
                    'answered_at' => now(),
                ])->save();

                $lockedCallSession->participants()
                    ->where('user_id', me()->id)
                    ->update([
                        'status' => CallStatus::ACCEPTED,
                        'joined_at' => now(),
                    ]);
            }

            return $lockedCallSession->fresh(['chat', 'initiator', 'receiver']);
        });
        $callPushNotifications->cancelIncomingNotification($callSession);
        event(new CallSessionEvent('call.answered', $callSession));

        return $this->responseSuccess([
            'data' => [
                'call' => CallSessionResource::make($callSession),
            ],
        ]);
    }

    public function decline(string $callUuid, CallLifecycleService $calls, CallPushNotificationService $callPushNotifications)
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
        $callPushNotifications->cancelIncomingNotification($callSession);

        event(new CallSessionEvent('call.declined', $callSession, [
            'reason' => 'declined',
        ]));

        return $this->responseSuccess([
            'data' => [
                'call' => CallSessionResource::make($callSession),
            ],
        ]);
    }

    public function end(Request $request, string $callUuid, CallLifecycleService $calls, CallPushNotificationService $callPushNotifications)
    {
        $validator = Validator::make($request->all(), [
            'reason' => ['nullable', 'string', Rule::in(['user_ended', 'canceled', 'no_answer', 'connection_lost', 'connection_timeout', 'ice_failed'])],
        ]);

        if($validator->fails()) {
            return $this->throwValidationError($validator);
        }

        $callSession = $this->resolveCallForMe($callUuid);

        if(empty($callSession)) {
            return $this->responseResourceNotFoundError('Call', $callUuid);
        }

        $requestedReason = (string) $request->input('reason', 'user_ended');
        $reason = $callSession->status === CallStatus::RINGING && $requestedReason !== 'no_answer' && $callSession->initiator_id === me()->id
            ? 'canceled'
            : $requestedReason;
        $finalStatus = $reason === 'no_answer'
            ? CallStatus::MISSED
            : (in_array($reason, ['connection_lost', 'connection_timeout', 'ice_failed'], true)
            ? CallStatus::FAILED
            : CallStatus::ENDED);

        $actorUserId = $reason === 'no_answer' ? $callSession->initiator_id : me()->id;

        $calls->finalize($callSession, $finalStatus, $reason, $actorUserId);
        $callSession = $callSession->fresh(['chat', 'initiator', 'receiver']);
        $callPushNotifications->cancelIncomingNotification($callSession);

        event(new CallSessionEvent('call.ended', $callSession, [
            'reason' => $reason,
        ]));

        return $this->responseSuccess([
            'data' => [
                'call' => CallSessionResource::make($callSession),
            ],
        ]);
    }

    public function heartbeat(string $callUuid, CallLifecycleService $calls)
    {
        $callSession = $this->resolveCallForMe($callUuid);

        if(empty($callSession)) {
            return $this->responseResourceNotFoundError('Call', $callUuid);
        }

        $this->finalizeStaleActiveCallsForUsers([me()->id]);
        $callSession = $callSession->fresh(['chat', 'initiator', 'receiver']);

        if(! $callSession->status->isActive()) {
            return $this->responseError([
                'message' => 'This call is no longer active.',
            ], Response::HTTP_GONE);
        }

        $callSession = DB::transaction(function () use ($callSession) {
            $lockedCallSession = CallSession::query()
                ->whereKey($callSession->id)
                ->lockForUpdate()
                ->firstOrFail();

            if(! $lockedCallSession->status->isActive()) {
                return $lockedCallSession->fresh(['chat', 'initiator', 'receiver']);
            }

            $this->recordParticipantHeartbeat($lockedCallSession);

            $metadata = $lockedCallSession->metadata ?: [];
            $latestHeartbeats = data_get($metadata, 'heartbeat.latest', []);
            $latestHeartbeats[(string) me()->id] = now()->toIso8601String();

            data_set($metadata, 'heartbeat.latest', $latestHeartbeats);
            data_set($metadata, 'heartbeat.last_seen_at', now()->toIso8601String());

            $lockedCallSession->forceFill([
                'metadata' => $metadata,
            ])->save();

            return $lockedCallSession->fresh(['chat', 'initiator', 'receiver']);
        });

        if(! $callSession->status->isActive()) {
            return $this->responseError([
                'message' => 'This call is no longer active.',
            ], Response::HTTP_GONE);
        }

        return $this->responseSuccess([
            'data' => [
                'call' => CallSessionResource::make($callSession),
            ],
        ]);
    }

    public function quality(Request $request, string $callUuid)
    {
        $validator = Validator::make($request->all(), [
            'network_quality' => ['nullable', 'string', Rule::in(['good', 'weak', 'poor', 'reconnecting', 'unknown'])],
            'issue' => ['nullable', 'string', 'max:120'],
            'connection_state' => ['nullable', 'string', Rule::in(['new', 'connecting', 'connected', 'disconnected', 'failed', 'closed', 'unknown'])],
            'ice_connection_state' => ['nullable', 'string', Rule::in(['new', 'checking', 'connected', 'completed', 'disconnected', 'failed', 'closed', 'unknown'])],
            'round_trip_time_ms' => ['nullable', 'numeric', 'min:0', 'max:60000'],
            'jitter_ms' => ['nullable', 'numeric', 'min:0', 'max:60000'],
            'packets_lost' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'packets_received' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'packet_loss_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bytes_sent' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'bytes_received' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'available_outgoing_bitrate' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'audio_level' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        if($validator->fails()) {
            return $this->throwValidationError($validator);
        }

        $callSession = $this->resolveCallForMe($callUuid);

        if(empty($callSession)) {
            return $this->responseResourceNotFoundError('Call', $callUuid);
        }

        $this->finalizeStaleActiveCallsForUsers([$callSession->initiator_id, $callSession->receiver_id]);
        $callSession = $callSession->fresh(['chat', 'initiator', 'receiver']);

        if(! $callSession->status->isActive()) {
            return $this->responseError([
                'message' => 'This call is no longer active.',
            ], Response::HTTP_GONE);
        }

        $metadata = $callSession->metadata ?: [];
        $qualityData = $metadata['quality'] ?? [];
        $latest = $qualityData['latest'] ?? [];
        $summary = $qualityData['summary'] ?? [];
        $events = $qualityData['events'] ?? [];
        $userKey = (string) me()->id;
        $sample = array_filter([
            'network_quality' => $request->input('network_quality', 'unknown'),
            'issue' => $request->input('issue'),
            'connection_state' => $request->input('connection_state', 'unknown'),
            'ice_connection_state' => $request->input('ice_connection_state', 'unknown'),
            'round_trip_time_ms' => $this->metricFloat($request->input('round_trip_time_ms')),
            'jitter_ms' => $this->metricFloat($request->input('jitter_ms')),
            'packets_lost' => $this->metricInt($request->input('packets_lost')),
            'packets_received' => $this->metricInt($request->input('packets_received')),
            'packet_loss_percent' => $this->metricFloat($request->input('packet_loss_percent')),
            'bytes_sent' => $this->metricInt($request->input('bytes_sent')),
            'bytes_received' => $this->metricInt($request->input('bytes_received')),
            'available_outgoing_bitrate' => $this->metricFloat($request->input('available_outgoing_bitrate')),
            'audio_level' => $this->metricFloat($request->input('audio_level')),
            'reported_at' => now()->toIso8601String(),
        ], fn ($value) => $value !== null && $value !== '');

        $latest[$userKey] = $sample;

        $userSummary = $summary[$userKey] ?? [
            'reports_count' => 0,
            'weak_reports' => 0,
            'poor_reports' => 0,
            'max_packet_loss_percent' => 0,
            'max_jitter_ms' => 0,
            'max_round_trip_time_ms' => 0,
            'last_reported_at' => null,
        ];
        $userSummary['reports_count'] = ((int) ($userSummary['reports_count'] ?? 0)) + 1;
        $userSummary['weak_reports'] = ((int) ($userSummary['weak_reports'] ?? 0)) + ($sample['network_quality'] === 'weak' ? 1 : 0);
        $userSummary['poor_reports'] = ((int) ($userSummary['poor_reports'] ?? 0)) + (in_array($sample['network_quality'], ['poor', 'reconnecting'], true) ? 1 : 0);
        $userSummary['max_packet_loss_percent'] = max((float) ($userSummary['max_packet_loss_percent'] ?? 0), (float) ($sample['packet_loss_percent'] ?? 0));
        $userSummary['max_jitter_ms'] = max((float) ($userSummary['max_jitter_ms'] ?? 0), (float) ($sample['jitter_ms'] ?? 0));
        $userSummary['max_round_trip_time_ms'] = max((float) ($userSummary['max_round_trip_time_ms'] ?? 0), (float) ($sample['round_trip_time_ms'] ?? 0));
        $userSummary['last_network_quality'] = $sample['network_quality'] ?? 'unknown';
        $userSummary['last_issue'] = $sample['issue'] ?? null;
        $userSummary['last_reported_at'] = $sample['reported_at'];
        $summary[$userKey] = $userSummary;

        if(in_array(($sample['network_quality'] ?? 'unknown'), ['weak', 'poor', 'reconnecting'], true) || ! empty($sample['issue'])) {
            $events[] = [
                'user_id' => me()->id,
                'network_quality' => $sample['network_quality'] ?? 'unknown',
                'issue' => $sample['issue'] ?? null,
                'reported_at' => $sample['reported_at'],
            ];
            $events = array_slice($events, -20);
        }

        $metadata['quality'] = [
            'latest' => $latest,
            'summary' => $summary,
            'events' => $events,
        ];

        $callSession->forceFill([
            'metadata' => $metadata,
        ])->save();

        return $this->responseSuccess([
            'data' => [
                'quality' => [
                    'accepted' => true,
                    'summary' => $summary[$userKey],
                ],
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

        if(strlen((string) json_encode($request->input('signal', []))) > 160000) {
            return $this->responseError([
                'message' => 'Call signal payload is too large.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $callSession = $this->resolveCallForMe($callUuid);

        if(empty($callSession)) {
            return $this->responseResourceNotFoundError('Call', $callUuid);
        }

        $this->finalizeStaleActiveCallsForUsers([$callSession->initiator_id, $callSession->receiver_id]);
        $callSession = $callSession->fresh(['chat', 'initiator', 'receiver']);

        if(! $callSession->status->isActive()) {
            return $this->responseError([
                'message' => 'This call is no longer active.',
            ], Response::HTTP_GONE);
        }

        $signalType = (string) $request->input('signal_type');
        $signalPayload = $request->input('signal', []);
        $callSession = DB::transaction(function () use ($callSession, $signalType, $signalPayload) {
            $lockedCallSession = CallSession::query()
                ->whereKey($callSession->id)
                ->lockForUpdate()
                ->firstOrFail();

            if(! $lockedCallSession->status->isActive()) {
                return $lockedCallSession->fresh(['chat', 'initiator', 'receiver']);
            }

            $this->recordParticipantSignalState($lockedCallSession, $signalType, $signalPayload);

            $metadata = $lockedCallSession->metadata ?: [];
            $latestSignals = data_get($metadata, 'signaling.latest', []);
            $signalProvider = $this->signalProvider($signalPayload);
            $latestSignals[(string) me()->id] = array_filter([
                'type' => $signalType,
                'provider' => $signalProvider,
                'reported_at' => now()->toIso8601String(),
            ]);

            data_set($metadata, 'signaling.latest', $latestSignals);

            if(filled($signalProvider)) {
                data_set($metadata, 'media.provider', $signalProvider);
            }

            $attributes = [
                'metadata' => $metadata,
            ];

            if(in_array($signalType, ['ready', 'offer', 'answer'], true)
                && $lockedCallSession->status === CallStatus::ACCEPTED) {
                $attributes['status'] = CallStatus::CONNECTING;
            }

            if($signalType === 'connected') {
                $attributes['status'] = CallStatus::CONNECTED;
                $attributes['connected_at'] = $lockedCallSession->connected_at ?: now();
            }

            $lockedCallSession->forceFill($attributes)->save();

            return $lockedCallSession->fresh(['chat', 'initiator', 'receiver']);
        });

        if(! $callSession->status->isActive()) {
            return $this->responseError([
                'message' => 'This call is no longer active.',
            ], Response::HTTP_GONE);
        }

        event(new CallSessionEvent('call.signal', $callSession, [
            'sender_user_id' => me()->id,
            'signal_type' => $signalType,
            'signal' => $signalPayload,
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
        $staleCalls = app(StaleCallCleanupService::class);

        return CallSession::query()
            ->active()
            ->with(['participants'])
            ->whereHas('participants', fn ($query) => $query->where('user_id', $userId))
            ->orderByDesc('id')
            ->get()
            ->contains(fn (CallSession $callSession) => ! $staleCalls->isStale($callSession));
    }

    private function finalizeStaleActiveCallsForUsers(array $userIds): void
    {
        app(StaleCallCleanupService::class)->cleanup($userIds, 100);
    }

    private function recordParticipantHeartbeat(CallSession $callSession): void
    {
        $participant = $callSession->participants()
            ->where('user_id', me()->id)
            ->first();

        if(empty($participant)) {
            return;
        }

        $metadata = $participant->metadata ?: [];
        $metadata['heartbeat_at'] = now()->toIso8601String();

        $participantStatus = $callSession->status === CallStatus::CONNECTED
            ? CallStatus::CONNECTED
            : (in_array($callSession->status, [CallStatus::ACCEPTED, CallStatus::CONNECTING], true)
                ? CallStatus::CONNECTING
                : $participant->status);

        $participant->forceFill([
            'status' => $participantStatus,
            'joined_at' => $participant->joined_at ?: now(),
            'metadata' => $metadata,
        ])->save();
    }

    private function recordParticipantSignalState(CallSession $callSession, string $signalType, array $signalPayload): void
    {
        $participant = $callSession->participants()
            ->where('user_id', me()->id)
            ->first();

        if(empty($participant)) {
            return;
        }

        $metadata = $participant->metadata ?: [];
        $signaling = $metadata['signaling'] ?? [];
        $reportedAt = now()->toIso8601String();
        $signalProvider = $this->signalProvider($signalPayload);

        $signaling["{$signalType}_at"] = $reportedAt;
        $signaling['last_signal_type'] = $signalType;
        $signaling['last_signal_at'] = $reportedAt;
        $metadata['signaling'] = $signaling;

        if(filled($signalProvider)) {
            $metadata['media_provider'] = $signalProvider;
        }

        $participantStatus = in_array($signalType, ['ready', 'offer', 'answer'], true)
            ? CallStatus::CONNECTING
            : $participant->status;

        if($signalType === 'ready') {
            $metadata['media_ready_at'] = $reportedAt;
        }

        if($signalType === 'connected') {
            $metadata['media_connected_at'] = $reportedAt;
            $participantStatus = CallStatus::CONNECTED;
        }

        $participant->forceFill([
            'status' => $participantStatus,
            'joined_at' => $participant->joined_at ?: now(),
            'metadata' => $metadata,
        ])->save();
    }

    private function signalProvider(array $signalPayload): ?string
    {
        $provider = data_get($signalPayload, 'provider');

        if(! is_string($provider)) {
            return null;
        }

        $provider = Str::lower(trim($provider));

        return in_array($provider, ['agora', 'webrtc'], true) ? $provider : null;
    }

    private function responseBusy(Chat $chat)
    {
        try {
            $busyCallSession = new CallSession([
                'call_uuid' => (string) Str::uuid(),
                'chat_id' => $chat->id,
                'initiator_id' => me()->id,
                'receiver_id' => me()->id,
                'media_type' => CallMediaType::AUDIO,
                'status' => CallStatus::BUSY,
            ]);
            $busyCallSession->setRelation('chat', $chat);

            event(new CallSessionEvent('call.busy', $busyCallSession, [
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

    private function metricFloat($value): ?float
    {
        return is_numeric($value) ? round((float) $value, 3) : null;
    }

    private function metricInt($value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    private function makeIceServers(): array
    {
        $iceServers = [];
        $stunUrls = $this->configUrlList('services.calls.stun_urls');
        $turnUrls = $this->configUrlList('services.calls.turn_urls');
        $expiresAt = null;

        if(! empty($stunUrls)) {
            $iceServers[] = [
                'urls' => count($stunUrls) === 1 ? $stunUrls[0] : $stunUrls,
            ];
        }

        if(! empty($turnUrls)) {
            $turnUsername = config('services.calls.turn_username');
            $turnCredential = config('services.calls.turn_credential');
            $turnSecret = config('services.calls.turn_secret');

            if(filled($turnSecret)) {
                $ttlSeconds = max(300, min(86400, (int) config('services.calls.turn_ttl_seconds', 3600)));
                $expiresAt = now()->addSeconds($ttlSeconds);
                $turnUsername = $expiresAt->timestamp . ':' . me()->id;
                $turnCredential = base64_encode(hash_hmac('sha1', $turnUsername, (string) $turnSecret, true));
            }

            if(filled($turnUsername) && filled($turnCredential)) {
                $iceServers[] = [
                    'urls' => count($turnUrls) === 1 ? $turnUrls[0] : $turnUrls,
                    'username' => $turnUsername,
                    'credential' => $turnCredential,
                ];
            }
        }

        if(empty($iceServers)) {
            $iceServers[] = [
                'urls' => 'stun:stun.l.google.com:19302',
            ];
        }

        return [$iceServers, $expiresAt];
    }

    private function configUrlList(string $key): array
    {
        $value = config($key, []);

        if(is_string($value)) {
            $value = explode(',', $value);
        }

        if(! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($url) => is_string($url) ? trim($url) : null,
            $value
        )));
    }
}
