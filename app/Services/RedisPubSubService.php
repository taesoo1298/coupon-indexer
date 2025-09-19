<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class RedisPubSubService
{
    private string $channel;

    public function __construct()
    {
        $this->channel = config('coupon-indexer.events.channel', 'coupon_events');
    }

    /**
     * Redis Pub/Sub 채널에 이벤트 발행
     */
    public function publishEvent(string $eventType, array $eventData): void
    {
        try {
            $message = [
                'event_type' => $eventType,
                'data' => $eventData,
                'published_at' => now()->toISOString(),
            ];

            Redis::connection('pubsub')->publish(
                $this->channel,
                json_encode($message)
            );

            Log::info("Event published to Redis Pub/Sub", [
                'channel' => $this->channel,
                'event_type' => $eventType,
                'data_size' => strlen(json_encode($eventData)),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to publish event to Redis Pub/Sub", [
                'channel' => $this->channel,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Redis Pub/Sub 채널 구독
     */
    public function subscribe(callable $callback): void
    {
        Redis::connection('pubsub')->subscribe([$this->channel], function ($message, $channel) use ($callback) {
            try {
                $decodedMessage = json_decode($message, true);

                if (!$decodedMessage) {
                    Log::warning("Invalid message format received from Redis Pub/Sub", [
                        'channel' => $channel,
                        'message' => $message,
                    ]);
                    return;
                }

                Log::info("Event received from Redis Pub/Sub", [
                    'channel' => $channel,
                    'event_type' => $decodedMessage['event_type'] ?? 'unknown',
                ]);

                $callback($decodedMessage, $channel);
            } catch (\Exception $e) {
                Log::error("Error processing Redis Pub/Sub message", [
                    'channel' => $channel,
                    'message' => $message,
                    'error' => $e->getMessage(),
                ]);
            }
        });
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
        try {
            $redis = Redis::connection('pubsub');

            // 채널 구독자 수 확인
            $subscribersCount = $redis->pubsub('numsub', $this->channel);

            return [
                'channel' => $this->channel,
                'subscribers' => $subscribersCount[$this->channel] ?? 0,
                'status' => 'active',
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get Redis Pub/Sub channel info", [
                'channel' => $this->channel,
                'error' => $e->getMessage(),
            ]);

            return [
                'channel' => $this->channel,
                'subscribers' => 0,
                'status' => 'error',
                'error' => $e->getMessage(),
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
    public function testPublish(): bool
    {
        try {
            $testData = [
                'test' => true,
                'timestamp' => now()->toISOString(),
            ];

            $this->publishEvent('test_event', $testData);
            return true;
        } catch (\Exception $e) {
            Log::error("Redis Pub/Sub test failed", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
