<?php

namespace App\Jobs\Media;

use App\Events\User\Chat\MessageReceivedEvent;
use App\Events\User\Story\StoryCreatedEvent;
use App\Events\User\Timeline\PostCreatedEvent;
use App\Events\User\Timeline\PublicTimelinePostCreatedEvent;
use App\Enums\Post\PostStatus;
use App\Enums\Story\StoryPrivacy;
use App\Enums\Story\StoryStatus;
use App\Models\MediaPublication;
use App\Models\Message;
use App\Models\Post;
use App\Models\StoryFrame;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\User\Chat\MessageReceivedNotification;
use App\Notifications\User\Mention\PostMentionNotification;
use App\Notifications\User\Mention\StoryMentionNotification;
use App\Services\Media\Publication\PublicationFinalizer;
use App\Services\Notifications\FirebaseCloudMessagingService;
use App\Services\Notifications\PushNotificationPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as Notifications;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

class DeliverPublicationEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 12;
    public int $timeout = 60;

    public function __construct(public int $eventId)
    {
        $this->onConnection(config('media.queue_connection'));
        $this->onQueue('default');
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120, 300, 600];
    }

    public static function record(MediaPublication $publication, string $type, ?int $recipientId = null, ?string $suffix = null): int
    {
        $key = implode(':', array_filter([$publication->id, $type, $recipientId, $suffix], fn ($part) => $part !== null));
        DB::table('media_publication_events')->insertOrIgnore([
            'publication_id' => $publication->id,
            'event_key' => $key,
            'type' => $type,
            'recipient_id' => $recipientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $id = (int) DB::table('media_publication_events')->where('event_key', $key)->value('id');
        DB::afterCommit(fn () => self::dispatchSafely($id));

        return $id;
    }

    public static function dispatchPending(int $limit = 100): void
    {
        DB::table('media_publication_events')->whereNull('delivered_at')->orderBy('updated_at')->limit($limit)
            ->pluck('id')->each(fn ($id) => self::dispatchSafely((int) $id));
    }

    private static function dispatchSafely(int $id): void
    {
        try {
            self::dispatch($id)->afterCommit();
        } catch (Throwable $exception) {
            // The committed row remains available to the periodic outbox recovery sweep.
            report($exception);
        }
    }

    public function handle(): void
    {
        // Keep the database lock through delivery. A crash after external acceptance can
        // still replay a send; stable notification IDs let clients replace/deduplicate it.
        try {
            DB::transaction(function () {
                $event = DB::table('media_publication_events')->where('id', $this->eventId)->lockForUpdate()->first();
                if (! $event || $event->delivered_at) {
                    return;
                }
                $publication = MediaPublication::find($event->publication_id);
                if (! $publication || $publication->status !== 'published') {
                    return;
                }
                $actor = User::active()->find($publication->user_id);
                if ($actor) {
                    PublicationFinalizer::asActor($actor, fn () => $this->deliver($publication, $event));
                }
                DB::table('media_publication_events')->where('id', $event->id)->update([
                    'delivered_at' => now(), 'updated_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            DB::table('media_publication_events')->where('id', $this->eventId)->whereNull('delivered_at')
                ->update(['updated_at' => now()]);
            throw $exception;
        }
    }

    private function deliver(MediaPublication $publication, object $event): void
    {
        $entity = match ($publication->kind) {
            'post' => Post::with(['user', 'media'])->find(data_get($publication->result, 'id')),
            'story' => StoryFrame::with(['story.user', 'media'])->find(data_get($publication->result, 'id')),
            'chat' => Message::with(['user', 'media', 'chat', 'participant', 'parent.user', 'parent.participant', 'parent.media', 'reactions', 'linkSnapshot'])
                ->where('is_deleted', false)->find(data_get($publication->result, 'id')),
        };
        if (! $entity) {
            return;
        }
        if ($entity instanceof Post && $entity->status !== PostStatus::ACTIVE) {
            return;
        }
        if ($entity instanceof StoryFrame && ($entity->status !== StoryStatus::ACTIVE
            || $entity->privacy !== StoryPrivacy::ALL || $entity->isExpired())) {
            return;
        }
        if ($entity instanceof Message) {
            if (! $entity->chat || ! $entity->participant || $entity->participant->chat_id !== $entity->chat_id
                || $entity->participant->user_id !== $publication->user_id) {
                return;
            }
            if ($entity->chat->type->isDirect()) {
                $others = $entity->chat->participants()->where('user_id', '!=', $publication->user_id)->with('user.permitSettings')->get();
                $otherUser = $others->count() === 1 ? $others->first()->user : null;
                if (! $otherUser || ! PublicationFinalizer::canContact($entity->user, $otherUser, 'direct_messages')) {
                    return;
                }
            }
            $entity->setAttribute('client_uid', $publication->client_uid);
        }
        if ($event->type === 'domain_created') {
            event(match ($publication->kind) {
                'post' => new PostCreatedEvent($entity),
                'story' => new StoryCreatedEvent($entity),
                'chat' => new MessageReceivedEvent($entity, $publication->client_uid),
            });
            return;
        }
        if ($event->type === 'public_timeline') {
            event(new PublicTimelinePostCreatedEvent($entity));
            return;
        }

        $recipient = User::active()->find($event->recipient_id);
        if (! $recipient) {
            return;
        }
        if (str_starts_with($event->type, 'chat_')) {
            if (! $entity->chat->participants()->where('user_id', $recipient->id)->exists()
                || ! PublicationFinalizer::canContact($entity->user, $recipient)) {
                return;
            }
        }
        if (str_starts_with($event->type, 'post_mention') || str_starts_with($event->type, 'story_mention')) {
            $actor = $entity instanceof Post ? $entity->user : $entity->story->user;
            if (! PublicationFinalizer::canContact($actor, $recipient, 'mentions')) {
                return;
            }
        }
        if ($event->type === 'owner_completion') {
            $this->recordPushTokens($publication, $recipient, 'owner_push');
            return;
        }
        if ($event->type === 'owner_push') {
            $this->sendPush($recipient, $event, [
                'data' => [
                    'type' => 'media_publication',
                    'publication_id' => (string) $publication->id,
                    'client_uid' => (string) $publication->client_uid,
                    'status' => 'published',
                    'user_id' => (string) $publication->user_id,
                    'url' => (string) data_get($publication->result, 'url', ''),
                    'title' => (string) config('app.name', 'Zulors'),
                    'body' => 'Your media is published.',
                    'channel_id' => 'zulors_activity',
                ],
                'android' => ['priority' => 'high'],
            ]);
            return;
        }

        $prefix = match (true) {
            str_starts_with($event->type, 'chat_') => 'chat',
            str_starts_with($event->type, 'post_mention') => 'post_mention',
            str_starts_with($event->type, 'story_mention') => 'story_mention',
            default => throw new RuntimeException('Unknown publication delivery event.'),
        };
        $notification = match ($prefix) {
            'chat' => new MessageReceivedNotification($entity),
            'post_mention' => new PostMentionNotification($entity),
            'story_mention' => new StoryMentionNotification($entity),
        };
        $notification->id = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, $publication->id.':'.$prefix.':'.$recipient->id);
        $channels = $notification->via($recipient);
        if (in_array($event->type, ['chat_recipient', 'post_mention', 'story_mention'], true)) {
            foreach ($channels as $channel) {
                if ($channel === WebPushChannel::class) {
                    $this->recordPushTokens($publication, $recipient, $prefix.'_push');
                } else {
                    self::record($publication, $prefix.'_'.$channel, $recipient->id);
                }
            }
            return;
        }

        $channel = substr($event->type, strlen($prefix) + 1);
        if ($channel === 'push') {
            if (in_array(WebPushChannel::class, $channels, true)) {
                $this->sendPush($recipient, $event, app(PushNotificationPayloadFactory::class)->make($recipient, $notification));
            }
        } elseif (in_array($channel, $channels, true)) {
            if ($channel === 'database') {
                $recipient->notifications()->firstOrCreate(['id' => $notification->id], [
                    'type' => $notification->notificationType,
                    'data' => $notification->toDatabase(),
                    'read_at' => null,
                ]);
            } else {
                Notifications::sendNow($recipient, $notification, [$channel]);
            }
        }
    }

    private function recordPushTokens(MediaPublication $publication, User $recipient, string $type): void
    {
        if (! config('notifications.push.enabled')) {
            return;
        }
        foreach ($recipient->pushTokens()->active()->where('provider', 'fcm')->pluck('id') as $tokenId) {
            self::record($publication, $type, $recipient->id, (string) $tokenId);
        }
    }

    private function sendPush(User $recipient, object $event, array $message): void
    {
        if (! config('notifications.push.enabled')) {
            return;
        }
        $tokenId = (int) substr($event->event_key, strrpos($event->event_key, ':') + 1);
        $token = $recipient->pushTokens()->active()->where('provider', 'fcm')->find($tokenId);
        if (! $token) {
            return;
        }
        $eventId = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, $event->event_key);
        $message['data'] = array_merge($message['data'] ?? [], [
            'event_id' => $eventId, 'notification_id' => $eventId, 'notification_tag' => $eventId,
        ]);
        $message['android']['collapse_key'] = $eventId;
        if (! app(FirebaseCloudMessagingService::class)->sendToToken($token, $message) && ! $token->fresh()?->revoked_at) {
            throw new RuntimeException('Publication push delivery failed; retry the durable event.');
        }
    }
}
