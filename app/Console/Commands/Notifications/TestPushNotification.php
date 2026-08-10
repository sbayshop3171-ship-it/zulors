<?php

namespace App\Console\Commands\Notifications;

use App\Models\User;
use App\Models\UserPushToken;
use App\Services\Notifications\FirebaseCloudMessagingService;
use Illuminate\Console\Command;

class TestPushNotification extends Command
{
    protected $signature = 'notifications:test-push
        {user? : User id, email, or username. Omit to use the latest active FCM token owner.}
        {--dry-run : Validate the target and payload without sending to Firebase.}';

    protected $description = 'Send a small FCM test push notification to a Zulors user device.';

    public function handle(FirebaseCloudMessagingService $firebase): int
    {
        if(! config('notifications.push.enabled')) {
            $this->error('Push notifications are disabled.');

            return self::FAILURE;
        }

        $user = $this->resolveUser($this->argument('user'));

        if(! $user) {
            $this->error('No matching user with an active FCM token was found.');

            return self::FAILURE;
        }

        $tokens = $user->pushTokens()
            ->active()
            ->where('provider', 'fcm')
            ->latest('id')
            ->get();

        if($tokens->isEmpty()) {
            $this->error('The selected user has no active FCM push token.');

            return self::FAILURE;
        }

        $this->info("Push target user: {$user->id} / @{$user->username}");
        $this->info('Active FCM token(s): ' . $tokens->count());

        if($this->option('dry-run')) {
            $this->info('Dry-run OK. Payload is ready and no Firebase request was sent.');

            return self::SUCCESS;
        }

        $message = $this->testMessage();
        $sentCount = 0;

        foreach($tokens as $token) {
            if($firebase->sendToToken($token, $message)) {
                $sentCount++;
            }
        }

        if($sentCount === 0) {
            $this->error('Firebase accepted no test push notifications. Check credentials and device tokens.');

            return self::FAILURE;
        }

        $this->info("Sent {$sentCount} test push notification(s).");

        return self::SUCCESS;
    }

    private function resolveUser(?string $lookup): ?User
    {
        if(blank($lookup)) {
            return UserPushToken::query()
                ->active()
                ->where('provider', 'fcm')
                ->whereHas('user')
                ->with('user')
                ->latest('id')
                ->first()
                ?->user;
        }

        return User::query()
            ->where(function ($query) use ($lookup) {
                if(ctype_digit($lookup)) {
                    $query->whereKey((int) $lookup);
                }

                $query->orWhere('email', $lookup)
                    ->orWhere('username', $lookup);
            })
            ->first();
    }

    private function testMessage(): array
    {
        return [
            'data' => [
                'title' => config('app.name', 'Zulors'),
                'body' => 'Push notifications are working on this device.',
                'type' => 'test.notification',
                'url' => url('/notifications'),
                'source' => 'push',
                'channel_id' => 'zulors_system',
                'sent_at' => now()->toIso8601String(),
            ],
            'android' => [
                'priority' => 'high',
            ],
        ];
    }
}
