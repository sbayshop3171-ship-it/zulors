<?php

namespace App\Http\Controllers\Api\Push;

use App\Enums\Chat\MessageType;
use App\Events\User\Chat\MessageReadEvent;
use App\Events\User\Chat\MessageReceivedEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\Chat\MessageResource;
use App\Models\Chat;
use App\Models\HiddenChat;
use App\Models\User;
use App\Notifications\User\Chat\MessageReceivedNotification;
use App\Rules\X\XRule;
use App\Services\Notifications\NotificationActionTokenService;
use App\Services\Notifications\UnreadBadgeCountService;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class PushActionController extends Controller
{
    use SupportsApiResponses;

    public function reply(Request $request, NotificationActionTokenService $tokens, UnreadBadgeCountService $badges)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'content' => ['required', 'string', 'min:1', XRule::join('max', config('chat.message.validation.content.max'))],
        ]);

        if($validator->fails()) {
            return $this->throwValidationError($validator);
        }

        $payload = $tokens->verify((string) $request->input('token'), 'reply');
        [$user, $chat] = $this->resolveActionContext($payload);
        $participant = $chat->participants()->where('user_id', $user->id)->firstOrFail();
        $messageContent = $request->string('content')->toString();

        $message = $chat->messages()->create([
            'content' => e($messageContent),
            'user_id' => $user->id,
            'chat_uuid' => $chat->chat_id,
            'participant_id' => $participant->id,
            'type' => MessageType::TEXT,
            'text_language' => detect_text_language($messageContent),
        ]);

        $participant->update([
            'last_read_message_id' => $message->id,
            'last_read_at' => now(),
        ]);

        $message = $this->loadMessageRealtimeRelations($message);

        try {
            event(new MessageReceivedEvent($message));

            $chat->participants()
                ->whereNot('user_id', $user->id)
                ->with('user')
                ->get()
                ->each(function ($participant) use ($message) {
                    $participant->user?->notify(new MessageReceivedNotification($message));
                });
        }
        catch(Throwable $exception) {
            // Notification shade replies should never fail because realtime delivery is temporarily unavailable.
        }

        $chat->update([
            'last_activity' => now(),
        ]);

        if($chat->type->isDirect()) {
            HiddenChat::where('chat_id', $chat->id)->delete();
        }

        return $this->responseSuccess([
            'data' => [
                'message' => MessageResource::make($message),
                'badge_count' => $badges->forUser($user),
            ],
        ]);
    }

    public function read(Request $request, NotificationActionTokenService $tokens, UnreadBadgeCountService $badges)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
        ]);

        if($validator->fails()) {
            return $this->throwValidationError($validator);
        }

        $payload = $tokens->verify((string) $request->input('token'), 'read');
        [$user, $chat] = $this->resolveActionContext($payload);
        $participant = $chat->participants()->where('user_id', $user->id)->firstOrFail();
        $lastMessage = $chat->messages()->latest('id')->first();
        $statusUpdated = false;

        if($lastMessage && $participant->last_read_message_id < $lastMessage->id) {
            $statusUpdated = true;

            $participant->update([
                'last_read_message_id' => $lastMessage->id,
                'last_read_at' => now(),
            ]);

            try {
                event(new MessageReadEvent([
                    'chat_uuid' => $chat->chat_id,
                    'user_id' => $user->id,
                    'last_read_message_id' => $lastMessage->id,
                ]));
            }
            catch(Throwable $exception) {
                // Pass.
            }
        }

        return $this->responseSuccess([
            'data' => [
                'status_updated' => $statusUpdated,
                'badge_count' => $badges->forUser($user),
            ],
        ]);
    }

    public function muteChat(Request $request, NotificationActionTokenService $tokens)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:43200'],
        ]);

        if($validator->fails()) {
            return $this->throwValidationError($validator);
        }

        $payload = $tokens->verify((string) $request->input('token'), 'mute');
        [$user, $chat] = $this->resolveActionContext($payload);
        $mutedUntil = now()->addMinutes($request->integer('duration_minutes', 480));

        $chat->participants()
            ->where('user_id', $user->id)
            ->update([
                'notifications_muted_until' => $mutedUntil,
            ]);

        return $this->responseSuccess([
            'data' => [
                'chat_id' => $chat->chat_id,
                'muted_until' => $mutedUntil->toIso8601String(),
            ],
        ]);
    }

    private function resolveActionContext(array $payload): array
    {
        $user = User::query()->findOrFail((int) $payload['user_id']);
        $chat = Chat::query()
            ->where('chat_id', $payload['chat_uuid'])
            ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id))
            ->firstOrFail();

        return [$user, $chat];
    }

    private function loadMessageRealtimeRelations($message)
    {
        return $message->load([
            'reactions',
            'media',
            'participant',
            'user:id,first_name,last_name,username,avatar,verified',
            'parent.user:id,first_name,last_name,username,avatar,verified',
            'parent.participant',
            'parent.media',
            'parent.linkSnapshot',
            'linkSnapshot',
        ]);
    }
}
