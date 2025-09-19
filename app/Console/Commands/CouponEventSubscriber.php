<?php

namespace App\Console\Commands;

use App\Services\RedisPubSubService;
use App\Jobs\ProcessCouponEventJob;
use App\Models\CouponEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CouponEventSubscriber extends Command
{
    protected $signature = 'coupon:subscribe-events {--timeout=0 : Timeout in seconds (0 for infinite)}';
    protected $description = 'Subscribe to Redis Pub/Sub coupon events and dispatch processing jobs';

    public function __construct(
        private RedisPubSubService $pubSubService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $timeout = (int)$this->option('timeout');

        $this->info("Starting coupon event subscriber...");
        $this->info("Press Ctrl+C to stop the subscriber");

        if ($timeout > 0) {
            $this->info("Will run for {$timeout} seconds");
        }

        try {
            // 시작 시간 기록
            $startTime = time();

            // Redis Pub/Sub 채널 구독
            $this->pubSubService->subscribe(function ($message, $channel) use ($startTime, $timeout) {
                try {
                    $this->processMessage($message, $channel);
                } catch (\Exception $e) {
                    $this->error("Error processing message: " . $e->getMessage());
                    Log::error("Error processing Redis Pub/Sub message", [
                        'error' => $e->getMessage(),
                        'message' => $message,
                        'channel' => $channel,
                    ]);
                }

                // 타임아웃 체크
                if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                    $this->info("Timeout reached, stopping subscriber");
                    return false; // 구독 중단
                }
            });

        } catch (\Exception $e) {
            $this->error("Failed to subscribe to coupon events: " . $e->getMessage());
            Log::error("Coupon event subscriber failed", [
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }

        $this->info("Coupon event subscriber stopped");
        return Command::SUCCESS;
    }

    /**
     * 수신된 메시지 처리
     */
    private function processMessage(array $message, string $channel): void
    {
        $eventType = $message['event_type'] ?? 'unknown';
        $eventData = $message['data'] ?? [];

        $this->line("Received event: {$eventType} on channel: {$channel}");

        // 이벤트 로그 생성
        $event = $this->createEventLog($eventType, $eventData);

        if ($event) {
            // 비동기 처리를 위해 Job 디스패치
            ProcessCouponEventJob::dispatch($event->id);

            $this->line("Dispatched processing job for event ID: {$event->id}");
        }
    }

    /**
     * 이벤트 로그 생성
     */
    private function createEventLog(string $eventType, array $eventData): ?CouponEvent
    {
        try {
            // 이벤트 데이터에서 필요한 정보 추출
            $entityType = $this->determineEntityType($eventType);
            $entityId = $this->extractEntityId($eventData, $entityType);
            $userId = $eventData['user_id'] ?? null;

            if (!$entityId) {
                $this->warn("Could not extract entity ID from event data");
                return null;
            }

            $event = CouponEvent::createEvent(
                $eventType,
                $entityType,
                $entityId,
                $eventData,
                $userId
            );

            Log::info("Created event log from Pub/Sub message", [
                'event_id' => $event->id,
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);

            return $event;

        } catch (\Exception $e) {
            $this->error("Failed to create event log: " . $e->getMessage());
            Log::error("Failed to create event log from Pub/Sub message", [
                'event_type' => $eventType,
                'event_data' => $eventData,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 이벤트 타입으로부터 엔티티 타입 결정
     */
    private function determineEntityType(string $eventType): string
    {
        if (str_starts_with($eventType, 'coupon_')) {
            return 'coupon';
        } elseif (str_starts_with($eventType, 'promotion_')) {
            return 'promotion';
        } elseif (str_starts_with($eventType, 'user_')) {
            return 'user';
        }

        return 'unknown';
    }

    /**
     * 이벤트 데이터에서 엔티티 ID 추출
     */
    private function extractEntityId(array $eventData, string $entityType): ?int
    {
        switch ($entityType) {
            case 'coupon':
                return $eventData['coupon_id'] ?? null;

            case 'promotion':
                return $eventData['promotion_id'] ?? null;

            case 'user':
                return $eventData['user_id'] ?? null;

            default:
                return null;
        }
    }
}
