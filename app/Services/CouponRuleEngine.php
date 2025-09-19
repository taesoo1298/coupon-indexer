<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CouponRuleEngine
{
    /**
     * 쿠폰 적용 가능성을 분석
     */
    public function analyzeCouponApplicability(Coupon $coupon, array $orderContext): array
    {
        $result = [
            'applicable' => false,
            'discount_amount' => 0,
            'reasons' => [],
            'warnings' => [],
        ];

        try {
            // 1. 기본 쿠폰 유효성 검사
            if (!$this->validateBasicCouponStatus($coupon, $result)) {
                return $result;
            }

            // 2. 프로모션 유효성 검사
            if (!$this->validatePromotionStatus($coupon->promotion, $result)) {
                return $result;
            }

            // 3. 사용자 타겟팅 검사
            if (isset($orderContext['user_id'])) {
                $user = User::find($orderContext['user_id']);
                if ($user && !$this->validateUserTargeting($coupon->promotion, $user, $result)) {
                    return $result;
                }
            }

            // 4. 주문 조건 검사
            if (!$this->validateOrderConditions($coupon, $orderContext, $result)) {
                return $result;
            }

            // 5. 사용 제한 검사
            if (!$this->validateUsageLimits($coupon, $orderContext, $result)) {
                return $result;
            }

            // 6. 할인 금액 계산
            $discountAmount = $this->calculateDiscountAmount($coupon, $orderContext);

            $result['applicable'] = true;
            $result['discount_amount'] = $discountAmount;
            $result['reasons'][] = 'All validation checks passed';

            Log::info('Coupon applicability analysis completed', [
                'coupon_id' => $coupon->id,
                'applicable' => true,
                'discount_amount' => $discountAmount,
            ]);

        } catch (\Exception $e) {
            $result['reasons'][] = 'Analysis error: ' . $e->getMessage();

            Log::error('Coupon applicability analysis failed', [
                'coupon_id' => $coupon->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * 사용자에게 적용 가능한 쿠폰들을 분석
     */
    public function getApplicableCouponsForUser(User $user, array $orderContext = []): array
    {
        $applicableCoupons = [];
        $orderContext['user_id'] = $user->id;

        // 사용자의 활성 쿠폰들 조회
        $coupons = $user->availableCoupons()
            ->with('promotion')
            ->get();

        foreach ($coupons as $coupon) {
            $analysis = $this->analyzeCouponApplicability($coupon, $orderContext);

            if ($analysis['applicable']) {
                $applicableCoupons[] = [
                    'coupon' => $coupon,
                    'analysis' => $analysis,
                    'priority_score' => $this->calculatePriorityScore($coupon, $analysis),
                ];
            }
        }

        // 우선순위별로 정렬
        usort($applicableCoupons, function ($a, $b) {
            return $b['priority_score'] <=> $a['priority_score'];
        });

        return $applicableCoupons;
    }

    /**
     * 최적의 쿠폰 조합을 찾기
     */
    public function findOptimalCouponCombination(User $user, array $orderContext): array
    {
        $applicableCoupons = $this->getApplicableCouponsForUser($user, $orderContext);

        if (empty($applicableCoupons)) {
            return [
                'coupons' => [],
                'total_discount' => 0,
                'savings' => 0,
            ];
        }

        // 단일 쿠폰 사용 (현재는 단순 구현, 추후 복합 쿠폰 로직 추가 가능)
        $bestCoupon = $applicableCoupons[0];

        return [
            'coupons' => [$bestCoupon['coupon']],
            'total_discount' => $bestCoupon['analysis']['discount_amount'],
            'savings' => $bestCoupon['analysis']['discount_amount'],
            'analysis' => $bestCoupon['analysis'],
        ];
    }

    /**
     * 기본 쿠폰 상태 검증
     */
    private function validateBasicCouponStatus(Coupon $coupon, array &$result): bool
    {
        if (!$coupon->isUsable()) {
            $result['reasons'][] = 'Coupon is not usable (status: ' . $coupon->status . ')';
            return false;
        }

        if ($coupon->isExpired()) {
            $result['reasons'][] = 'Coupon has expired';
            return false;
        }

        return true;
    }

    /**
     * 프로모션 상태 검증
     */
    private function validatePromotionStatus(Promotion $promotion, array &$result): bool
    {
        if (!$promotion->isCurrentlyActive()) {
            $result['reasons'][] = 'Promotion is not currently active';
            return false;
        }

        if (!$promotion->hasAvailableUsage()) {
            $result['reasons'][] = 'Promotion usage limit exceeded';
            return false;
        }

        return true;
    }

    /**
     * 사용자 타겟팅 검증
     */
    private function validateUserTargeting(Promotion $promotion, User $user, array &$result): bool
    {
        if (!$promotion->matchesTargeting($user)) {
            $result['reasons'][] = 'User does not match promotion targeting criteria';
            return false;
        }

        if (!$promotion->canUserUse($user)) {
            $result['reasons'][] = 'User has exceeded per-user usage limit';
            return false;
        }

        return true;
    }

    /**
     * 주문 조건 검증
     */
    private function validateOrderConditions(Coupon $coupon, array $orderContext, array &$result): bool
    {
        if (!$coupon->canApplyTo($orderContext)) {
            $result['reasons'][] = 'Order does not meet coupon usage restrictions';
            return false;
        }

        // 최소 주문 금액 검사
        $promotion = $coupon->promotion;
        $minAmount = $promotion->conditions['min_amount'] ?? 0;

        if (($orderContext['amount'] ?? 0) < $minAmount) {
            $result['reasons'][] = "Minimum order amount not met (required: {$minAmount})";
            return false;
        }

        return true;
    }

    /**
     * 사용 제한 검증
     */
    private function validateUsageLimits(Coupon $coupon, array $orderContext, array &$result): bool
    {
        $restrictions = $coupon->usage_restrictions ?? [];

        // 카테고리 제한 검사
        if (!empty($restrictions['categories'])) {
            $orderCategories = $orderContext['categories'] ?? [];
            if (!array_intersect($restrictions['categories'], $orderCategories)) {
                $result['reasons'][] = 'Order categories do not match coupon restrictions';
                return false;
            }
        }

        // 제외 카테고리 검사
        if (!empty($restrictions['excluded_categories'])) {
            $orderCategories = $orderContext['categories'] ?? [];
            if (array_intersect($restrictions['excluded_categories'], $orderCategories)) {
                $result['reasons'][] = 'Order contains excluded categories';
                return false;
            }
        }

        return true;
    }

    /**
     * 할인 금액 계산
     */
    private function calculateDiscountAmount(Coupon $coupon, array $orderContext): float
    {
        $orderAmount = $orderContext['amount'] ?? 0;
        return $coupon->calculateDiscount($orderAmount);
    }

    /**
     * 쿠폰 우선순위 점수 계산
     */
    private function calculatePriorityScore(Coupon $coupon, array $analysis): int
    {
        $score = 0;

        // 할인 금액 기반 점수 (높을수록 좋음)
        $score += min($analysis['discount_amount'] * 10, 1000);

        // 프로모션 우선순위
        $score += $coupon->promotion->priority * 100;

        // 만료일까지의 시간 (빨리 만료될수록 우선)
        $daysToExpiry = $coupon->expires_at->diffInDays(now());
        $score += max(0, 100 - $daysToExpiry);

        return (int)$score;
    }

    /**
     * 쿠폰 규칙을 구조화된 형태로 분석
     */
    public function parsePromotionRules(Promotion $promotion): array
    {
        return [
            'id' => $promotion->id,
            'name' => $promotion->name,
            'type' => $promotion->type,
            'value' => $promotion->value,
            'conditions' => $promotion->conditions ?? [],
            'targeting_rules' => $promotion->targeting_rules ?? [],
            'usage_limits' => [
                'max_total_usage' => $promotion->max_usage_count,
                'max_per_user' => $promotion->max_usage_per_user,
                'current_usage' => $promotion->current_usage_count,
            ],
            'validity_period' => [
                'start_date' => $promotion->start_date->toISOString(),
                'end_date' => $promotion->end_date->toISOString(),
                'is_active' => $promotion->is_active,
                'is_current' => $promotion->isCurrentlyActive(),
            ],
            'priority' => $promotion->priority,
            'metadata' => $promotion->metadata ?? [],
        ];
    }
}
