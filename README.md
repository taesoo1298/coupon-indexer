<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# Coupon Indexer System

고성능 실시간 쿠폰 적용 로직 및 정책 판단을 지원하는 이벤트 기반 쿠폰 인덱싱 시스템입니다.

## 🎯 주요 기능

- **실시간 이벤트 감지**: 쿠폰 발급, 프로모션 생성/수정, 사용자 등급 변화 등 모든 관련 이벤트를 실시간으로 감지
- **고성능 인덱싱**: Redis 기반 인덱싱으로 빠른 쿠폰 적용 규칙 분석
- **이벤트 기반 아키텍처**: Pub/Sub 패턴으로 확장 가능한 비동기 처리
- **규칙 엔진**: 복잡한 쿠폰 적용 규칙을 실시간으로 분석하고 평가
- **모니터링 및 복구**: 시스템 상태 모니터링과 자동 복구 메커니즘

## 🏗️ 시스템 아키텍처

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Events        │    │   Queue Jobs    │    │   Redis Index   │
│   ────────      │    │   ──────────    │    │   ───────────   │
│ • CouponIssued  │───▶│ • ProcessEvent  │───▶│ • User Index    │
│ • UserLevelUp   │    │ • FullSync      │    │ • Coupon Index  │
│ • PromoCreated  │    │ • Cleanup       │    │ • Rule Cache    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Pub/Sub       │    │   Rule Engine   │    │   API Layer     │
│   ─────────     │    │   ───────────   │    │   ─────────     │
│ • Redis Stream  │    │ • Evaluate      │    │ • Applicable    │
│ • Event Dist.   │    │ • Optimize      │    │ • Optimal       │
│ • Monitoring    │    │ • Cache Rules   │    │ • Health Check  │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## 🚀 빠른 시작

### 1. 환경 설정

```bash
# 환경 변수 설정 (.env)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

QUEUE_CONNECTION=redis
```

### 2. 시스템 설치

```bash
# 의존성 설치
composer install

# 시스템 초기화
php artisan coupon:setup

# 데이터베이스 마이그레이션
php artisan migrate

# 큐 워커 시작
php artisan queue:work --queue=coupon_events
php artisan queue:work --queue=coupon_sync
php artisan queue:work --queue=coupon_cleanup
```

### 3. 이벤트 구독자 시작

```bash
# Redis Pub/Sub 이벤트 구독 시작
php artisan coupon:subscribe
```

## 📊 모니터링

### 시스템 상태 확인

```bash
# 전체 시스템 상태
php artisan coupon:monitor health

# 일관성 검사
php artisan coupon:monitor consistency-check

# 실패한 이벤트 재시도
php artisan coupon:monitor retry-failed

# 유지보수 실행
php artisan coupon:monitor maintenance
```

### 인덱스 동기화

```bash
# 전체 동기화
php artisan coupon:sync --full

# 증분 동기화
php artisan coupon:sync

# 특정 사용자 동기화
php artisan coupon:sync --user=123
```

## 🔌 API 사용법

### 적용 가능한 쿠폰 조회

```http
GET /api/coupons/user/{userId}/applicable
Content-Type: application/json

{
    "purchase_amount": 50000,
    "categories": ["electronics", "books"],
    "items": [
        {"id": 1, "price": 30000, "category": "electronics"},
        {"id": 2, "price": 20000, "category": "books"}
    ]
}
```

### 최적 쿠폰 조합 추천

```http
GET /api/coupons/user/{userId}/optimal
Content-Type: application/json

{
    "purchase_amount": 100000,
    "categories": ["electronics"]
}
```

### 쿠폰 적용 가능성 검사

```http
POST /api/coupons/{couponId}/check-applicability
Content-Type: application/json

{
    "user_id": 123,
    "purchase_context": {
        "amount": 50000,
        "categories": ["electronics"]
    }
}
```

## 📋 데이터베이스 스키마

### 주요 테이블

- **promotions**: 프로모션 정보 및 규칙
- **coupons**: 발급된 쿠폰 정보
- **user_levels**: 사용자 등급 정보
- **coupon_events**: 이벤트 로그
- **coupon_index_status**: 인덱스 동기화 상태

### Redis 인덱스 구조

```
coupon:user_indexes:{user_id}     - 사용자별 쿠폰 인덱스
coupon:promotion_indexes:{promo_id} - 프로모션별 인덱스
coupon:rule_cache:{rule_hash}     - 규칙 분석 결과 캐시
coupon:applicable_cache:{key}     - 적용 가능성 결과 캐시
```

## 🔧 구성 요소

### Models
- `Promotion`: 프로모션 관리
- `Coupon`: 쿠폰 라이프사이클
- `User`: 사용자 정보 (확장)
- `UserLevel`: 사용자 등급
- `CouponEvent`: 이벤트 로깅

### Services
- `CouponRuleEngine`: 규칙 분석 엔진
- `CouponIndexService`: Redis 인덱싱
- `RedisPubSubService`: 이벤트 배포
- `CouponEventLogger`: 이벤트 로깅
- `CouponMonitoringService`: 시스템 모니터링

### Jobs
- `ProcessCouponEventJob`: 이벤트 처리
- `FullSyncCouponIndexJob`: 전체 동기화
- `ExpiredCouponCleanupJob`: 만료 쿠폰 정리

### Commands
- `coupon:setup`: 시스템 초기화
- `coupon:subscribe`: 이벤트 구독
- `coupon:monitor`: 시스템 모니터링
- `coupon:sync`: 인덱스 동기화

## 🔒 보안 고려사항

- Redis 연결 보안 설정
- API 인증 및 권한 검사
- 입력 데이터 검증
- 레이트 리미팅

## 📈 성능 최적화

- Redis 인덱싱으로 O(1) 조회
- 규칙 분석 결과 캐싱
- 비동기 이벤트 처리
- 배치 인덱스 업데이트

## 🛠️ 트러블슈팅

### 일반적인 문제들

1. **Redis 연결 실패**
   ```bash
   php artisan coupon:monitor health
   ```

2. **인덱스 불일치**
   ```bash
   php artisan coupon:monitor consistency-check
   php artisan coupon:sync --full
   ```

3. **이벤트 처리 지연**
   ```bash
   php artisan queue:work --queue=coupon_events --tries=3
   ```

### 로그 확인

```bash
# 애플리케이션 로그
tail -f storage/logs/laravel.log

# 큐 실패 로그
php artisan queue:failed

# 이벤트 로그 (데이터베이스)
SELECT * FROM coupon_events ORDER BY created_at DESC LIMIT 100;
```

## 🧪 테스트

```bash
# 전체 테스트 실행
php artisan test

# 특정 테스트 실행
php artisan test tests/Feature/CouponIndexerSystemTest.php

# 커버리지 리포트
php artisan test --coverage
```

## 📚 추가 문서

- [API 문서](docs/api.md)
- [아키텍처 가이드](docs/architecture.md)
- [배포 가이드](docs/deployment.md)

## 🤝 기여하기

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## 📄 라이센스

이 프로젝트는 MIT 라이센스 하에 배포됩니다.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
