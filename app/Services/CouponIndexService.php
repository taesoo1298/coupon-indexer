<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Promotion;
use App\Models\User;
use App\Models\CouponIndexStatus;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CouponIndexService
{
    private string $redisConnection;
    private string $keyPrefix;
    private int $defaultTtl;

    public function __construct()
    {
        $this->redisConnection = config('coupon-indexer.redis.connection', 'coupon_index');
        $this->keyPrefix = config('coupon-indexer.redis.prefix', 'coupon_idx:');
        $this->defaultTtl = config('coupon-indexer.redis.ttl', 86400);
    }

    /**
     * 쿠폰 인덱스 생성/업데이트
     */
    public function indexCoupon(Coupon $coupon): void
    {
        try {
            $redis = Redis::connection($this->redisConnection);
            $couponKey = $this->getKey('coupon', $coupon->id);

            // 쿠폰 기본 정보 인덱싱
            $couponData = [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'status' => $coupon->status,
                'user_id' => $coupon->user_id,
                'promotion_id' => $coupon->promotion_id,
                'expires_at' => $coupon->expires_at->timestamp,
                'issued_at' => $coupon->issued_at->timestamp,
                'used_at' => $coupon->used_at?->timestamp,
                'discount_amount' => $coupon->discount_amount,
                'usage_restrictions' => json_encode($coupon->usage_restrictions ?? []),
                'metadata' => json_encode($coupon->metadata ?? []),
                'indexed_at' => now()->timestamp,
            ];

            // coupon:{id}

            // 쿠폰 데이터 저장
            $redis->hMSet($couponKey, $couponData);
            $redis->expire($couponKey, $this->defaultTtl);

            // 사용자별 쿠폰 인덱스에 추가
            if ($coupon->user_id) {
                $this->addToUserCoupons($coupon->user_id, $coupon->id, $coupon->status);
                // user_coupons:{user_id}
            }

            // 프로모션별 쿠폰 인덱스에 추가
            $this->addToPromotionCoupons($coupon->promotion_id, $coupon->id, $coupon->status);

            // promotion_coupons:{promotion_id}

            // 상태별 쿠폰 인덱스에 추가
            // $this->addToStatusCoupons($coupon->status, $coupon->id);

            // 만료 예정 쿠폰 인덱스 관리
            $this->updateExpiringCoupons($coupon);
            // expiring_coupons

            // 인덱스 상태 업데이트
            $this->updateIndexStatus('coupon', "coupon:{$coupon->id}", $coupon->id, 'completed', $couponData);

            Log::info("Coupon indexed successfully", [
                'coupon_id' => $coupon->id,
                'key' => $couponKey,
            ]);

        } catch (\Exception $e) {
            $this->updateIndexStatus('coupon', "coupon:{$coupon->id}", $coupon->id, 'failed', [], $e->getMessage());

            Log::error("Failed to index coupon", [
                'coupon_id' => $coupon->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 프로모션 인덱스 생성/업데이트
     */
    public function indexPromotion(Promotion $promotion): void
    {
        try {
            $redis = Redis::connection($this->redisConnection);
            $promotionKey = $this->getKey('promotion_coupons', $promotion->id);

            $promotionData = [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'type' => $promotion->type,
                'value' => $promotion->value,
                'conditions' => json_encode($promotion->conditions ?? []),
                'targeting_rules' => json_encode($promotion->targeting_rules ?? []),
                'start_date' => $promotion->start_date->timestamp,
                'end_date' => $promotion->end_date->timestamp,
                'is_active' => $promotion->is_active ? 1 : 0,
                'max_usage_count' => $promotion->max_usage_count ?? -1,
                'max_usage_per_user' => $promotion->max_usage_per_user ?? -1,
                'current_usage_count' => $promotion->current_usage_count,
                'priority' => $promotion->priority,
                'indexed_at' => now()->timestamp,
            ];

            // 프로모션 데이터 저장
            $redis->hMSet($promotionKey, $promotionData);
            $redis->expire($promotionKey, $this->defaultTtl);

            // 활성 프로모션 인덱스 관리
            if ($promotion->isCurrentlyActive()) {
                $redis->zAdd($this->getKey('active_promotions'), $promotion->priority, $promotion->id);
            } else {
                $redis->zRem($this->getKey('active_promotions'), $promotion->id);
            }

            // 타입별 프로모션 인덱스
            $redis->sAdd($this->getKey('promotions_by_type', $promotion->type), $promotion->id);

            // 인덱스 상태 업데이트
            $this->updateIndexStatus('promotion', "promotion:{$promotion->id}", $promotion->id, 'completed', $promotionData);

            Log::info("Promotion indexed successfully", [
                'promotion_id' => $promotion->id,
                'key' => $promotionKey,
            ]);

        } catch (\Exception $e) {
            $this->updateIndexStatus('promotion', "promotion:{$promotion->id}", $promotion->id, 'failed', [], $e->getMessage());

            Log::error("Failed to index promotion", [
                'promotion_id' => $promotion->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 사용자 정보 인덱싱
     */
    public function indexUser(User $user): void
    {
        try {
            $redis = Redis::connection($this->redisConnection);
            $userKey = $this->getKey('user', $user->id);

            $userData = [
                'id' => $user->id,
                'email' => $user->email,
                'user_level_id' => $user->user_level_id,
                'points' => $user->points,
                'total_purchase_amount' => $user->total_purchase_amount,
                'preferences' => json_encode($user->preferences ?? []),
                'indexed_at' => now()->timestamp,
            ];

            $redis->hMSet($userKey, $userData);
            $redis->expire($userKey, $this->defaultTtl);

            // 등급별 사용자 인덱스
            if ($user->user_level_id) {
                $redis->sAdd($this->getKey('users_by_level', $user->user_level_id), $user->id);
            }

            // 포인트 범위별 사용자 인덱스
            $pointRange = $this->getPointRange($user->points);
            $redis->sAdd($this->getKey('users_by_point_range', $pointRange), $user->id);

            $this->updateIndexStatus('user', "user:{$user->id}", $user->id, 'completed', $userData);

            Log::info("User indexed successfully", [
                'user_id' => $user->id,
                'key' => $userKey,
            ]);

        } catch (\Exception $e) {
            $this->updateIndexStatus('user', "user:{$user->id}", $user->id, 'failed', [], $e->getMessage());

            Log::error("Failed to index user", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 사용자의 사용 가능한 쿠폰 조회
     */
    public function getUserAvailableCoupons(int $userId): array
    {
        $redis = Redis::connection($this->redisConnection);
        $userCouponsKey = $this->getKey('user_coupons', $userId);

        // 활성 상태의 쿠폰 ID들 조회
        $activeCouponIds = $redis->sMembers("{$userCouponsKey}:active");
        $coupons = [];

        foreach ($activeCouponIds as $couponId) {
            $couponData = $redis->hGetAll($this->getKey('coupon', $couponId));

            if (!empty($couponData) && $this->isCouponCurrentlyValid($couponData)) {
                $coupons[] = $this->deserializeCouponData($couponData);
            }
        }

        return $coupons;
    }

    /**
     * 프로모션에 적용 가능한 사용자 조회
     */
    public function getEligibleUsersForPromotion(int $promotionId): array
    {
        $redis = Redis::connection($this->redisConnection);
        $promotionKey = $this->getKey('promotion', $promotionId);

        $promotionData = $redis->hGetAll($promotionKey);
        if (empty($promotionData)) {
            return [];
        }

        $targetingRules = json_decode($promotionData['targeting_rules'] ?? '[]', true);

        return $this->findUsersByTargeting($targetingRules);
    }

    /**
     * 만료 예정 쿠폰들 조회
     */
    public function getExpiringCoupons(int $hours = 24): array
    {
        $redis = Redis::connection($this->redisConnection);
        $expiringKey = $this->getKey('expiring_coupons');

        $cutoffTimestamp = now()->addHours($hours)->timestamp;

        // 지정된 시간 내에 만료되는 쿠폰들 조회
        $expiringCouponIds = $redis->zRangeByScore($expiringKey, 0, $cutoffTimestamp);

        $coupons = [];
        foreach ($expiringCouponIds as $couponId) {
            $couponData = $redis->hGetAll($this->getKey('coupon', $couponId));
            if (!empty($couponData)) {
                $coupons[] = $this->deserializeCouponData($couponData);
            }
        }

        return $coupons;
    }

    /**
     * 인덱스에서 쿠폰 제거
     */
    public function removeCouponFromIndex(int $couponId): void
    {
        $redis = Redis::connection($this->redisConnection);
        $couponKey = $this->getKey('coupon', $couponId);

        // 쿠폰 데이터 조회
        $couponData = $redis->hGetAll($couponKey);

        if (!empty($couponData)) {
            // 관련 인덱스들에서 제거
            if (!empty($couponData['user_id'])) {
                $this->removeFromUserCoupons($couponData['user_id'], $couponId, $couponData['status']);
            }

            $this->removeFromPromotionCoupons($couponData['promotion_id'], $couponId, $couponData['status']);
            $this->removeFromStatusCoupons($couponData['status'], $couponId);

            $redis->zRem($this->getKey('expiring_coupons'), $couponId);
        }

        // 쿠폰 데이터 삭제
        $redis->del($couponKey);

        Log::info("Coupon removed from index", [
            'coupon_id' => $couponId,
        ]);
    }

    /**
     * 전체 쿠폰 재인덱싱
     */
    public function reindexAllCoupons(): void
    {
        Log::info("Starting full coupon reindexing");

        Coupon::with('promotion', 'user')
            ->chunk(1000, function ($coupons) {
                foreach ($coupons as $coupon) {
                    try {
                        $this->indexCoupon($coupon);
                    } catch (\Exception $e) {
                        Log::error("Failed to reindex coupon", [
                            'coupon_id' => $coupon->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info("Completed full coupon reindexing");
    }

    /**
     * Redis 키 생성 헬퍼
     */
    private function getKey(string $type, ...$params): string
    {
        $keyTemplate = config("coupon-indexer.keys.{$type}", $type);

        if (!empty($params)) {
            foreach ($params as $i => $param) {
                $keyTemplate = str_replace('{' . ($i === 0 ? 'id' : "param{$i}") . '}', $param, $keyTemplate);
            }
        }

        return $this->keyPrefix . $keyTemplate;
    }

    /**
     * 사용자별 쿠폰 인덱스에 추가
     */
    private function addToUserCoupons(int $userId, int $couponId, string $status): void
    {
        $redis = Redis::connection($this->redisConnection);
        $userCouponsKey = $this->getKey('user_coupons', $userId);

        $redis->sAdd("{$userCouponsKey}:{$status}", $couponId);
        $redis->expire("{$userCouponsKey}:{$status}", $this->defaultTtl);
    }

    /**
     * 사용자별 쿠폰 인덱스에서 제거
     */
    private function removeFromUserCoupons(int $userId, int $couponId, string $status): void
    {
        $redis = Redis::connection($this->redisConnection);
        $userCouponsKey = $this->getKey('user_coupons', $userId);

        $redis->sRem("{$userCouponsKey}:{$status}", $couponId);
    }

    /**
     * 프로모션별 쿠폰 인덱스에 추가
     */
    private function addToPromotionCoupons(int $promotionId, int $couponId, string $status): void
    {
        $redis = Redis::connection($this->redisConnection);
        $promotionCouponsKey = $this->getKey('promotion_coupons', $promotionId);

        $redis->sAdd("{$promotionCouponsKey}:{$status}", $couponId);
        $redis->expire("{$promotionCouponsKey}:{$status}", $this->defaultTtl);
    }

    /**
     * 프로모션별 쿠폰 인덱스에서 제거
     */
    private function removeFromPromotionCoupons(int $promotionId, int $couponId, string $status): void
    {
        $redis = Redis::connection($this->redisConnection);
        $promotionCouponsKey = $this->getKey('promotion_coupons', $promotionId);

        $redis->sRem("{$promotionCouponsKey}:{$status}", $couponId);
    }

    /**
     * 상태별 쿠폰 인덱스 관리
     */
    private function addToStatusCoupons(string $status, int $couponId): void
    {
        $redis = Redis::connection($this->redisConnection);
        $statusKey = $this->getKey('coupons_by_status', $status);

        $redis->sAdd($statusKey, $couponId);
        $redis->expire($statusKey, $this->defaultTtl);
    }

    private function removeFromStatusCoupons(string $status, int $couponId): void
    {
        $redis = Redis::connection($this->redisConnection);
        $statusKey = $this->getKey('coupons_by_status', $status);

        $redis->sRem($statusKey, $couponId);
    }

    /**
     * 만료 예정 쿠폰 인덱스 업데이트
     */
    private function updateExpiringCoupons(Coupon $coupon): void
    {
        $redis = Redis::connection($this->redisConnection);
        $expiringKey = $this->getKey('expiring_coupons');

        if ($coupon->status === 'active') {
            $redis->zAdd($expiringKey, $coupon->expires_at->timestamp, $coupon->id);
        } else {
            $redis->zRem($expiringKey, $coupon->id);
        }
    }

    /**
     * 인덱스 상태 업데이트
     */
    private function updateIndexStatus(string $indexType, string $entityKey, int $entityId, string $status, array $indexData = [], string $errorMessage = null): void
    {
        $indexStatus = CouponIndexStatus::findOrCreate($indexType, $entityKey, $entityId);
        $indexStatus->updateStatus($status, $indexData, $errorMessage);
    }

    /**
     * 쿠폰 데이터가 현재 유효한지 확인
     */
    private function isCouponCurrentlyValid(array $couponData): bool
    {
        return $couponData['status'] === 'active'
            && $couponData['expires_at'] > now()->timestamp;
    }

    /**
     * Redis 쿠폰 데이터를 배열로 역직렬화
     */
    private function deserializeCouponData(array $couponData): array
    {
        return [
            'id' => (int)$couponData['id'],
            'code' => $couponData['code'],
            'status' => $couponData['status'],
            'user_id' => $couponData['user_id'] ? (int)$couponData['user_id'] : null,
            'promotion_id' => (int)$couponData['promotion_id'],
            'expires_at' => Carbon::createFromTimestamp($couponData['expires_at']),
            'issued_at' => Carbon::createFromTimestamp($couponData['issued_at']),
            'used_at' => $couponData['used_at'] ? Carbon::createFromTimestamp($couponData['used_at']) : null,
            'discount_amount' => $couponData['discount_amount'] ? (float)$couponData['discount_amount'] : null,
            'usage_restrictions' => json_decode($couponData['usage_restrictions'] ?? '[]', true),
            'metadata' => json_decode($couponData['metadata'] ?? '[]', true),
        ];
    }

    /**
     * 포인트 범위 계산
     */
    private function getPointRange(int $points): string
    {
        if ($points < 1000) return 'low';
        if ($points < 5000) return 'medium';
        if ($points < 10000) return 'high';
        return 'premium';
    }

    /**
     * 타겟팅 조건으로 사용자 찾기
     */
    private function findUsersByTargeting(array $targetingRules): array
    {
        $redis = Redis::connection($this->redisConnection);
        $userIds = [];

        foreach ($targetingRules as $rule => $criteria) {
            switch ($rule) {
                case 'user_level':
                    foreach ($criteria as $levelId) {
                        $levelUsers = $redis->sMembers($this->getKey('users_by_level', $levelId));
                        $userIds = array_merge($userIds, $levelUsers);
                    }
                    break;

                case 'min_points':
                    // 포인트 범위별로 조회
                    $ranges = ['medium', 'high', 'premium'];
                    foreach ($ranges as $range) {
                        $rangeUsers = $redis->sMembers($this->getKey('users_by_point_range', $range));
                        $userIds = array_merge($userIds, $rangeUsers);
                    }
                    break;
            }
        }

        return array_unique($userIds);
    }
}
