<?php

namespace App\Jobs;

use App\Models\CouponEvent;
use App\Services\CouponIndexService;
use App\Services\CouponRuleEngine;
use App\Services\CouponEventLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCouponEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5분
    public int $tries = 3;
    public int $backoff = 60; // 1분

    public function __construct(
        private int $eventId
    ) {
        $this->onQueue(config('coupon-indexer.events.queue', 'coupon_events'));
    }

    public function handle(
        CouponIndexService $indexService,
        CouponRuleEngine $ruleEngine,
        CouponEventLogger $eventLogger
    ): void {
        try {
            $event = CouponEvent::find($this->eventId);

            if (!$event) {
                Log::warning("Coupon event not found", ['event_id' => $this->eventId]);
                return;
            }

            if ($event->is_processed) {
                Log::info("Coupon event already processed", ['event_id' => $this->eventId]);
                return;
            }

            Log::info("Processing coupon event", [
                'event_id' => $event->id,
                'event_type' => $event->event_type,
                'entity_type' => $event->entity_type,
                'entity_id' => $event->entity_id,
            ]);

            // 이벤트 타입에 따라 적절한 인덱싱 처리
            $this->processEventByType($event, $indexService, $ruleEngine);

            // 이벤트를 처리 완료로 표시
            $eventLogger->markEventAsProcessed($event);

            Log::info("Coupon event processed successfully", [
                'event_id' => $event->id,
                'event_type' => $event->event_type,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to process coupon event", [
                'event_id' => $this->eventId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 재시도 횟수가 남아있다면 이벤트 로그에 실패 기록
            if ($this->attempts() < $this->tries) {
                if (isset($event)) {
                    $eventLogger->markEventAsFailed($event, [
                        'error' => $e->getMessage(),
                        'attempt' => $this->attempts(),
                    ]);
                }
            }

            throw $e;
        }
    }

    /**
     * 이벤트 타입별 처리 로직
     */
    private function processEventByType(
        CouponEvent $event,
        CouponIndexService $indexService,
        CouponRuleEngine $ruleEngine
    ): void {
        switch ($event->event_type) {
            case 'coupon_issued':
            case 'coupon_used':
            case 'coupon_expired':
            case 'coupon_revoked':
                $this->processCouponEvent($event, $indexService);
                break;

            case 'promotion_created':
            case 'promotion_updated':
            case 'promotion_activated':
            case 'promotion_deactivated':
                $this->processPromotionEvent($event, $indexService);
                break;

            case 'user_level_changed':
            case 'user_profile_updated':
                $this->processUserEvent($event, $indexService);
                break;

            default:
                Log::warning("Unknown event type", [
                    'event_type' => $event->event_type,
                    'event_id' => $event->id,
                ]);
        }
    }

    /**
     * 쿠폰 관련 이벤트 처리
     */
    private function processCouponEvent(CouponEvent $event, CouponIndexService $indexService): void
    {
        $coupon = \App\Models\Coupon::find($event->entity_id);

        if (!$coupon) {
            Log::warning("Coupon not found for event", [
                'coupon_id' => $event->entity_id,
                'event_id' => $event->id,
            ]);
            return;
        }

        switch ($event->event_type) {
            case 'coupon_issued':
            case 'coupon_used':
                $indexService->indexCoupon($coupon);
                break;

            case 'coupon_expired':
            case 'coupon_revoked':
                // 상태 업데이트를 위해 재인덱싱
                $indexService->indexCoupon($coupon);
                break;
        }
    }

    /**
     * 프로모션 관련 이벤트 처리
     */
    private function processPromotionEvent(CouponEvent $event, CouponIndexService $indexService): void
    {
        $promotion = \App\Models\Promotion::find($event->entity_id);

        if (!$promotion) {
            Log::warning("Promotion not found for event", [
                'promotion_id' => $event->entity_id,
                'event_id' => $event->id,
            ]);
            return;
        }

        // 프로모션 인덱싱
        $indexService->indexPromotion($promotion);

        // 프로모션 상태 변경 시 관련 쿠폰들도 재인덱싱
        if (in_array($event->event_type, ['promotion_activated', 'promotion_deactivated', 'promotion_updated'])) {
            $this->reindexPromotionCoupons($promotion, $indexService);
        }
    }

    /**
     * 사용자 관련 이벤트 처리
     */
    private function processUserEvent(CouponEvent $event, CouponIndexService $indexService): void
    {
        $user = \App\Models\User::find($event->entity_id);

        if (!$user) {
            Log::warning("User not found for event", [
                'user_id' => $event->entity_id,
                'event_id' => $event->id,
            ]);
            return;
        }

        // 사용자 정보 인덱싱
        $indexService->indexUser($user);

        // 사용자 등급 변경 시 관련 쿠폰들 재검토
        if ($event->event_type === 'user_level_changed') {
            $this->reindexUserCoupons($user, $indexService);
        }
    }

    /**
     * 프로모션 관련 쿠폰들 재인덱싱
     */
    private function reindexPromotionCoupons(\App\Models\Promotion $promotion, CouponIndexService $indexService): void
    {
        $promotion->coupons()->chunk(100, function ($coupons) use ($indexService) {
            foreach ($coupons as $coupon) {
                try {
                    $indexService->indexCoupon($coupon);
                } catch (\Exception $e) {
                    Log::error("Failed to reindex coupon", [
                        'coupon_id' => $coupon->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    /**
     * 사용자 관련 쿠폰들 재인덱싱
     */
    private function reindexUserCoupons(\App\Models\User $user, CouponIndexService $indexService): void
    {
        $user->coupons()->chunk(100, function ($coupons) use ($indexService) {
            foreach ($coupons as $coupon) {
                try {
                    $indexService->indexCoupon($coupon);
                } catch (\Exception $e) {
                    Log::error("Failed to reindex user coupon", [
                        'coupon_id' => $coupon->id,
                        'user_id' => $coupon->user_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    /**
     * Job 실패 시 처리
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessCouponEventJob failed permanently", [
            'event_id' => $this->eventId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        try {
            $event = CouponEvent::find($this->eventId);
            if ($event) {
                $eventLogger = app(CouponEventLogger::class);
                $eventLogger->markEventAsFailed($event, [
                    'final_error' => $exception->getMessage(),
                    'failed_at' => now()->toISOString(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to mark event as failed", [
                'event_id' => $this->eventId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
