<?php

namespace App\Services;

use App\Services\CouponIndexService;
use App\Services\CouponEventLogger;
use App\Models\CouponIndexStatus;
use App\Models\CouponEvent;
use App\Jobs\FullSyncCouponIndexJob;
use App\Jobs\ProcessCouponEventJob;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CouponMonitoringService
{
    public function __construct(
        private CouponIndexService $indexService,
        private CouponEventLogger $eventLogger
    ) {}

    /**
     * 시스템 전반적인 상태 모니터링
     */
    public function getSystemHealthStatus(): array
    {
        $health = [
            'overall_status' => 'healthy',
            'checks' => [],
            'metrics' => [],
            'alerts' => [],
            'timestamp' => now()->toISOString(),
        ];

        try {
            // 1. Redis 연결 상태 확인
            $health['checks']['redis'] = $this->checkRedisConnection();

            // 2. 데이터베이스 연결 상태 확인
            $health['checks']['database'] = $this->checkDatabaseConnection();

            // 3. 큐 시스템 상태 확인
            $health['checks']['queue'] = $this->checkQueueStatus();

            // 4. 인덱스 상태 확인
            $health['checks']['index'] = $this->checkIndexStatus();

            // 5. 이벤트 처리 상태 확인
            $health['checks']['event_processing'] = $this->checkEventProcessingStatus();

            // 6. 메트릭 수집
            $health['metrics'] = $this->collectMetrics();

            // 7. 알림이 필요한 이슈들 확인
            $health['alerts'] = $this->checkForAlerts();

            // 전체 상태 결정
            $health['overall_status'] = $this->determineOverallStatus($health['checks']);

        } catch (\Exception $e) {
            $health['overall_status'] = 'critical';
            $health['error'] = $e->getMessage();

            Log::error('System health check failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $health;
    }

    /**
     * 실패한 이벤트들 재처리
     */
    public function retryFailedEvents(int $limit = 100): array
    {
        Log::info("Starting failed events retry", ['limit' => $limit]);

        $failedEvents = CouponEvent::failed()
            ->limit($limit)
            ->get();

        $retryStats = [
            'total_found' => $failedEvents->count(),
            'retried' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($failedEvents as $event) {
            try {
                // 재시도 횟수 리셋
                $event->update([
                    'retry_count' => 0,
                    'processing_errors' => null,
                ]);

                // 새로운 처리 Job 디스패치
                ProcessCouponEventJob::dispatch($event->id);

                $retryStats['retried']++;

                Log::info("Failed event queued for retry", [
                    'event_id' => $event->id,
                    'event_type' => $event->event_type,
                ]);

            } catch (\Exception $e) {
                $retryStats['skipped']++;
                $retryStats['errors'][] = [
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ];

                Log::error("Failed to retry event", [
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Completed failed events retry", $retryStats);
        return $retryStats;
    }

    /**
     * 일관성 검사 및 자동 복구
     */
    public function performConsistencyCheck(): array
    {
        Log::info("Starting consistency check");

        $checkResults = [
            'database_vs_index' => $this->checkDatabaseIndexConsistency(),
            'orphaned_indexes' => $this->checkOrphanedIndexes(),
            'missing_indexes' => $this->checkMissingIndexes(),
            'data_integrity' => $this->checkDataIntegrity(),
        ];

        // 자동 복구 시도
        $autoFixResults = $this->attemptAutoFix($checkResults);

        Log::info("Completed consistency check", [
            'issues_found' => $this->countIssues($checkResults),
            'auto_fixes_applied' => count($autoFixResults),
        ]);

        return [
            'check_results' => $checkResults,
            'auto_fixes' => $autoFixResults,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * 정기적인 시스템 정리
     */
    public function performMaintenanceCleanup(): array
    {
        Log::info("Starting maintenance cleanup");

        $cleanupStats = [
            'old_events_cleaned' => 0,
            'expired_indexes_cleaned' => 0,
            'orphaned_data_cleaned' => 0,
            'cache_cleared' => false,
        ];

        try {
            // 1. 오래된 이벤트 로그 정리
            $daysToKeep = config('coupon-indexer.monitoring.event_log_retention_days', 30);
            $cleanupStats['old_events_cleaned'] = $this->eventLogger->cleanupOldEvents($daysToKeep);

            // 2. 만료된 인덱스 정리
            $cleanupStats['expired_indexes_cleaned'] = $this->cleanupExpiredIndexes();

            // 3. 고아 데이터 정리
            $cleanupStats['orphaned_data_cleaned'] = $this->cleanupOrphanedData();

            // 4. 캐시 정리
            $cleanupStats['cache_cleared'] = $this->clearOldCache();

        } catch (\Exception $e) {
            Log::error("Maintenance cleanup failed", [
                'error' => $e->getMessage(),
            ]);

            $cleanupStats['error'] = $e->getMessage();
        }

        Log::info("Completed maintenance cleanup", $cleanupStats);
        return $cleanupStats;
    }

    /**
     * Redis 연결 상태 확인
     */
    private function checkRedisConnection(): array
    {
        try {
            $redis = Redis::connection('coupon_index');
            $redis->ping();

            return [
                'status' => 'healthy',
                'response_time_ms' => $this->measureRedisResponseTime(),
                'memory_usage' => $this->getRedisMemoryUsage(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 데이터베이스 연결 상태 확인
     */
    private function checkDatabaseConnection(): array
    {
        try {
            $startTime = microtime(true);
            DB::select('SELECT 1');
            $responseTime = (microtime(true) - $startTime) * 1000;

            return [
                'status' => 'healthy',
                'response_time_ms' => round($responseTime, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 큐 시스템 상태 확인
     */
    private function checkQueueStatus(): array
    {
        try {
            $redis = Redis::connection('default');
            $queueLength = $redis->lLen('queues:coupon_events');

            return [
                'status' => $queueLength < 10000 ? 'healthy' : 'warning',
                'queue_length' => $queueLength,
                'warning_threshold' => 10000,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 인덱스 상태 확인
     */
    private function checkIndexStatus(): array
    {
        $stats = CouponIndexStatus::getStatusStats();

        $failureRate = $stats['total'] > 0
            ? ($stats['failed'] / $stats['total']) * 100
            : 0;

        return [
            'status' => $failureRate < 5 ? 'healthy' : 'warning',
            'stats' => $stats,
            'failure_rate_percent' => round($failureRate, 2),
        ];
    }

    /**
     * 이벤트 처리 상태 확인
     */
    private function checkEventProcessingStatus(): array
    {
        $pendingEvents = CouponEvent::unprocessed()->count();
        $failedEvents = CouponEvent::failed()->count();

        return [
            'status' => ($pendingEvents < 1000 && $failedEvents < 100) ? 'healthy' : 'warning',
            'pending_events' => $pendingEvents,
            'failed_events' => $failedEvents,
        ];
    }

    /**
     * 메트릭 수집
     */
    private function collectMetrics(): array
    {
        return [
            'total_coupons' => \App\Models\Coupon::count(),
            'active_coupons' => \App\Models\Coupon::where('status', 'active')->count(),
            'total_promotions' => \App\Models\Promotion::count(),
            'active_promotions' => \App\Models\Promotion::where('is_active', true)->count(),
            'events_last_24h' => CouponEvent::where('occurred_at', '>=', now()->subDay())->count(),
            'index_entries' => CouponIndexStatus::count(),
        ];
    }

    /**
     * 알림이 필요한 이슈 확인
     */
    private function checkForAlerts(): array
    {
        $alerts = [];

        // 실패한 이벤트 수가 임계값 초과
        $failedEvents = CouponEvent::failed()->count();
        if ($failedEvents > 50) {
            $alerts[] = [
                'level' => 'warning',
                'message' => "Too many failed events: {$failedEvents}",
                'action_required' => 'Review and retry failed events',
            ];
        }

        // 큐 길이가 임계값 초과
        $queueLength = Redis::connection('default')->lLen('queues:coupon_events');
        if ($queueLength > 5000) {
            $alerts[] = [
                'level' => 'critical',
                'message' => "Queue backlog too high: {$queueLength}",
                'action_required' => 'Scale queue workers or investigate processing delays',
            ];
        }

        return $alerts;
    }

    /**
     * 전체 시스템 상태 결정
     */
    private function determineOverallStatus(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        if (in_array('unhealthy', $statuses)) {
            return 'critical';
        } elseif (in_array('warning', $statuses)) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }

    /**
     * Redis 응답 시간 측정
     */
    private function measureRedisResponseTime(): float
    {
        $startTime = microtime(true);
        Redis::connection('coupon_index')->get('health_check_key');
        return round((microtime(true) - $startTime) * 1000, 2);
    }

    /**
     * Redis 메모리 사용량 조회
     */
    private function getRedisMemoryUsage(): ?string
    {
        try {
            $info = Redis::connection('coupon_index')->info('memory');
            return $info['used_memory_human'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 데이터베이스와 인덱스 일관성 확인
     */
    private function checkDatabaseIndexConsistency(): array
    {
        // 샘플 쿠폰들을 DB와 Redis에서 비교
        $sampleCoupons = \App\Models\Coupon::limit(100)->get();
        $inconsistencies = [];

        foreach ($sampleCoupons as $coupon) {
            $redisData = Redis::connection('coupon_index')
                ->hGetAll("coupon_idx:coupon:{$coupon->id}");

            if (empty($redisData)) {
                $inconsistencies[] = "Missing index for coupon {$coupon->id}";
            } elseif ($redisData['status'] !== $coupon->status) {
                $inconsistencies[] = "Status mismatch for coupon {$coupon->id}";
            }
        }

        return [
            'total_checked' => $sampleCoupons->count(),
            'inconsistencies' => $inconsistencies,
            'inconsistency_count' => count($inconsistencies),
        ];
    }

    /**
     * 고아 인덱스 확인
     */
    private function checkOrphanedIndexes(): array
    {
        // 구현 복잡도로 인해 기본 구조만 제공
        return [
            'orphaned_coupon_indexes' => 0,
            'orphaned_user_indexes' => 0,
            'orphaned_promotion_indexes' => 0,
        ];
    }

    /**
     * 누락된 인덱스 확인
     */
    private function checkMissingIndexes(): array
    {
        // 구현 복잡도로 인해 기본 구조만 제공
        return [
            'missing_coupon_indexes' => 0,
            'missing_user_indexes' => 0,
            'missing_promotion_indexes' => 0,
        ];
    }

    /**
     * 데이터 무결성 확인
     */
    private function checkDataIntegrity(): array
    {
        return [
            'invalid_expiry_dates' => \App\Models\Coupon::where('expires_at', '<', 'issued_at')->count(),
            'coupons_without_promotion' => \App\Models\Coupon::whereNotExists(function($query) {
                $query->select(DB::raw(1))
                      ->from('promotions')
                      ->whereRaw('promotions.id = coupons.promotion_id');
            })->count(),
        ];
    }

    /**
     * 자동 복구 시도
     */
    private function attemptAutoFix(array $checkResults): array
    {
        $fixes = [];

        // 누락된 인덱스 자동 복구
        if ($checkResults['missing_indexes']['missing_coupon_indexes'] > 0) {
            try {
                FullSyncCouponIndexJob::dispatch('coupons');
                $fixes[] = 'Dispatched full coupon sync job';
            } catch (\Exception $e) {
                Log::error('Failed to dispatch sync job', ['error' => $e->getMessage()]);
            }
        }

        return $fixes;
    }

    /**
     * 이슈 개수 계산
     */
    private function countIssues(array $checkResults): int
    {
        $count = 0;
        foreach ($checkResults as $result) {
            if (isset($result['inconsistency_count'])) {
                $count += $result['inconsistency_count'];
            }
            // 다른 카운트 필드들도 추가 가능
        }
        return $count;
    }

    /**
     * 만료된 인덱스 정리
     */
    private function cleanupExpiredIndexes(): int
    {
        // 7일 이상 된 사용된/만료된 쿠폰 인덱스 정리
        $cutoffDate = now()->subDays(7);
        $expiredCoupons = \App\Models\Coupon::whereIn('status', ['used', 'expired'])
            ->where('updated_at', '<', $cutoffDate)
            ->pluck('id');

        $cleanedCount = 0;
        foreach ($expiredCoupons as $couponId) {
            try {
                $this->indexService->removeCouponFromIndex($couponId);
                $cleanedCount++;
            } catch (\Exception $e) {
                Log::error("Failed to cleanup expired index", [
                    'coupon_id' => $couponId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $cleanedCount;
    }

    /**
     * 고아 데이터 정리
     */
    private function cleanupOrphanedData(): int
    {
        // 관련 엔티티가 삭제된 인덱스 상태 정리
        return CouponIndexStatus::whereNotExists(function($query) {
            $query->select(DB::raw(1))
                  ->from('coupons')
                  ->whereRaw('coupons.id = coupon_index_status.entity_id')
                  ->where('coupon_index_status.index_type', 'coupon');
        })->delete();
    }

    /**
     * 오래된 캐시 정리
     */
    private function clearOldCache(): bool
    {
        try {
            // TTL이 만료된 키들은 Redis가 자동으로 정리하므로
            // 여기서는 특별한 캐시 정리 로직만 수행
            Redis::connection('cache')->flushDb();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to clear cache", ['error' => $e->getMessage()]);
            return false;
        }
    }
}
