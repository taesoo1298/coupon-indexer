<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CouponController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| 쿠폰 인덱서 API 라우트들
|
*/

Route::middleware('api')->prefix('api/v1')->group(function () {

    // 쿠폰 관련 API
    Route::prefix('coupons')->group(function () {

        // 사용자 쿠폰 관련
        Route::get('users/{userId}', [CouponController::class, 'getUserCoupons'])
            ->name('api.coupons.user-coupons');

        Route::get('users/{userId}/optimal', [CouponController::class, 'getOptimalCoupons'])
            ->name('api.coupons.optimal-coupons');

        // 쿠폰 분석
        Route::post('{couponCode}/analyze', [CouponController::class, 'analyzeCoupon'])
            ->name('api.coupons.analyze');

        // 만료 예정 쿠폰
        Route::get('expiring', [CouponController::class, 'getExpiringCoupons'])
            ->name('api.coupons.expiring');
    });

    // 프로모션 관련 API
    Route::prefix('promotions')->group(function () {
        Route::get('{promotionId}/eligible-users', [CouponController::class, 'getEligibleUsers'])
            ->name('api.promotions.eligible-users');
    });

    // 시스템 상태 API
    Route::get('status', [CouponController::class, 'getIndexStatus'])
        ->name('api.system.status');
});

// 헬스 체크 엔드포인트
Route::get('health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'service' => 'coupon-indexer',
    ]);
})->name('health-check');
