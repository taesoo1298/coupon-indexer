<?php

namespace App\Jobs;

use App\Services\CouponIndexService;
use App\Services\CouponEventLogger;
use App\Models\Coupon;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FullSyncCouponIndexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1시간
    public int $tries = 1; // 재시도 없음 (수동으로만 실행)

    public function __construct(
        private ?string $syncType = null, // 'coupons', 'promotions', 'users' 또는 null (전체)
        private ?array $entityIds = null // 특정 엔티티들만 동기화
    ) {
        $this->onQueue('coupon_sync');
    }

    public function handle(
        CouponIndexService $indexService,
        CouponEventLogger $eventLogger
    ): void {
        try {
            Log::info("Starting full sync coupon index job", [
                'sync_type' => $this->syncType,
                'entity_ids' => $this->entityIds ? count($this->entityIds) : null,
            ]);

            $startTime = microtime(true);
            $stats = [
                'coupons_synced' => 0,
                'promotions_synced' => 0,
                'users_synced' => 0,
                'errors' => 0,
            ];

            if (!$this->syncType || $this->syncType === 'coupons') {
                $stats['coupons_synced'] = $this->syncCoupons($indexService);
            }

            if (!$this->syncType || $this->syncType === 'promotions') {
                $stats['promotions_synced'] = $this->syncPromotions($indexService);
            }

            if (!$this->syncType || $this->syncType === 'users') {
                $stats['users_synced'] = $this->syncUsers($indexService);
            }

            $duration = microtime(true) - $startTime;

            Log::info("Completed full sync coupon index job", [
                'duration_seconds' => round($duration, 2),
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error("Full sync coupon index job failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * 쿠폰 동기화
     */
    private function syncCoupons(CouponIndexService $indexService): int
    {
        Log::info("Starting coupons sync");

        $query = Coupon::with('promotion', 'user');

        if ($this->entityIds) {
            $query->whereIn('id', $this->entityIds);
        }

        $syncedCount = 0;
        $chunkSize = config('coupon-indexer.sync.chunk_size', 100);

        $query->chunk($chunkSize, function ($coupons) use ($indexService, &$syncedCount) {
            foreach ($coupons as $coupon) {
                try {
                    $indexService->indexCoupon($coupon);
                    $syncedCount++;

                    if ($syncedCount % 1000 === 0) {
                        Log::info("Coupons sync progress", ['synced' => $syncedCount]);
                    }

                } catch (\Exception $e) {
                    Log::error("Failed to sync coupon", [
                        'coupon_id' => $coupon->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        Log::info("Completed coupons sync", ['total_synced' => $syncedCount]);
        return $syncedCount;
    }

    /**
     * 프로모션 동기화
     */
    private function syncPromotions(CouponIndexService $indexService): int
    {
        Log::info("Starting promotions sync");

        $query = Promotion::query();

        if ($this->entityIds) {
            $query->whereIn('id', $this->entityIds);
        }

        $syncedCount = 0;
        $chunkSize = config('coupon-indexer.sync.chunk_size', 100);

        $query->chunk($chunkSize, function ($promotions) use ($indexService, &$syncedCount) {
            foreach ($promotions as $promotion) {
                try {
                    $indexService->indexPromotion($promotion);
                    $syncedCount++;

                    if ($syncedCount % 100 === 0) {
                        Log::info("Promotions sync progress", ['synced' => $syncedCount]);
                    }

                } catch (\Exception $e) {
                    Log::error("Failed to sync promotion", [
                        'promotion_id' => $promotion->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        Log::info("Completed promotions sync", ['total_synced' => $syncedCount]);
        return $syncedCount;
    }

    /**
     * 사용자 동기화
     */
    private function syncUsers(CouponIndexService $indexService): int
    {
        Log::info("Starting users sync");

        $query = User::with('userLevel');

        if ($this->entityIds) {
            $query->whereIn('id', $this->entityIds);
        }

        $syncedCount = 0;
        $chunkSize = config('coupon-indexer.sync.chunk_size', 100);

        $query->chunk($chunkSize, function ($users) use ($indexService, &$syncedCount) {
            foreach ($users as $user) {
                try {
                    $indexService->indexUser($user);
                    $syncedCount++;

                    if ($syncedCount % 1000 === 0) {
                        Log::info("Users sync progress", ['synced' => $syncedCount]);
                    }

                } catch (\Exception $e) {
                    Log::error("Failed to sync user", [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        Log::info("Completed users sync", ['total_synced' => $syncedCount]);
        return $syncedCount;
    }

    /**
     * Job 실패 시 처리
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("FullSyncCouponIndexJob failed", [
            'sync_type' => $this->syncType,
            'error' => $exception->getMessage(),
        ]);
    }
}
