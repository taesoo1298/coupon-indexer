<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CouponIndexService;
use App\Services\CouponRuleEngine;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CouponController extends Controller
{
    public function __construct(
        private CouponIndexService $indexService,
        private CouponRuleEngine $ruleEngine
    ) {}

    /**
     * 사용자의 사용 가능한 쿠폰 목록 조회
     */
    public function getUserCoupons(Request $request, int $userId): JsonResponse
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            // Redis에서 사용자 쿠폰 조회
            $coupons = $this->indexService->getUserAvailableCoupons($userId);

            // 주문 컨텍스트가 제공된 경우 적용 가능성 분석
            $orderContext = $this->extractOrderContext($request);
            $analyzedCoupons = [];

            foreach ($coupons as $couponData) {
                $coupon = \App\Models\Coupon::find($couponData['id']);

                if ($coupon && !empty($orderContext)) {
                    $analysis = $this->ruleEngine->analyzeCouponApplicability($coupon, $orderContext);
                    $analyzedCoupons[] = [
                        'coupon' => $couponData,
                        'applicable' => $analysis['applicable'],
                        'discount_amount' => $analysis['discount_amount'],
                        'reasons' => $analysis['reasons'],
                    ];
                } else {
                    $analyzedCoupons[] = [
                        'coupon' => $couponData,
                        'applicable' => null, // 분석 안됨
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'coupons' => $analyzedCoupons,
                    'total_count' => count($analyzedCoupons),
                    'order_context_provided' => !empty($orderContext),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get user coupons', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user coupons',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 최적의 쿠폰 조합 추천
     */
    public function getOptimalCoupons(Request $request, int $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'categories' => 'array',
            'products' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            $orderContext = $this->extractOrderContext($request);
            $optimalCombination = $this->ruleEngine->findOptimalCouponCombination($user, $orderContext);

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'order_context' => $orderContext,
                    'optimal_combination' => $optimalCombination,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get optimal coupons', [
                'user_id' => $userId,
                'order_context' => $orderContext ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to find optimal coupons',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 특정 쿠폰의 적용 가능성 분석
     */
    public function analyzeCoupon(Request $request, string $couponCode): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'categories' => 'array',
            'products' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $coupon = \App\Models\Coupon::where('code', $couponCode)->first();

            if (!$coupon) {
                return response()->json([
                    'success' => false,
                    'message' => 'Coupon not found',
                ], 404);
            }

            $orderContext = $this->extractOrderContext($request);
            $analysis = $this->ruleEngine->analyzeCouponApplicability($coupon, $orderContext);

            return response()->json([
                'success' => true,
                'data' => [
                    'coupon_code' => $couponCode,
                    'coupon_id' => $coupon->id,
                    'analysis' => $analysis,
                    'order_context' => $orderContext,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to analyze coupon', [
                'coupon_code' => $couponCode,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze coupon',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 프로모션에 적용 가능한 사용자 조회
     */
    public function getEligibleUsers(int $promotionId): JsonResponse
    {
        try {
            $promotion = \App\Models\Promotion::find($promotionId);

            if (!$promotion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Promotion not found',
                ], 404);
            }

            $eligibleUserIds = $this->indexService->getEligibleUsersForPromotion($promotionId);

            // 사용자 기본 정보도 함께 조회
            $users = User::whereIn('id', $eligibleUserIds)
                ->select('id', 'email', 'user_level_id', 'points', 'total_purchase_amount')
                ->with('userLevel:id,name,code')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'promotion_id' => $promotionId,
                    'promotion_name' => $promotion->name,
                    'eligible_users' => $users,
                    'total_count' => $users->count(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get eligible users', [
                'promotion_id' => $promotionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get eligible users',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 만료 예정 쿠폰 조회
     */
    public function getExpiringCoupons(Request $request): JsonResponse
    {
        $hours = $request->get('hours', 24);

        if (!is_numeric($hours) || $hours < 1 || $hours > 168) { // 최대 7일
            return response()->json([
                'success' => false,
                'message' => 'Invalid hours parameter (must be between 1 and 168)',
            ], 400);
        }

        try {
            $expiringCoupons = $this->indexService->getExpiringCoupons((int)$hours);

            return response()->json([
                'success' => true,
                'data' => [
                    'hours' => (int)$hours,
                    'expiring_coupons' => $expiringCoupons,
                    'total_count' => count($expiringCoupons),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get expiring coupons', [
                'hours' => $hours,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get expiring coupons',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 쿠폰 발행
     */
    public function issueCoupon(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'promotion_id' => 'required|integer|exists:promotions,id',
            'user_id' => 'required|integer|exists:users,id',
            'expires_days' => 'integer|min:1|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $promotion = \App\Models\Promotion::find($request->promotion_id);
            $user = User::find($request->user_id);

            // 프로모션 활성 상태 확인
            if (!$promotion->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Promotion is not active',
                ], 400);
            }

            // 쿠폰 생성
            $coupon = \App\Models\Coupon::create([
                'promotion_id' => $promotion->id,
                'user_id' => $user->id,
                'code' => $this->generateCouponCode(),
                'status' => 'active',
                'issued_at' => now(),
                'expires_at' => now()->addDays($request->get('expires_days', 30)),
                'discount_amount' => $promotion->value,
            ]);

            // 인덱스에 추가
            $this->indexService->indexCoupon($coupon);

            return response()->json([
                'success' => true,
                'message' => 'Coupon issued successfully',
                'data' => [
                    'coupon' => [
                        'id' => $coupon->id,
                        'code' => $coupon->code,
                        'status' => $coupon->status,
                        'promotion_id' => $coupon->promotion_id,
                        'user_id' => $coupon->user_id,
                        'issued_at' => $coupon->issued_at->toISOString(),
                        'expires_at' => $coupon->expires_at->toISOString(),
                        'discount_amount' => $coupon->discount_amount,
                    ],
                    'promotion' => [
                        'id' => $promotion->id,
                        'name' => $promotion->name,
                        'type' => $promotion->type,
                        'value' => $promotion->value,
                    ],
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to issue coupon', [
                'promotion_id' => $request->promotion_id,
                'user_id' => $request->user_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to issue coupon',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 인덱스 상태 조회
     */
    public function getIndexStatus(): JsonResponse
    {
        try {
            $stats = \App\Models\CouponIndexStatus::getStatusStats();
            $redis = \Illuminate\Support\Facades\Redis::connection('coupon_index');

            // Redis 연결 상태 확인
            $redisInfo = [
                'connected' => true,
                'memory_usage' => null,
                'key_count' => null,
            ];

            try {
                $info = $redis->info('memory');
                $redisInfo['memory_usage'] = $info['used_memory_human'] ?? null;
                $redisInfo['key_count'] = $redis->dbSize();
            } catch (\Exception $e) {
                $redisInfo['connected'] = false;
                $redisInfo['error'] = $e->getMessage();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'index_status' => $stats,
                    'redis_info' => $redisInfo,
                    'timestamp' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get index status', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get index status',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 요청에서 주문 컨텍스트 추출
     */
    private function extractOrderContext(Request $request): array
    {
        return [
            'user_id' => $request->get('user_id'),
            'amount' => (float)($request->get('amount', 0)),
            'categories' => $request->get('categories', []),
            'products' => $request->get('products', []),
            'shipping_method' => $request->get('shipping_method'),
            'payment_method' => $request->get('payment_method'),
        ];
    }

    /**
     * 쿠폰 코드 생성
     */
    private function generateCouponCode(): string
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10));
        } while (\App\Models\Coupon::where('code', $code)->exists());

        return $code;
    }
}
