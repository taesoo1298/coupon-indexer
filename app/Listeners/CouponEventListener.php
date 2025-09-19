<?php

namespace App\Listeners;

use App\Events\CouponIssued;
use App\Events\CouponUsed;
use App\Events\CouponExpired;
use App\Events\CouponRevoked;
use App\Events\PromotionCreated;
use App\Events\PromotionUpdated;
use App\Events\PromotionActivated;
use App\Events\PromotionDeactivated;
use App\Events\UserLevelChanged;
use App\Events\UserProfileUpdated;
use App\Services\CouponEventLogger;
use App\Services\RedisPubSubService;
use Illuminate\Contracts\Queue\ShouldQueue;

class CouponEventListener implements ShouldQueue
{
    public function __construct(
        private CouponEventLogger $eventLogger,
        private RedisPubSubService $pubSubService
    ) {}

    /**
     * 쿠폰 발급 이벤트 처리
     */
    public function handleCouponIssued(CouponIssued $event): void
    {
        $coupon = $event->coupon;

        // 이벤트 로그 기록
        $this->eventLogger->logEvent(
            'coupon_issued',
            'coupon',
            $coupon->id,
            $coupon->user_id,
            [
                'coupon_code' => $coupon->code,
                'promotion_id' => $coupon->promotion_id,
                'expires_at' => $coupon->expires_at->toISOString(),
            ],
            null,
            $coupon->toArray()
        );

        // Redis Pub/Sub로 이벤트 발행
        $this->pubSubService->publishEvent('coupon_issued', [
            'coupon_id' => $coupon->id,
            'user_id' => $coupon->user_id,
            'promotion_id' => $coupon->promotion_id,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * 쿠폰 사용 이벤트 처리
     */
    public function handleCouponUsed(CouponUsed $event): void
    {
        $coupon = $event->coupon;

        $this->eventLogger->logEvent(
            'coupon_used',
            'coupon',
            $coupon->id,
            $coupon->user_id,
            [
                'coupon_code' => $coupon->code,
                'promotion_id' => $coupon->promotion_id,
                'discount_amount' => $coupon->discount_amount,
                'used_at' => $coupon->used_at->toISOString(),
            ],
            ['status' => 'active'],
            ['status' => 'used']
        );

        $this->pubSubService->publishEvent('coupon_used', [
            'coupon_id' => $coupon->id,
            'user_id' => $coupon->user_id,
            'promotion_id' => $coupon->promotion_id,
            'discount_amount' => $coupon->discount_amount,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * 쿠폰 만료 이벤트 처리
     */
    public function handleCouponExpired(CouponExpired $event): void
    {
        $coupon = $event->coupon;

        $this->eventLogger->logEvent(
            'coupon_expired',
            'coupon',
            $coupon->id,
            $coupon->user_id,
            [
                'coupon_code' => $coupon->code,
                'promotion_id' => $coupon->promotion_id,
                'expires_at' => $coupon->expires_at->toISOString(),
            ],
            ['status' => 'active'],
            ['status' => 'expired']
        );

        $this->pubSubService->publishEvent('coupon_expired', [
            'coupon_id' => $coupon->id,
            'user_id' => $coupon->user_id,
            'promotion_id' => $coupon->promotion_id,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * 쿠폰 취소 이벤트 처리
     */
    public function handleCouponRevoked(CouponRevoked $event): void
    {
        $coupon = $event->coupon;

        $this->eventLogger->logEvent(
            'coupon_revoked',
            'coupon',
            $coupon->id,
            $coupon->user_id,
            [
                'coupon_code' => $coupon->code,
                'promotion_id' => $coupon->promotion_id,
                'revoke_reason' => $coupon->metadata['revoke_reason'] ?? null,
            ],
            ['status' => 'active'],
            ['status' => 'revoked']
        );

        $this->pubSubService->publishEvent('coupon_revoked', [
            'coupon_id' => $coupon->id,
            'user_id' => $coupon->user_id,
            'promotion_id' => $coupon->promotion_id,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * 프로모션 생성 이벤트 처리
     */
    public function handlePromotionCreated(PromotionCreated $event): void
    {
        $promotion = $event->promotion;

        $this->eventLogger->logEvent(
            'promotion_created',
            'promotion',
            $promotion->id,
            null,
            [
                'name' => $promotion->name,
                'type' => $promotion->type,
                'value' => $promotion->value,
                'start_date' => $promotion->start_date->toISOString(),
                'end_date' => $promotion->end_date->toISOString(),
            ],
            null,
            $promotion->toArray()
        );

        $this->pubSubService->publishEvent('promotion_created', [
            'promotion_id' => $promotion->id,
            'name' => $promotion->name,
            'type' => $promotion->type,
            'is_active' => $promotion->is_active,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * 프로모션 업데이트 이벤트 처리
     */
    public function handlePromotionUpdated(PromotionUpdated $event): void
    {
        $promotion = $event->promotion;

        $this->eventLogger->logEvent(
            'promotion_updated',
            'promotion',
            $promotion->id,
            null,
            [
                'name' => $promotion->name,
                'type' => $promotion->type,
                'is_active' => $promotion->is_active,
            ],
            $promotion->getOriginal(),
            $promotion->toArray()
        );

        $this->pubSubService->publishEvent('promotion_updated', [
            'promotion_id' => $promotion->id,
            'name' => $promotion->name,
            'is_active' => $promotion->is_active,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * 프로모션 활성화 이벤트 처리
     */
    public function handlePromotionActivated(PromotionActivated $event): void
    {
        $promotion = $event->promotion;

        $this->eventLogger->logEvent(
            'promotion_activated',
            'promotion',
            $promotion->id,
            null,
            [
                'name' => $promotion->name,
                'type' => $promotion->type,
            ],
            ['is_active' => false],
            ['is_active' => true]
        );

        $this->pubSubService->publishEvent('promotion_activated', [
            'promotion_id' => $promotion->id,
            'name' => $promotion->name,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * 프로모션 비활성화 이벤트 처리
     */
    public function handlePromotionDeactivated(PromotionDeactivated $event): void
    {
        $promotion = $event->promotion;

        $this->eventLogger->logEvent(
            'promotion_deactivated',
            'promotion',
            $promotion->id,
            null,
            [
                'name' => $promotion->name,
                'type' => $promotion->type,
            ],
            ['is_active' => true],
            ['is_active' => false]
        );

        $this->pubSubService->publishEvent('promotion_deactivated', [
            'promotion_id' => $promotion->id,
            'name' => $promotion->name,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * 사용자 등급 변경 이벤트 처리
     */
    public function handleUserLevelChanged(UserLevelChanged $event): void
    {
        $user = $event->user;

        $this->eventLogger->logEvent(
            'user_level_changed',
            'user',
            $user->id,
            $user->id,
            [
                'previous_level_id' => $event->previousLevelId,
                'current_level_id' => $event->currentLevelId,
                'points' => $user->points,
                'total_purchase_amount' => $user->total_purchase_amount,
            ],
            ['user_level_id' => $event->previousLevelId],
            ['user_level_id' => $event->currentLevelId]
        );

        $this->pubSubService->publishEvent('user_level_changed', [
            'user_id' => $user->id,
            'previous_level_id' => $event->previousLevelId,
            'current_level_id' => $event->currentLevelId,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * 사용자 프로필 업데이트 이벤트 처리
     */
    public function handleUserProfileUpdated(UserProfileUpdated $event): void
    {
        $user = $event->user;

        $this->eventLogger->logEvent(
            'user_profile_updated',
            'user',
            $user->id,
            $user->id,
            [
                'updated_fields' => array_keys(array_diff_assoc($user->toArray(), $event->previousData)),
            ],
            $event->previousData,
            $user->toArray()
        );

        $this->pubSubService->publishEvent('user_profile_updated', [
            'user_id' => $user->id,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
