/**
 * Non-Blocking WebSocket Event Broadcaster
 * Ensures real-time events are sent instantly without waiting for heavy backend operations
 * Achieves sub-100ms broadcast latency
 */

namespace App\Services\RealTime;

use Illuminate\Broadcasting\Broadcasters\RedisBroadcaster;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class NonBlockingBroadcaster {

	private $broadcaster;
	private $redisClient;
	private const BROADCAST_PRIORITY_HIGH = 'high';
	private const BROADCAST_PRIORITY_NORMAL = 'normal';

	public function __construct() {
		$this->broadcaster = app('broadcaster');
		$this->redisClient = app('redis')->connection();
	}

	/**
	 * Broadcast event instantly without waiting for middleware/database
	 * Uses Redis pub/sub for immediate delivery
	 */
	public function broadcastInstant(ShouldBroadcastNow $event, string $priority = self::BROADCAST_PRIORITY_NORMAL) {
		try {
			$startTime = microtime(true);

			// Get channels from event
			$channels = $event->broadcastOn();
			$eventData = $event->broadcastWith();
			$eventName = $event->broadcastAs();

			// Prepare broadcast payload
			$payload = [
				'event' => $eventName,
				'data' => $eventData,
				'server_time' => now()->toIso8601String(),
				'broadcast_time_ms' => 0
			];

			// Send to Redis immediately (bypasses Eloquent observers)
			foreach ($channels as $channel) {
				$this->publishToRedis(
					$channel->name,
					$payload,
					$priority
				);
			}

			$elapsedMs = (microtime(true) - $startTime) * 1000;

			// Log for monitoring
			if ($elapsedMs > 50) {
				Log::info("Slow broadcast detected: {$eventName} took {$elapsedMs}ms", [
					'event' => $eventName,
					'channels' => count($channels),
					'elapsed_ms' => $elapsedMs
				]);
			}

			return true;
		} catch (\Exception $e) {
			Log::error('Broadcast failed: ' . $e->getMessage(), [
				'event' => class_basename($event)
			]);
			return false;
		}
	}

	/**
	 * Publish directly to Redis without Laravel's serialization overhead
	 */
	private function publishToRedis(string $channel, array $payload, string $priority) {
		// Optimize payload for transmission
		$optimizedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		// Determine Redis priority queue
		$redisKey = $priority === self::BROADCAST_PRIORITY_HIGH
			? "broadcast:high:{$channel}"
			: "broadcast:normal:{$channel}";

		// Push to Redis Pub/Sub
		$this->redisClient->publish($channel, $optimizedPayload);

		// Also queue for persistence if needed
		$this->redisClient->lpush($redisKey, $optimizedPayload);
	}

	/**
	 * Dispatch background queue job after instant broadcast
	 * Heavy operations (DB, external APIs) run async
	 */
	public function broadcastThenProcess(
		ShouldBroadcastNow $event,
		callable $backgroundJob,
		string $queue = 'default'
	) {
		// 1. Broadcast instantly (0-10ms)
		$this->broadcastInstant($event, self::BROADCAST_PRIORITY_HIGH);

		// 2. Dispatch heavy work to background queue (non-blocking)
		Bus::dispatch($backgroundJob)->onQueue($queue);

		return true;
	}

	/**
	 * Broadcast with acknowledgment tracking
	 * Track which clients received the event
	 */
	public function broadcastWithAck(
		ShouldBroadcastNow $event,
		array $targetUsers = [],
		int $timeoutMs = 5000
	): array {
		$eventId = uniqid('evt_', true);
		$channels = $event->broadcastOn();
		$eventData = $event->broadcastWith();

		// Add event ID for tracking
		$eventData['event_id'] = $eventId;
		$eventData['requires_ack'] = true;

		$payload = [
			'event' => $event->broadcastAs(),
			'data' => $eventData
		];

		// Store in Redis with TTL for tracking
		$this->redisClient->setex(
			"broadcast_ack:{$eventId}",
			($timeoutMs / 1000),
			json_encode([
				'expected_receivers' => count($targetUsers),
				'received_acks' => 0,
				'created_at' => now()->timestamp
			])
		);

		// Broadcast with priority
		foreach ($channels as $channel) {
			$this->publishToRedis($channel->name, $payload, self::BROADCAST_PRIORITY_HIGH);
		}

		return [
			'event_id' => $eventId,
			'channels' => count($channels),
			'target_users' => count($targetUsers),
			'timeout_ms' => $timeoutMs
		];
	}

	/**
	 * Record acknowledgment from client
	 */
	public function recordAck(string $eventId, int $userId) {
		$ackKey = "broadcast_ack:{$eventId}";
		$ackData = json_decode($this->redisClient->get($ackKey), true);

		if ($ackData) {
			$ackData['received_acks']++;
			$ackData['receivers'][] = [
				'user_id' => $userId,
				'received_at' => now()->timestamp
			];

			// Update in Redis
			$this->redisClient->set(
				$ackKey,
				json_encode($ackData),
				'EX',
				10 // 10 second TTL
			);
		}
	}

	/**
	 * Get broadcast metrics for monitoring
	 */
	public function getBroadcastMetrics(): array {
		$highPriorityCount = $this->redisClient->keys('broadcast:high:*');
		$normalPriorityCount = $this->redisClient->keys('broadcast:normal:*');

		return [
			'high_priority_pending' => count($highPriorityCount),
			'normal_priority_pending' => count($normalPriorityCount),
			'timestamp' => now()->toIso8601String()
		];
	}
}
