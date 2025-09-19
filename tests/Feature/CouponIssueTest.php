<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\UserLevel;
use App\Models\Promotion;
use App\Models\Coupon;
use App\Services\CouponIndexService;

class CouponIssueTest extends TestCase
{
    use RefreshDatabase;

    private CouponIndexService $indexService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->indexService = app(CouponIndexService::class);
    }

    /** @test */
    public function 쿠폰을_정상적으로_발행할_수_있다()
    {
        // Given: 사용자와 프로모션 준비
        $userLevel = UserLevel::factory()->create();
        $user = User::factory()->create(['user_level_id' => $userLevel->id]);
        $promotion = Promotion::factory()->create([
            'name' => '테스트 프로모션',
            'type' => 'percentage',
            'value' => 15.00,
            'is_active' => true,
        ]);

        // When: 쿠폰 발행 API 호출
        $response = $this->postJson('/api/coupons/issue', [
            'promotion_id' => $promotion->id,
            'user_id' => $user->id,
            'expires_days' => 30,
        ]);

        // Then: 쿠폰이 성공적으로 발행되었는지 확인
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Coupon issued successfully',
                ]);

        $responseData = $response->json('data');

        // 응답 데이터 구조 확인
        $this->assertArrayHasKey('coupon', $responseData);
        $this->assertArrayHasKey('promotion', $responseData);
        $this->assertArrayHasKey('user', $responseData);

        // 쿠폰 데이터 확인
        $couponData = $responseData['coupon'];
        $this->assertEquals($promotion->id, $couponData['promotion_id']);
        $this->assertEquals($user->id, $couponData['user_id']);
        $this->assertEquals('active', $couponData['status']);
        $this->assertNotEmpty($couponData['code']);
        $this->assertEquals(15.00, $couponData['discount_amount']);

        // 데이터베이스에 저장되었는지 확인
        $this->assertDatabaseHas('coupons', [
            'id' => $couponData['id'],
            'promotion_id' => $promotion->id,
            'user_id' => $user->id,
            'code' => $couponData['code'],
            'status' => 'active',
        ]);
    }

    /** @test */
    public function 비활성_프로모션으로는_쿠폰을_발행할_수_없다()
    {
        // Given: 비활성 프로모션
        $user = User::factory()->create();
        $promotion = Promotion::factory()->create([
            'is_active' => false, // 비활성 상태
        ]);

        // When: 쿠폰 발행 시도
        $response = $this->postJson('/api/coupons/issue', [
            'promotion_id' => $promotion->id,
            'user_id' => $user->id,
        ]);

        // Then: 실패 응답
        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Promotion is not active',
                ]);

        // 쿠폰이 생성되지 않았는지 확인
        $this->assertEquals(0, Coupon::where('promotion_id', $promotion->id)->count());
    }

    /** @test */
    public function 존재하지_않는_사용자로는_쿠폰을_발행할_수_없다()
    {
        // Given: 존재하지 않는 사용자 ID
        $promotion = Promotion::factory()->create(['is_active' => true]);
        $nonExistentUserId = 99999;

        // When: 쿠폰 발행 시도
        $response = $this->postJson('/api/coupons/issue', [
            'promotion_id' => $promotion->id,
            'user_id' => $nonExistentUserId,
        ]);

        // Then: 유효성 검사 실패
        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid request data',
                ]);

        $this->assertArrayHasKey('errors', $response->json());
    }

    /** @test */
    public function 쿠폰_코드는_중복되지_않는다()
    {
        // Given: 같은 프로모션으로 여러 쿠폰 발행
        $user = User::factory()->create();
        $promotion = Promotion::factory()->create(['is_active' => true]);

        $codes = [];

        // When: 여러 개의 쿠폰 발행
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/coupons/issue', [
                'promotion_id' => $promotion->id,
                'user_id' => $user->id,
            ]);

            $response->assertStatus(200);
            $code = $response->json('data.coupon.code');
            $codes[] = $code;
        }

        // Then: 모든 코드가 중복되지 않음
        $this->assertEquals(5, count(array_unique($codes)), '쿠폰 코드가 중복되었습니다');
        $this->assertEquals(5, Coupon::where('user_id', $user->id)->count());
    }

    /** @test */
    public function 발행된_쿠폰이_사용자_쿠폰_목록에_나타난다()
    {
        // Given: 쿠폰 발행
        $user = User::factory()->create();
        $promotion = Promotion::factory()->create(['is_active' => true]);

        $issueResponse = $this->postJson('/api/coupons/issue', [
            'promotion_id' => $promotion->id,
            'user_id' => $user->id,
        ]);

        $issueResponse->assertStatus(200);
        $issuedCouponId = $issueResponse->json('data.coupon.id');

        // When: 사용자 쿠폰 목록 조회
        $listResponse = $this->getJson("/api/coupons/user/{$user->id}");

        // Then: 발행한 쿠폰이 목록에 포함됨
        $listResponse->assertStatus(200);
        $coupons = $listResponse->json('data.coupons');

        $couponIds = collect($coupons)->pluck('coupon.id')->toArray();
        $this->assertContains($issuedCouponId, $couponIds, '발행된 쿠폰이 사용자 쿠폰 목록에 없습니다');
    }

    /** @test */
    public function 만료일을_지정해서_쿠폰을_발행할_수_있다()
    {
        // Given: 사용자와 프로모션
        $user = User::factory()->create();
        $promotion = Promotion::factory()->create(['is_active' => true]);
        $expiresInDays = 7;

        // When: 만료일 지정해서 쿠폰 발행
        $response = $this->postJson('/api/coupons/issue', [
            'promotion_id' => $promotion->id,
            'user_id' => $user->id,
            'expires_days' => $expiresInDays,
        ]);

        // Then: 지정한 만료일로 쿠폰 생성
        $response->assertStatus(200);

        $couponId = $response->json('data.coupon.id');
        $coupon = Coupon::find($couponId);

        $expectedExpiryDate = now()->addDays($expiresInDays)->format('Y-m-d');
        $actualExpiryDate = $coupon->expires_at->format('Y-m-d');

        $this->assertEquals($expectedExpiryDate, $actualExpiryDate);
    }

    /** @test */
    public function 쿠폰_발행_통합_워크플로우_테스트()
    {
        // Given: 전체 시스템 준비
        $userLevel = UserLevel::factory()->create(['name' => 'Gold']);
        $user = User::factory()->create(['user_level_id' => $userLevel->id]);
        $promotion = Promotion::factory()->create([
            'name' => '골드 회원 할인',
            'type' => 'percentage',
            'value' => 20.00,
            'is_active' => true,
        ]);

        // When: 쿠폰 발행
        $issueResponse = $this->postJson('/api/coupons/issue', [
            'promotion_id' => $promotion->id,
            'user_id' => $user->id,
            'expires_days' => 14,
        ]);

        // Then: 발행 성공
        $issueResponse->assertStatus(200);
        $couponCode = $issueResponse->json('data.coupon.code');

        // When: 발행된 쿠폰 적용 가능성 분석
        $analyzeResponse = $this->postJson("/api/coupons/{$couponCode}/analyze", [
            'user_id' => $user->id,
            'amount' => 50000,
            'categories' => ['electronics'],
        ]);

        // Then: 분석 성공
        $analyzeResponse->assertStatus(200);
        $analysis = $analyzeResponse->json('data.analysis');

        $this->assertArrayHasKey('applicable', $analysis);
        $this->assertArrayHasKey('discount_amount', $analysis);

        // 골드 회원이므로 할인 적용 가능해야 함
        $this->assertGreaterThan(0, $analysis['discount_amount']);

        // When: 사용자 쿠폰 목록에서 확인
        $listResponse = $this->getJson("/api/coupons/user/{$user->id}");

        // Then: 목록에서 발행된 쿠폰 확인
        $listResponse->assertStatus(200);
        $this->assertGreaterThan(0, $listResponse->json('data.total_count'));
    }
}
