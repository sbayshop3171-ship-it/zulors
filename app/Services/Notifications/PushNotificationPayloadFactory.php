<?php

namespace App\Services\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ReflectionMethod;

class PushNotificationPayloadFactory
{
    public function make(object $notifiable, Notification $notification): array
    {
        $customPayload = $this->customPayload($notifiable, $notification);

        if(isset($customPayload['message']) && is_array($customPayload['message'])) {
            return $customPayload['message'];
        }

        $data = $this->databasePayload($notifiable, $notification);
        $type = (string) ($notification->notificationType ?? $customPayload['type'] ?? 'notification');
        $url = (string) ($customPayload['url'] ?? $customPayload['link'] ?? $this->destinationUrl($type, $data));
        $title = (string) ($customPayload['title'] ?? config('app.name', 'Zulors'));
        $body = (string) ($customPayload['body'] ?? $this->body($notifiable, $data, $type));

        return [
            'data' => $this->stringData(array_merge([
                'title' => Str::limit($title, 80),
                'body' => Str::limit($body, 180),
                'type' => $type,
                'url' => $url,
                'source' => 'push',
                'channel_id' => $customPayload['channel_id'] ?? $this->channelId($type),
            ], Arr::get($customPayload, 'data', []))),
            'android' => array_merge([
                'priority' => 'high',
            ], Arr::get($customPayload, 'android', [])),
        ];
    }

    private function customPayload(object $notifiable, Notification $notification): array
    {
        if(! method_exists($notification, 'toPush')) {
            return [];
        }

        $payload = $notification->toPush($notifiable);

        return is_array($payload) ? $payload : [];
    }

    private function databasePayload(object $notifiable, Notification $notification): array
    {
        if(! method_exists($notification, 'toDatabase')) {
            return [];
        }

        $method = new ReflectionMethod($notification, 'toDatabase');
        $payload = $method->getNumberOfRequiredParameters() === 0
            ? $notification->toDatabase()
            : $notification->toDatabase($notifiable);

        return is_array($payload) ? $payload : [];
    }

    private function body(object $notifiable, array $data, string $type): string
    {
        $group = Arr::get($data, 'message_group');
        $key = Arr::get($data, 'message_key');
        $params = Arr::get($data, 'message_params', []);
        $locale = $notifiable->language ?? app()->getLocale();

        $message = ($group && $key)
            ? __("notifications.{$group}.{$key}", $params, $locale)
            : __('notifications.subjects.' . Str::of($type)->after('.')->replace('-', '_'), [], $locale);

        $actorName = Arr::get($data, 'actor.name')
            ?? Arr::get($data, 'actor.username')
            ?? Arr::get($data, 'entity.username');

        if($actorName) {
            return trim($actorName . ' ' . $message);
        }

        return $message;
    }

    private function destinationUrl(string $type, array $data): string
    {
        $path = '/notifications';
        $postHashId = Arr::get($data, 'entity.hash_id');
        $username = Arr::get($data, 'actor.username') ?? Arr::get($data, 'entity.username');

        if(Str::startsWith($type, ['post.', 'comment.']) && $postHashId) {
            $path = "/publication/{$postHashId}";
        }
        elseif(Str::startsWith($type, 'user.') && $username) {
            $path = "/@{$username}";
        }
        elseif(Str::startsWith($type, 'important.') || Str::startsWith($type, 'wallet.')) {
            $path = '/notifications/important';
        }

        return url($path);
    }

    private function channelId(string $type): string
    {
        if(Str::startsWith($type, 'call.')) {
            return 'zulors_calls';
        }

        if(Str::startsWith($type, 'chat.')) {
            return 'zulors_messages';
        }

        if(Str::startsWith($type, ['important.', 'wallet.'])) {
            return 'zulors_system';
        }

        return 'zulors_activity';
    }

    private function stringData(array $data): array
    {
        return collect($data)
            ->filter(fn ($value) => ! is_null($value))
            ->map(fn ($value) => is_scalar($value) ? (string) $value : json_encode($value))
            ->all();
    }
}
