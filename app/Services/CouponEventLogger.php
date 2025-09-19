<?php

namespace App\Services;

use App\Models\CouponEvent;
use Illuminate\Support\Facades\Log;

class CouponEventLogger
{
    /**
     * 쿠폰 관련 이벤트를 로그로 기록
     */
    public function logEvent(
        string $eventType,
        string $entityType,
        int $entityId,
        ?int $userId = null,
        array $eventData = [],
        ?array $previousState = null,
        ?array $currentState = null
    ): CouponEvent {
        try {
            $event = CouponEvent::createEvent(
                $eventType,
                $entityType,
                $entityId,
                $eventData,
                $userId,
                $previousState,
                $currentState
            );

            Log::info("Coupon event logged", [
                'event_id' => $event->id,
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'user_id' => $userId,
            ]);

            return $event;
        } catch (\Exception $e) {
            Log::error("Failed to log coupon event", [
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 미처리 이벤트들을 조회
     */
    public function getPendingEvents(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return CouponEvent::getPendingEvents($limit);
    }

    /**
     * 이벤트를 처리 완료로 표시
     */
    public function markEventAsProcessed(CouponEvent $event): void
    {
        $event->markAsProcessed();

        Log::info("Coupon event marked as processed", [
            'event_id' => $event->id,
            'event_type' => $event->event_type,
        ]);
    }

    /**
     * 이벤트 처리 실패 시 재시도 횟수 증가
     */
    public function markEventAsFailed(CouponEvent $event, array $errors = []): void
    {
        $event->incrementRetry($errors);

        Log::warning("Coupon event processing failed", [
            'event_id' => $event->id,
            'event_type' => $event->event_type,
            'retry_count' => $event->retry_count,
            'errors' => $errors,
        ]);
    }

    /**
     * 특정 엔티티의 최근 이벤트들 조회
     */
    public function getEntityEvents(string $entityType, int $entityId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return CouponEvent::getEventsForEntity($entityType, $entityId, $limit);
    }

    /**
     * 이벤트 타입별 통계 조회
     */
    public function getEventStats(int $days = 7): array
    {
        $startDate = now()->subDays($days);

        return CouponEvent::where('occurred_at', '>=', $startDate)
            ->selectRaw('event_type, COUNT(*) as count, DATE(occurred_at) as date')
            ->groupBy('event_type', 'date')
            ->orderBy('date', 'desc')
            ->get()
            ->groupBy('event_type')
            ->toArray();
    }

    /**
     * 처리 실패한 이벤트들 조회
     */
    public function getFailedEvents(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return CouponEvent::where('is_processed', false)
            ->where('retry_count', '>=', 5)
            ->orderBy('occurred_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 오래된 이벤트 로그 정리
     */
    public function cleanupOldEvents(int $daysToKeep = 30): int
    {
        $cutoffDate = now()->subDays($daysToKeep);

        $deletedCount = CouponEvent::where('occurred_at', '<', $cutoffDate)
            ->where('is_processed', true)
            ->delete();

        Log::info("Cleaned up old coupon events", [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->toDateString(),
        ]);

        return $deletedCount;
    }
}
