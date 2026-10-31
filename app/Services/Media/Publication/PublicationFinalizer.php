<?php

namespace App\Services\Media\Publication;

use App\Enums\Media\MediaStatus;
use App\Enums\Post\PostStatus;
use App\Enums\Story\StoryPrivacy;
use App\Enums\Story\StoryStatus;
use App\Enums\User\PrivacyPermit;
use App\Enums\User\UserStatus;
use App\Events\User\Chat\MessageMediaReadyEvent;
use App\Events\User\Timeline\MediaProcessedEvent;
use App\Jobs\Media\DeliverPublicationEvent;
use App\Models\Chat;
use App\Models\HiddenChat;
use App\Models\Media;
use App\Models\MediaPublication;
use App\Models\MediaPublicationItem;
use App\Models\Message;
use App\Models\Post;
use App\Models\StoryFrame;
use App\Models\User;
use App\Services\Blacklist\BlacklistService;
use App\Services\Censor\CensorService;
use App\Services\Relations\BlockService;
use App\Services\Relations\FollowService;
use App\Services\Safety\SafetyService;
use App\Services\Timeline\TopicExtractionService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PublicationFinalizer
{
    public function finalize(string $publicationId): ?MediaPublication
    {
        return DB::transaction(function () use ($publicationId) {
            $publication = MediaPublication::query()->lockForUpdate()->find($publicationId);

            if (! $publication || $publication->terminal()) {
                return $publication;
            }

            $items = $publication->items()->lockForUpdate()->get();
            $publication->setRelation('items', $items);

            $outputs = $this->readyOutputs($publication, $items);

            if ($items->isEmpty() || empty($outputs)) {
                return $publication;
            }

            $actor = User::active()->lockForUpdate()->find($publication->user_id);
            if (! $actor) {
                return $this->fail($publication, 'actor_inactive');
            }

            return self::asActor($actor, function () use ($publication, $actor, $items, $outputs) {
                $payload = $publication->payload;
                if (! in_array($publication->kind, ['post', 'story', 'chat'], true)) {
                    return $this->fail($publication, 'invalid_kind');
                }
                if (in_array($publication->kind, ['post', 'story'], true)
                    && (($payload['privacy'] ?? 'all') !== 'all' || ! empty($payload['selected_user_ids']))) {
                    return $this->fail($publication, 'unsupported_privacy');
                }
                $types = $items->pluck('type')->unique();
                $maxItems = $publication->kind === 'post' && $types->first() === 'image' ? 10 : 1;
                $allowedTypes = $publication->kind === 'story' ? ['image', 'video'] : ['image', 'video', 'audio', 'document'];
                if ($types->count() !== 1 || ! in_array($types->first(), $allowedTypes, true) || $items->count() > $maxItems) {
                    return $this->fail($publication, 'invalid_attachments');
                }
                foreach ($items as $item) {
                    $output = $outputs[$item->id] ?? [];

                    if (! $this->validOutput($item, $output)) {
                        return $this->fail($publication, 'invalid_processed_output');
                    }
                }

                app(PublicationService::class)->assertMayPublish($actor, array_merge($payload, ['kind' => $publication->kind]));
                $safety = app(SafetyService::class);
                $publication->update(['status' => 'publishing', 'error' => null]);

                $entity = match ($publication->kind) {
                    'post' => $this->createPost($publication, $actor, $safety),
                    'story' => $this->createStory($publication, $actor),
                    'chat' => $this->createMessage($publication, $actor),
                };

                if (! $entity) {
                    return $publication;
                }

                foreach ($items as $item) {
                    $output = $outputs[$item->id];
                    $entity->media()->create(array_merge(Arr::only($output, [
                        'source_path', 'disk', 'mime', 'extension', 'size',
                        'thumbnail_path', 'thumbnail_disk', 'thumbnail_size', 'lqip_base64',
                    ]), [
                        'type' => $item->type,
                        'status' => MediaStatus::PROCESSED,
                        'order' => $item->position,
                        'metadata' => $this->mediaMetadata($publication, $item, $output),
                    ]));
                }

                $publication->update([
                    'status' => 'published',
                    'error' => null,
                    'published_at' => now(),
                    'result' => [
                        'id' => $entity->id,
                        'type' => $publication->kind === 'chat' ? 'message' : $publication->kind,
                        'url' => match ($publication->kind) {
                            'post' => $entity->url,
                            'story' => $entity->story->url,
                            'chat' => url('/messenger/c/'.$entity->chat_uuid),
                        },
                    ],
                ]);

                DeliverPublicationEvent::record($publication, 'domain_created');
                DeliverPublicationEvent::record($publication, 'owner_completion', $actor->id);
                if ($entity instanceof Post) {
                    DeliverPublicationEvent::record($publication, 'public_timeline');
                }
                if ($entity instanceof Message) {
                    foreach ($entity->chat->participants()->where('user_id', '!=', $actor->id)->pluck('user_id')->unique() as $recipientId) {
                        DeliverPublicationEvent::record($publication, 'chat_recipient', $recipientId);
                    }
                } else {
                    foreach (User::active()->where('id', '!=', $actor->id)->whereIn('username', $entity->getMentions() ?: [])->pluck('id') as $recipientId) {
                        DeliverPublicationEvent::record($publication, $publication->kind.'_mention', $recipientId);
                    }
                }

                return $publication;
            });
        });
    }

    public function replacePublishedMedia(MediaPublication $publication, MediaPublicationItem $item, array $output): Media
    {
        if ($publication->status !== 'published' || ! $this->validOutput($item, $output)) {
            throw new RuntimeException('Published media replacement is invalid.');
        }

        $media = Media::query()
            ->where('metadata->publication_id', $publication->id)
            ->where('metadata->publication_item_id', $item->id)
            ->lockForUpdate()
            ->first();

        if (! $media) {
            throw new RuntimeException('Published media row was not found for replacement.');
        }

        $media->forceFill(array_merge(Arr::only($output, [
            'source_path', 'disk', 'mime', 'extension', 'size',
            'thumbnail_path', 'thumbnail_disk', 'thumbnail_size', 'lqip_base64',
        ]), [
            'type' => $item->type,
            'status' => MediaStatus::PROCESSED,
            'order' => $item->position,
            'metadata' => $this->mediaMetadata($publication, $item, $output, $media->metadata ?? []),
        ]))->save();

        DB::afterCommit(fn () => $this->broadcastMediaReplacement((int) $media->id, $publication->kind, $publication->user_id));

        return $media;
    }

    private function readyOutputs(MediaPublication $publication, $items): ?array
    {
        $outputs = [];

        foreach ($items as $item) {
            if ($item->status === 'processed' && ! empty($item->output)) {
                $outputs[$item->id] = $item->output;
                continue;
            }

            $rawOutput = $this->directUploadedOutput($publication, $item);

            if ($rawOutput) {
                $outputs[$item->id] = $rawOutput;
                continue;
            }

            return null;
        }

        return $outputs;
    }

    private function directUploadedOutput(MediaPublication $publication, MediaPublicationItem $item): ?array
    {
        $items = $publication->items;
        $singleItem = $items->count() === 1;
        $finalRetryItem = $publication->status === 'uploading' && $items->every(
            fn($candidate) => $candidate->id === $item->id
                || ($candidate->status === 'processed' && ! empty($candidate->output))
        );

        // Normal multi-item publication waits for each worker. The fast path is
        // allowed on retry only when every sibling already has a verified output.
        if ((! $singleItem && ! $finalRetryItem)
            || ! in_array($item->type, ['image', 'video', 'audio', 'document'], true)
            || ! in_array($item->status, ['uploaded', 'processing'], true)) {
            return null;
        }

        $output = app(PublicationMediaProcessor::class)->process($item);
        $item->update(['output' => $output, 'status' => 'processed', 'progress' => 100]);

        return $output;
    }

    private function validOutput(MediaPublicationItem $item, array $output): bool
    {
        return filled($output['source_path'] ?? null)
            && filled($output['disk'] ?? null)
            && filled($output['extension'] ?? null)
            && (int) ($output['size'] ?? 0) > 0
            && $this->validMimeForType((string) ($output['mime'] ?? ''), $item->type);
    }

    private function validMimeForType(string $mime, string $type): bool
    {
        return match ($type) {
            'image' => str_starts_with($mime, 'image/'),
            'video' => str_starts_with($mime, 'video/'),
            'audio' => str_starts_with($mime, 'audio/') || $mime === 'video/webm',
            'document' => str_starts_with($mime, 'application/') || $mime === 'text/plain',
            default => false,
        };
    }

    private function mediaMetadata(MediaPublication $publication, MediaPublicationItem $item, array $output, array $base = []): array
    {
        $metadata = array_merge($base, $item->metadata ?? [], $output['metadata'] ?? [], [
            'publication_id' => $publication->id,
            'publication_item_id' => $item->id,
        ]);

        $metadata['processing_state'] = (string) data_get($output, 'metadata.processing_state', 'processed');
        $metadata['processing_progress'] = (int) data_get($output, 'metadata.processing_progress', 100);

        return $metadata;
    }

    private function broadcastMediaReplacement(int $mediaId, string $kind, int $userId): void
    {
        $media = Media::with('mediaable')->find($mediaId);

        if (! $media) {
            return;
        }

        if ($kind === 'post' && $media->mediaable instanceof Post) {
            event(new MediaProcessedEvent($media, $userId));
            return;
        }

        if ($kind === 'chat' && $media->mediaable instanceof Message) {
            event(new MessageMediaReadyEvent($media->mediaable->loadMissing([
                'user', 'media', 'chat', 'participant', 'parent.user', 'parent.participant',
                'parent.media', 'reactions', 'linkSnapshot',
            ])));
        }
    }

    public static function asActor(User $actor, callable $callback): mixed
    {
        $driver = Auth::getDefaultDriver();
        $guard = Auth::guard($driver);
        $previous = $guard->user();
        $guard->setUser($actor);

        try {
            return $callback();
        } finally {
            $previous ? $guard->setUser($previous) : $guard->forgetUser();
            Auth::shouldUse($driver);
        }
    }

    public static function canContact(User $actor, User $recipient, ?string $permission = null): bool
    {
        if ($actor->status !== UserStatus::ACTIVE || $recipient->status !== UserStatus::ACTIVE
            || (new BlockService($actor, $recipient))->blockedAny()) {
            return false;
        }
        if ($permission === null || $actor->id === $recipient->id) {
            return true;
        }

        return match ($recipient->permitSettings?->getAttribute($permission) ?? PrivacyPermit::ALL) {
            PrivacyPermit::ALL => true,
            PrivacyPermit::FOLLOWERS, PrivacyPermit::APPROVED => (new FollowService($actor, $recipient))->isFollowing(),
            default => false,
        };
    }

    private function createPost(MediaPublication $publication, User $actor, SafetyService $safety): ?Post
    {
        $content = $this->content($publication);
        $blacklist = app(BlacklistService::class);
        if ($blacklist->isEmailBlacklisted($actor->email)) {
            $this->fail($publication, 'content_rejected');
            return null;
        }

        app(CensorService::class)->setUser($actor)->censor($content);
        // The existing censor records a blacklist entry without changing User.status.
        if ($blacklist->isEmailBlacklisted($actor->email) || $actor->fresh()?->status !== UserStatus::ACTIVE) {
            $this->fail($publication, 'content_rejected');
            return null;
        }

        $payload = $publication->payload;
        $post = new Post([
            'user_id' => $actor->id,
            'content' => $content,
            'type' => $publication->items->first()->type,
            'status' => PostStatus::ACTIVE,
            'is_ai_generated' => (bool) data_get($payload, 'marks.is_ai_generated', false),
            'is_sensitive' => (bool) data_get($payload, 'marks.is_sensitive', false),
        ]);
        if ($post->content !== '') {
            $post->text_language = $post->getContentLanguage();
        }
        if (! empty($payload['quoted_post_id'])) {
            $quote = Post::activeById($payload['quoted_post_id'])->with('user')->lockForUpdate()->first();
            if (! $quote?->user || ! self::canContact($actor, $quote->user)) {
                $this->fail($publication, 'quoted_post_unavailable');
                return null;
            }
            $post->quote_post_id = $quote->id;
            $post->is_quoting = true;
            $quote->increment('quotes_count');
        }
        $post->save();
        app(TopicExtractionService::class)->syncPostTopics($post);
        $actor->increment('publications_count');
        $safety->recordPostCreated($actor);

        return $post;
    }

    private function createStory(MediaPublication $publication, User $actor): ?StoryFrame
    {
        $story = $actor->story()->lockForUpdate()->first();
        if (($story?->activeFramesCount() ?? 0) >= (int) config('story.max_frames_per_story', 10)) {
            $this->fail($publication, 'story_frame_limit');
            return null;
        }
        $story ??= $actor->story()->create(['story_uuid' => (string) Str::uuid()]);
        $item = $publication->items->first();
        $duration = $item->type === 'video'
            ? max(1, (int) ceil((float) data_get($item->output, 'metadata.duration_seconds', data_get($item->metadata, 'duration_seconds', 1))))
            : max(1, (int) config('story.image_clip_size', 5));
        $publishedAt = now();
        $story->update(['updated_at' => $publishedAt]);

        return $story->frames()->create([
            'content' => $this->content($publication),
            'type' => $item->type,
            'status' => StoryStatus::ACTIVE,
            'privacy' => StoryPrivacy::ALL,
            'duration_seconds' => $duration,
            'created_at' => $publishedAt,
            'expires_at' => $publishedAt->copy()->addHours(max(1, (int) config('story.expire_after_hours', 24))),
            'meta' => $item->type === 'video' ? ['video' => [
                'clip_start_seconds' => (float) data_get($publication->payload, 'clip_start_seconds', 0),
                'duration_seconds' => $duration,
            ]] : [],
        ]);
    }

    private function createMessage(MediaPublication $publication, User $actor): ?Message
    {
        $chat = Chat::participatedChats()->where('chat_id', data_get($publication->payload, 'chat_id'))->lockForUpdate()->first();
        $participant = $chat?->participants()->where('user_id', $actor->id)->lockForUpdate()->first();
        if (! $participant) {
            $this->fail($publication, 'chat_membership_required');
            return null;
        }
        if ($chat->type->isDirect()) {
            $others = $chat->participants()->where('user_id', '!=', $actor->id)->with('user.permitSettings')->lockForUpdate()->get();
            $recipient = $others->count() === 1 ? $others->first()->user : null;
            if (! $recipient || ! self::canContact($actor, $recipient, 'direct_messages')) {
                $this->fail($publication, 'chat_recipient_unavailable');
                return null;
            }
        }
        $parentId = data_get($publication->payload, 'parent_id');
        if ($parentId && ! $chat->messages()->excludeDeleted()->where('is_deleted', false)->whereKey($parentId)->lockForUpdate()->first()) {
            $this->fail($publication, 'invalid_parent');
            return null;
        }
        $content = $this->content($publication);
        $message = $chat->messages()->create([
            'user_id' => $actor->id,
            'participant_id' => $participant->id,
            'chat_uuid' => $chat->chat_id,
            'parent_id' => $parentId,
            'content' => $content,
            'text_language' => $content === '' ? '' : detect_text_language($content),
            'type' => match ($publication->items->first()->type) {
                'video' => 'video_circle',
                'audio' => 'audio',
                'document' => 'document',
                default => 'image',
            },
        ]);
        $participant->update(['last_read_message_id' => $message->id, 'last_read_at' => now()]);
        $chat->update(['last_activity' => now()]);
        if ($chat->type->isDirect()) {
            HiddenChat::where('chat_id', $chat->id)->delete();
        }

        return $message;
    }

    private function content(MediaPublication $publication): string
    {
        return e(normalize_nls((string) data_get($publication->payload, 'content', '')));
    }

    private function fail(MediaPublication $publication, string $error): MediaPublication
    {
        $publication->update(['status' => 'failed', 'error' => $error]);

        return $publication;
    }
}
