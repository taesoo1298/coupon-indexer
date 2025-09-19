<?php

namespace App\Jobs;

use App\Services\CouponIndexService;
use App\Services\CouponEventLogger;
use App\Models\Coupon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExpiredCouponCleanupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30분
    public int $tries = 2;

    public function __construct()
    {
        $this->onQueue('coupon_cleanup');
    }

    public function handle(
        CouponIndexService $indexService,
        CouponEventLogger $eventLogger
    ): void {
        try {
            Log::info("Starting expired coupon cleanup job");

            $startTime = microtime(true);
            $stats = [
                'expired_coupons_processed' => 0,
                'index_entries_cleaned' => 0,
                'old_events_cleaned' => 0,
            ];

            // 1. 만료된 쿠폰들을 찾아서 상태 업데이트
            $stats['expired_coupons_processed'] = $this->processExpiredCoupons($indexService);

            // 2. 오래된 인덱스 엔트리 정리
            $stats['index_entries_cleaned'] = $this->cleanupOldIndexEntries($indexService);

            // 3. 오래된 이벤트 로그 정리
            $stats['old_events_cleaned'] = $this->cleanupOldEventLogs($eventLogger);

            $duration = microtime(true) - $startTime;

            Log::info("Completed expired coupon cleanup job", [
                'duration_seconds' => round($duration, 2),
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error("Expired coupon cleanup job failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * 만료된 쿠폰들 처리
     */
    private function processExpiredCoupons(CouponIndexService $indexService): int
    {
        Log::info("Processing expired coupons");

        $processedCount = 0;
        $now = now();

        // 활성 상태이지만 만료된 쿠폰들 조회
        Coupon::where('status', 'active')
            ->where('expires_at', '<=', $now)
            ->chunk(500, function ($coupons) use ($indexService, &$processedCount) {
                foreach ($coupons as $coupon) {
                    try {
                        // 쿠폰 상태를 만료로 변경
                        $coupon->markAsExpired();

                        // 인덱스 업데이트
                        $indexService->indexCoupon($coupon);

                        $processedCount++;

                        if ($processedCount % 100 === 0) {
                            Log::info("Expired coupons processing progress", ['processed' => $processedCount]);
                        }

                    } catch (\Exception $e) {
                        Log::error("Failed to process expired coupon", [
                            'coupon_id' => $coupon->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info("Completed processing expired coupons", ['total_processed' => $processedCount]);
        return $processedCount;
    }

    /**
     * 오래된 인덱스 엔트리 정리
     */
    private function cleanupOldIndexEntries(CouponIndexService $indexService): int
    {
        Log::info("Cleaning up old index entries");

        $cleanedCount = 0;
        $cutoffDate = now()->subDays(7); // 7일 이전의 사용된/만료된 쿠폰들

        // 사용되거나 만료된 오래된 쿠폰들의 인덱스 정리
        Coupon::whereIn('status', ['used', 'expired', 'revoked'])
            ->where('updated_at', '<', $cutoffDate)
            ->chunk(500, function ($coupons) use ($indexService, &$cleanedCount) {
                foreach ($coupons as $coupon) {
                    try {
                        // 불필요한 인덱스 엔트리 제거
                        $indexService->removeCouponFromIndex($coupon->id);
                        $cleanedCount++;

                        if ($cleanedCount % 100 === 0) {
                            Log::info("Index cleanup progress", ['cleaned' => $cleanedCount]);
                        }

                    } catch (\Exception $e) {
                        Log::error("Failed to cleanup coupon index", [
                            'coupon_id' => $coupon->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info("Completed index entries cleanup", ['total_cleaned' => $cleanedCount]);
        return $cleanedCount;
    }

    /**
     * 오래된 이벤트 로그 정리
     */
    private function cleanupOldEventLogs(CouponEventLogger $eventLogger): int
    {
        Log::info("Cleaning up old event logs");

        $daysToKeep = config('coupon-indexer.monitoring.event_log_retention_days', 30);
        $cleanedCount = $eventLogger->cleanupOldEvents($daysToKeep);

        Log::info("Completed event logs cleanup", ['total_cleaned' => $cleanedCount]);
        return $cleanedCount;
    }

    /**
     * Job 실패 시 처리
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ExpiredCouponCleanupJob failed", [
            'error' => $exception->getMessage(),
        ]);
    }
}
