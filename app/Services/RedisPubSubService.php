<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class RedisPubSubService
{
    private string $channel;
    private bool $isConnected = false;
    private int $reconnectAttempts = 0;
    private const MAX_RECONNECT_ATTEMPTS = 3;

    public function __construct()
    {
        $this->channel = config('coupon-indexer.events.channel', 'coupon_events');
        $this->checkConnection();
    }

    /**
     * Redis 연결 상태 확인
     */
    private function checkConnection(): bool
    {
        try {
            $redis = Redis::connection('pubsub');
            $redis->ping();
            $this->isConnected = true;
            $this->reconnectAttempts = 0;
            return true;
        } catch (\Exception $e) {
            $this->isConnected = false;
            Log::warning("Redis PubSub connection failed", [
                'error' => $e->getMessage(),
                'attempt' => $this->reconnectAttempts,
            ]);
            return false;
        }
    }

    /**
     * 연결 재시도
     */
    private function reconnect(): bool
    {
        if ($this->reconnectAttempts >= self::MAX_RECONNECT_ATTEMPTS) {
            Log::error("Redis PubSub max reconnect attempts reached");
            return false;
        }

        $this->reconnectAttempts++;
        sleep(1); // 1초 대기 후 재시도

        return $this->checkConnection();
    }

    /**
     * Redis Pub/Sub 채널에 이벤트 발행
     */
    public function publishEvent(string $eventType, array $eventData): bool
    {
        if (!$this->isConnected && !$this->reconnect()) {
            Log::error("Cannot publish event - Redis connection failed", [
                'event_type' => $eventType,
            ]);
            return false;
        }

        try {
            $message = [
                'event_type' => $eventType,
                'data' => $eventData,
                'published_at' => now()->toISOString(),
                'publisher' => config('app.name', 'coupon-indexer'),
            ];

            $published = Redis::connection('pubsub')->publish(
                $this->channel,
                json_encode($message)
            );

            if ($published > 0) {
                Log::info("Event published to Redis Pub/Sub", [
                    'channel' => $this->channel,
                    'event_type' => $eventType,
                    'subscribers' => $published,
                    'data_size' => strlen(json_encode($eventData)),
                ]);
                return true;
            } else {
                Log::warning("Event published but no subscribers", [
                    'channel' => $this->channel,
                    'event_type' => $eventType,
                ]);
                return true; // 발행은 성공했지만 구독자가 없음
            }

        } catch (\Exception $e) {
            $this->isConnected = false;

            Log::error("Failed to publish event to Redis Pub/Sub", [
                'channel' => $this->channel,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Redis Pub/Sub 채널 구독 (비블로킹)
     */
    public function subscribe(callable $callback, int $timeout = 1): void
    {
        if (!$this->isConnected && !$this->reconnect()) {
            Log::error("Cannot subscribe - Redis connection failed");
            return;
        }

        try {
            $redis = Redis::connection('pubsub');

            // 논블로킹 방식으로 메시지 처리
            $redis->subscribe([$this->channel], function ($message, $channel) use ($callback) {
                try {
                    $decodedMessage = json_decode($message, true);

                    if (!$decodedMessage || !is_array($decodedMessage)) {
                        Log::warning("Invalid message format received from Redis Pub/Sub", [
                            'channel' => $channel,
                            'message' => substr($message, 0, 100), // 처음 100자만 로깅
                        ]);
                        return;
                    }

                    // 메시지 유효성 검사
                    if (!isset($decodedMessage['event_type'])) {
                        Log::warning("Message missing event_type", [
                            'channel' => $channel,
                            'message_keys' => array_keys($decodedMessage),
                        ]);
                        return;
                    }

                    Log::info("Event received from Redis Pub/Sub", [
                        'channel' => $channel,
                        'event_type' => $decodedMessage['event_type'],
                        'published_at' => $decodedMessage['published_at'] ?? null,
                    ]);

                    // 콜백 실행 (에러 격리)
                    try {
                        $callback($decodedMessage, $channel);
                    } catch (\Exception $callbackError) {
                        Log::error("Callback execution failed", [
                            'channel' => $channel,
                            'event_type' => $decodedMessage['event_type'],
                            'callback_error' => $callbackError->getMessage(),
                        ]);
                    }

                } catch (\Exception $e) {
                    Log::error("Error processing Redis Pub/Sub message", [
                        'channel' => $channel,
                        'error' => $e->getMessage(),
                        'message_preview' => substr($message, 0, 100),
                    ]);
                }
            });

        } catch (\Exception $e) {
            $this->isConnected = false;

            Log::error("Failed to subscribe to Redis Pub/Sub", [
                'channel' => $this->channel,
                'error' => $e->getMessage(),
            ]);

            // 재연결 시도
            if ($this->reconnect()) {
                Log::info("Reconnected to Redis, retrying subscription");
                $this->subscribe($callback, $timeout);
            }
        }
    }

    /**
     * 특정 채널에서 구독 해제
     */
    public function unsubscribe(?array $channels = null): void
    {
        $channelsToUnsubscribe = $channels ?? [$this->channel];

        Redis::connection('pubsub')->unsubscribe($channelsToUnsubscribe);

        Log::info("Unsubscribed from Redis Pub/Sub channels", [
            'channels' => $channelsToUnsubscribe,
        ]);
    }

    /**
     * 채널 상태 확인
     */
    public function getChannelInfo(): array
    {
        if (!$this->checkConnection()) {
            return [
                'channel' => $this->channel,
                'subscribers' => 0,
                'status' => 'disconnected',
                'connected' => false,
                'reconnect_attempts' => $this->reconnectAttempts,
            ];
        }

        try {
            $redis = Redis::connection('pubsub');

            // 채널 구독자 수 확인
            $result = $redis->pubsub('numsub', $this->channel);
            $subscribersCount = 0;

            if (is_array($result)) {
                $subscribersCount = $result[$this->channel] ?? 0;
            }

            // Redis 서버 정보
            $info = $redis->info();

            return [
                'channel' => $this->channel,
                'subscribers' => $subscribersCount,
                'status' => 'active',
                'connected' => $this->isConnected,
                'reconnect_attempts' => $this->reconnectAttempts,
                'redis_version' => $info['redis_version'] ?? 'unknown',
                'uptime' => $info['uptime_in_seconds'] ?? 0,
                'memory_usage' => $info['used_memory_human'] ?? '0',
            ];
        } catch (\Exception $e) {
            $this->isConnected = false;

            Log::error("Failed to get Redis Pub/Sub channel info", [
                'channel' => $this->channel,
                'error' => $e->getMessage(),
            ]);

            return [
                'channel' => $this->channel,
                'subscribers' => 0,
                'status' => 'error',
                'connected' => false,
                'error' => $e->getMessage(),
                'reconnect_attempts' => $this->reconnectAttempts,
            ];
        }
    }

    /**
     * 채널 패턴 기반 구독
     */
    public function psubscribe(string $pattern, callable $callback): void
    {
        Redis::connection('pubsub')->psubscribe([$pattern], function ($message, $channel, $pattern) use ($callback) {
            try {
                $decodedMessage = json_decode($message, true);

                if (!$decodedMessage) {
                    Log::warning("Invalid message format received from Redis pattern subscription", [
                        'channel' => $channel,
                        'pattern' => $pattern,
                        'message' => $message,
                    ]);
                    return;
                }

                Log::info("Event received from Redis pattern subscription", [
                    'channel' => $channel,
                    'pattern' => $pattern,
                    'event_type' => $decodedMessage['event_type'] ?? 'unknown',
                ]);

                $callback($decodedMessage, $channel, $pattern);
            } catch (\Exception $e) {
                Log::error("Error processing Redis pattern subscription message", [
                    'channel' => $channel,
                    'pattern' => $pattern,
                    'message' => $message,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * 메시지 발행 테스트
     */
    public function testPublish(): array
    {
        $result = [
            'success' => false,
            'connection_status' => $this->isConnected,
            'channel' => $this->channel,
            'error' => null,
            'test_data' => null,
        ];

        try {
            $testData = [
                'test' => true,
                'timestamp' => now()->toISOString(),
                'test_id' => uniqid('test_'),
            ];

            $published = $this->publishEvent('test_event', $testData);

            $result['success'] = $published;
            $result['test_data'] = $testData;

            if ($published) {
                Log::info("Redis Pub/Sub test successful", [
                    'test_id' => $testData['test_id'],
                    'channel' => $this->channel,
                ]);
            }

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();

            Log::error("Redis Pub/Sub test failed", [
                'error' => $e->getMessage(),
                'channel' => $this->channel,
            ]);
        }

        return $result;
    }

    /**
     * 연결 상태 반환
     */
    public function isConnected(): bool
    {
        return $this->isConnected && $this->checkConnection();
    }

    /**
     * 수동 재연결
     */
    public function forceReconnect(): bool
    {
        $this->isConnected = false;
        $this->reconnectAttempts = 0;
        return $this->reconnect();
    }

    /**
     * 연결 통계
     */
    public function getConnectionStats(): array
    {
        return [
            'connected' => $this->isConnected,
            'reconnect_attempts' => $this->reconnectAttempts,
            'max_attempts' => self::MAX_RECONNECT_ATTEMPTS,
            'channel' => $this->channel,
            'last_check' => now()->toISOString(),
        ];
    }
}
