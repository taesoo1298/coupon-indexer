<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Coupon Indexer Configuration
    |--------------------------------------------------------------------------
    |
    | 쿠폰 인덱서 시스템의 전체적인 설정을 관리합니다.
    |
    */

    'enabled' => env('COUPON_INDEXER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Redis 설정
    |--------------------------------------------------------------------------
    */
    'redis' => [
        'connection' => 'coupon_index',
        'prefix' => env('COUPON_INDEX_PREFIX', 'coupon_idx:'),
        'ttl' => env('COUPON_INDEX_TTL', 86400), // 24시간
    ],

    /*
    |--------------------------------------------------------------------------
    | 이벤트 처리 설정
    |--------------------------------------------------------------------------
    */
    'events' => [
        'channel' => env('REDIS_PUB_SUB_CHANNEL', 'coupon_events'),
        'queue' => 'coupon_events',
        'retry_attempts' => env('COUPON_EVENT_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('COUPON_EVENT_RETRY_DELAY', 60), // 초
    ],

    /*
    |--------------------------------------------------------------------------
    | 동기화 설정
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'enabled' => env('COUPON_ENABLE_FULL_SYNC', true),
        'interval' => env('COUPON_SYNC_INTERVAL', 3600), // 1시간
        'batch_size' => env('COUPON_SYNC_BATCH_SIZE', 1000),
        'chunk_size' => env('COUPON_SYNC_CHUNK_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | 인덱스 키 구조
    |--------------------------------------------------------------------------
    */
    'keys' => [
        'coupon' => 'coupon:{id}',
        'user_coupons' => 'user_coupons:{user_id}',
        'promotion_coupons' => 'promotion_coupons:{promotion_id}',
        'category_coupons' => 'category_coupons:{category_id}',
        'active_coupons' => 'active_coupons',
        'expiring_coupons' => 'expiring_coupons',
        'coupon_rules' => 'coupon_rules:{coupon_id}',
        'user_targeting' => 'user_targeting:{targeting_id}',
        'event_log' => 'event_log:{event_id}',
    ],

    /*
    |--------------------------------------------------------------------------
    | 모니터링 설정
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'enabled' => env('COUPON_MONITORING_ENABLED', true),
        'metrics_ttl' => env('COUPON_METRICS_TTL', 3600),
        'error_threshold' => env('COUPON_ERROR_THRESHOLD', 10),
        'alert_email' => env('COUPON_ALERT_EMAIL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 성능 설정
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'max_processing_time' => env('COUPON_MAX_PROCESSING_TIME', 30), // 초
        'concurrent_workers' => env('COUPON_CONCURRENT_WORKERS', 4),
        'memory_limit' => env('COUPON_MEMORY_LIMIT', '512M'),
    ],
];
