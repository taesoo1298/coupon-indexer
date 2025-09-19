<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Promotion;
use App\Models\Coupon;
use App\Services\CouponRuleEngine;
use App\Services\CouponIndexService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;

class CouponIndexerSystemTest extends TestCase
{
    use RefreshDatabase;

    private CouponRuleEngine $ruleEngine;
    private CouponIndexService $indexService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ruleEngine = app(CouponRuleEngine::class);
        $this->indexService = app(CouponIndexService::class);

        // Redis 테스트 데이터 정리
        Redis::connection('coupon_index')->flushdb();
    }

    /** @test */
    public function it_can_create_and_index_promotions()
    {
        // Given: 프로모션 생성
        $promotion = Promotion::factory()->create([
            'name' => 'Test Promotion',
            'rules' => [
                'user_level' => ['bronze', 'silver'],
                'min_purchase_amount' => 10000,
                'applicable_categories' => ['electronics', 'books'],
                'max_usage_per_user' => 3,
            ],
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
        ]);

        // When: 프로모션이 인덱싱되는지 확인
        $this->indexService->indexPromotion($promotion);

        // Then: Redis에 인덱스가 생성되었는지 확인
        $promotionKey = "coupon:promotion:{$promotion->id}";
        $this->assertTrue(Redis::connection('coupon_index')->exists($promotionKey));

        $indexData = Redis::connection('coupon_index')->hgetall($promotionKey);
        $this->assertEquals($promotion->name, $indexData['name']);
        $this->assertNotEmpty($indexData['rules']);
    }

    /** @test */
    public function it_can_evaluate_coupon_applicability()
    {
        // Given: 사용자와 프로모션 생성
        $user = User::factory()->create(['level' => 'silver']);

        $promotion = Promotion::factory()->create([
            'rules' => [
                'user_level' => ['silver', 'gold'],
                'min_purchase_amount' => 5000,
            ],
            'is_active' => true,
        ]);

        $coupon = Coupon::factory()->create([
            'promotion_id' => $promotion->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        // When: 쿠폰 적용 가능성 검사
        $purchaseContext = [
            'amount' => 10000,
            'items' => [
                ['category' => 'electronics', 'price' => 5000],
                ['category' => 'books', 'price' => 5000],
            ],
        ];

        $result = $this->ruleEngine->analyzeCouponApplicability(
            $coupon,
            $purchaseContext
        );

        // Then: 적용 가능해야 함
        $this->assertTrue($result['applicable']);
        $this->assertEmpty($result['reasons']);
    }

    /** @test */
    public function it_can_find_applicable_coupons_via_api()
    {
        // Given: 사용자와 적용 가능한 쿠폰들
        $user = User::factory()->create(['level' => 'gold']);

        $promotion1 = Promotion::factory()->create([
            'rules' => ['user_level' => ['gold', 'platinum']],
            'discount_value' => 15,
            'is_active' => true,
        ]);

        $promotion2 = Promotion::factory()->create([
            'rules' => ['user_level' => ['silver', 'gold']],
            'discount_value' => 10,
            'is_active' => true,
        ]);

        $coupon1 = Coupon::factory()->create([
            'promotion_id' => $promotion1->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $coupon2 = Coupon::factory()->create([
            'promotion_id' => $promotion2->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        // 인덱싱
        $this->indexService->indexUser($user);

        // When: API 호출
        $response = $this->getJson("/api/coupons/user/{$user->id}/applicable", [
            'purchase_amount' => 20000,
            'categories' => ['electronics'],
        ]);

        // Then: 적용 가능한 쿠폰들이 반환되어야 함
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'coupons' => [
                         '*' => [
                             'id',
                             'promotion_id',
                             'discount_amount',
                             'applicable',
                             'reasons'
                         ]
                     ]
                 ]);

        $coupons = $response->json('coupons');
        $this->assertCount(2, $coupons);
    }

    /** @test */
    public function it_can_handle_concurrent_coupon_usage()
    {
        // Given: 사용량 제한이 있는 프로모션
        $promotion = Promotion::factory()->create([
            'rules' => [
                'max_usage_per_user' => 1,
                'max_total_usage' => 5,
            ],
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $coupon = Coupon::factory()->create([
            'promotion_id' => $promotion->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        // When: 동시에 여러 번 사용 시도
        $results = [];
        for ($i = 0; $i < 3; $i++) {
            $results[] = $this->ruleEngine->analyzeCouponApplicability(
                $coupon,
                ['amount' => 10000]
            );
        }

        // Then: 첫 번째만 성공해야 함 (실제 사용 로직에서)
        $this->assertTrue($results[0]['applicable']);

        // 사용 후 상태 체크
        $coupon->refresh();
        $this->assertEquals('active', $coupon->status); // 아직 사용되지 않음
    }

    /** @test */
    public function it_maintains_index_consistency()
    {
        // Given: 여러 사용자와 쿠폰들
        $users = User::factory()->count(5)->create();
        $promotions = Promotion::factory()->count(3)->create(['is_active' => true]);

        foreach ($users as $user) {
            foreach ($promotions as $promotion) {
                Coupon::factory()->create([
                    'promotion_id' => $promotion->id,
                    'user_id' => $user->id,
                    'status' => 'active',
                ]);
            }
        }

        // When: 전체 동기화
        foreach ($users as $user) {
            $this->indexService->indexUser($user);
        }

        // Then: 인덱스와 데이터베이스 일관성 확인
        foreach ($users as $user) {
            $dbCoupons = Coupon::where('user_id', $user->id)
                              ->where('status', 'active')
                              ->count();

            $redisKey = "coupon:user_indexes:{$user->id}";
            $indexedCoupons = Redis::connection('coupon_index')->hlen($redisKey);

            $this->assertEquals($dbCoupons, $indexedCoupons);
        }
    }

    protected function tearDown(): void
    {
        // Redis 테스트 데이터 정리
        Redis::connection('coupon_index')->flushdb();

        parent::tearDown();
    }
}
