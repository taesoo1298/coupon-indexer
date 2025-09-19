# 🎯 쿠폰 발행 테스트 가이드

## 📋 구현 완료 사항

### 1. API 엔드포인트 추가
- `POST /api/coupons/issue` - 쿠폰 발행

### 2. CouponController 메서드 추가
- `issueCoupon()` - 쿠폰 발행 로직
- `generateCouponCode()` - 고유 쿠폰 코드 생성

### 3. 테스트 코드 작성
- `CouponIssueTest.php` - 8개의 테스트 케이스

## 🚀 테스트 실행 방법

### 간단한 테스트
```bash
php artisan test --filter=CouponIssueTest
```

### 개별 테스트 실행
```bash
php artisan test --filter=쿠폰을_정상적으로_발행할_수_있다
```

## 📝 API 사용 예제

### 쿠폰 발행
```bash
curl -X POST http://localhost:8000/api/coupons/issue \
  -H "Content-Type: application/json" \
  -d '{
    "promotion_id": 1,
    "user_id": 1,
    "expires_days": 30
  }'
```

### 응답 예시
```json
{
  "success": true,
  "message": "Coupon issued successfully",
  "data": {
    "coupon": {
      "id": 138,
      "code": "ABC123XYZ9",
      "status": "active",
      "promotion_id": 1,
      "user_id": 1,
      "issued_at": "2025-09-19T02:30:00.000000Z",
      "expires_at": "2025-10-19T02:30:00.000000Z",
      "discount_amount": "15.00"
    }
  }
}
```

## 🔍 테스트 케이스

1. **정상 발행** - 프로모션과 사용자가 유효할 때
2. **비활성 프로모션** - 비활성 프로모션으로는 발행 불가
3. **존재하지 않는 사용자** - 유효성 검사 실패
4. **코드 중복 방지** - 여러 쿠폰의 코드가 중복되지 않음
5. **사용자 목록 연동** - 발행된 쿠폰이 사용자 쿠폰 목록에 표시됨
6. **만료일 설정** - 지정한 만료일로 쿠폰 생성
7. **통합 워크플로우** - 전체 시스템이 연동되어 작동

## 🛠️ 트러블슈팅

### 외래키 제약 조건 오류
시드 데이터를 먼저 로드하거나 Factory에서 관계를 올바르게 설정하세요.

### Redis 연결 오류
Redis가 실행 중이 아니어도 테스트는 계속 진행됩니다.

## 📚 다음 단계

1. 테스트 실행으로 기능 확인
2. Postman/curl로 실제 API 테스트
3. 쿠폰 사용/만료 기능 추가
4. 배치 쿠폰 발행 기능 확장
