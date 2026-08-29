/**
 * Push Notification Manager (FCM / PushKit)
 * High-priority call and message notifications
 * Ensures instant delivery even when app is backgrounded
 */

namespace App\Services\Push;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationManager
{
    private $fcmApiKey;
    private $fcmApiUrl = 'https://fcm.googleapis.com/v1/projects/{projectId}/messages:send';

    public function __construct()
    {
        $this->fcmApiKey = config('services.firebase.server_key');
    }

    /**
     * Send high-priority call notification via FCM
     * Uses highest priority for instant delivery
     */
    public function sendCallNotification(
        string $userId,
        string $callerId,
        string $callerName,
        string $callType = 'voice'
    ): bool {
        try {
            $deviceTokens = $this->getUserDeviceTokens($userId);

            if (empty($deviceTokens)) {
                Log::warning("No device tokens for user: {$userId}");
                return false;
            }

            foreach ($deviceTokens as $token) {
                $this->sendFCMMessage($token, [
                    'title' => "{$callerName} is calling...",
                    'body' => ucfirst($callType) . " call incoming",
                    'event' => 'call.incoming',
                    'call_type' => $callType,
                    'caller_id' => $callerId,
                    'caller_name' => $callerName,
                    'call_id' => uniqid('call_'),
                    'timestamp' => now()->toIso8601String(),
                ], [
                    'priority' => 'high',
                    'ttl' => 60, // 60 seconds to answer
                    'mutable_content' => true,
                    'sound' => 'call_ringtone',
                    'badge' => '+1'
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send call notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send message notification
     */
    public function sendMessageNotification(
        string $userId,
        string $senderName,
        string $messagePreview,
        string $chatId
    ): bool {
        try {
            $deviceTokens = $this->getUserDeviceTokens($userId);

            if (empty($deviceTokens)) {
                return false;
            }

            foreach ($deviceTokens as $token) {
                $this->sendFCMMessage($token, [
                    'title' => $senderName,
                    'body' => substr($messagePreview, 0, 150),
                    'event' => 'message.received',
                    'chat_id' => $chatId,
                    'sender_name' => $senderName,
                    'timestamp' => now()->toIso8601String(),
                ], [
                    'priority' => 'high',
                    'ttl' => 3600,
                    'sound' => 'message_tone',
                    'badge' => '+1'
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send message notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send FCM message using HTTP v1 API
     */
    private function sendFCMMessage(
        string $deviceToken,
        array $data = [],
        array $options = []
    ): bool {
        try {
            $payload = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $data['title'] ?? '',
                        'body' => $data['body'] ?? '',
                    ],
                    'data' => array_filter($data, fn($k) => !in_array($k, ['title', 'body']), ARRAY_FILTER_USE_KEY),
                    'android' => [
                        'priority' => $options['priority'] ?? 'high',
                        'notification' => [
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'sound' => $options['sound'] ?? 'default',
                            'channel_id' => 'call_notifications',
                            'priority' => 'max'
                        ],
                        'ttl' => $options['ttl'] . 's'
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => '10',
                            'apns-expiration' => (now()->addSeconds($options['ttl'] ?? 3600))->timestamp
                        ],
                        'payload' => [
                            'aps' => [
                                'alert' => [
                                    'title' => $data['title'] ?? '',
                                    'body' => $data['body'] ?? '',
                                ],
                                'sound' => $options['sound'] ?? 'default',
                                'badge' => $options['badge'] ?? 0,
                                'mutable-content' => true,
                                'interruption-level' => $options['priority'] === 'high' ? 'critical' : 'active'
                            ]
                        ]
                    ],
                    'webpush' => [
                        'headers' => [
                            'TTL' => $options['ttl'] ?? 3600,
                            'Urgency' => $options['priority'] === 'high' ? 'high' : 'normal'
                        ],
                        'notification' => [
                            'title' => $data['title'] ?? '',
                            'body' => $data['body'] ?? '',
                            'icon' => '/img/app-icon-192x192.png',
                            'badge' => '/img/badge-72x72.png',
                            'tag' => 'zulors-notification',
                            'requireInteraction' => $data['event'] === 'call.incoming'
                        ]
                    ]
                ]
            ];

            $response = Http::withToken($this->getAccessToken())
                ->post($this->getProjectMessagesUrl(), $payload);

            if (!$response->successful()) {
                Log::error('FCM send failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('FCM request failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user device tokens from database
     */
    private function getUserDeviceTokens(string $userId): array
    {
        return \App\Models\UserDeviceToken::where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();
    }

    /**
     * Get Firebase access token
     */
    private function getAccessToken(): string
    {
        $credentialsPath = storage_path('app/firebase-credentials.json');

        if (!file_exists($credentialsPath)) {
            throw new \Exception('Firebase credentials not found');
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);

        $token = \Google\Auth\CredentialsLoader::makeCredentials(
            $credentials,
            ['https://www.googleapis.com/auth/firebase.messaging']
        )->fetchAccessToken();

        return $token['access_token'];
    }

    /**
     * Get Firebase project messages URL
     */
    private function getProjectMessagesUrl(): string
    {
        $projectId = config('services.firebase.project_id');
        return str_replace('{projectId}', $projectId, $this->fcmApiUrl);
    }

    /**
     * Register device token for user
     */
    public function registerDeviceToken(string $userId, string $token, string $platform = 'web'): void
    {
        \App\Models\UserDeviceToken::updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $userId,
                'platform' => $platform,
                'is_active' => true,
                'last_used_at' => now()
            ]
        );
    }

    /**
     * Unregister device token
     */
    public function unregisterDeviceToken(string $token): void
    {
        \App\Models\UserDeviceToken::where('token', $token)->update(['is_active' => false]);
    }
}
