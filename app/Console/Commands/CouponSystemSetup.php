<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Artisan;

class CouponSystemSetup extends Command
{
    protected $signature = 'coupon:setup
                           {--force : Force setup even if system is already configured}
                           {--reset : Reset existing configuration}';

    protected $description = 'Setup and initialize the coupon indexer system';

    public function handle(): int
    {
        $this->info('🚀 Setting up Coupon Indexer System...');

        if ($this->option('reset')) {
            $this->resetSystem();
        }

        $setupSteps = [
            'checkEnvironment',
            'checkDatabase',
            'checkRedis',
            'runMigrations',
            'createRedisIndexes',
            'startQueueWorkers',
            'validateSetup',
        ];

        foreach ($setupSteps as $step) {
            if (!$this->$step()) {
                $this->error("❌ Setup failed at step: {$step}");
                return Command::FAILURE;
            }
        }

        $this->info('✅ Coupon Indexer System setup completed successfully!');
        $this->showNextSteps();

        return Command::SUCCESS;
    }

    /**
     * 환경 설정 확인
     */
    private function checkEnvironment(): bool
    {
        $this->info('📋 Checking environment configuration...');

        $requiredEnvVars = [
            'REDIS_HOST',
            'REDIS_PORT',
            'DB_CONNECTION',
            'QUEUE_CONNECTION',
        ];

        $missing = [];
        foreach ($requiredEnvVars as $var) {
            if (!env($var)) {
                $missing[] = $var;
            }
        }

        if (!empty($missing)) {
            $this->error('Missing required environment variables:');
            foreach ($missing as $var) {
                $this->line("  - {$var}");
            }
            return false;
        }

        $this->line('✓ Environment configuration OK');
        return true;
    }

    /**
     * 데이터베이스 연결 확인
     */
    private function checkDatabase(): bool
    {
        $this->info('🗄️ Checking database connection...');

        try {
            DB::connection()->getPdo();
            $this->line('✓ Database connection OK');
            return true;
        } catch (\Exception $e) {
            $this->error("Database connection failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Redis 연결 확인
     */
    private function checkRedis(): bool
    {
        $this->info('📦 Checking Redis connections...');

        $connections = ['default', 'cache', 'coupon_index', 'pubsub'];

        foreach ($connections as $connection) {
            try {
                Redis::connection($connection)->ping();
                $this->line("✓ Redis connection '{$connection}' OK");
            } catch (\Exception $e) {
                $this->error("Redis connection '{$connection}' failed: " . $e->getMessage());
                return false;
            }
        }

        return true;
    }

    /**
     * 마이그레이션 실행
     */
    private function runMigrations(): bool
    {
        $this->info('🔧 Running database migrations...');

        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->line('✓ Database migrations completed');
            return true;
        } catch (\Exception $e) {
            $this->error("Migration failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Redis 인덱스 구조 생성
     */
    private function createRedisIndexes(): bool
    {
        $this->info('📂 Creating Redis index structures...');

        try {
            $redis = Redis::connection('coupon_index');

            // 기본 인덱스 키 구조 생성
            $indexKeys = [
                'coupon:user_indexes',
                'coupon:promotion_indexes',
                'coupon:user_level_indexes',
                'coupon:applicable_cache',
                'coupon:rule_cache',
                'coupon:sync_status',
            ];

            foreach ($indexKeys as $key) {
                if (!$redis->exists($key)) {
                    $redis->hset($key, 'initialized', time());
                }
            }

            $this->line('✓ Redis indexes initialized');
            return true;
        } catch (\Exception $e) {
            $this->error("Redis index creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 큐 워커 상태 확인
     */
    private function startQueueWorkers(): bool
    {
        $this->info('🔄 Checking queue workers...');

        try {
            // 큐 연결 테스트
            Queue::size('coupon_events');
            Queue::size('coupon_sync');
            Queue::size('coupon_cleanup');

            $this->line('✓ Queue system ready');

            if ($this->confirm('Would you like to start queue workers now?', false)) {
                $this->info('Starting queue workers...');
                $this->line('Run these commands in separate terminals:');
                $this->line('php artisan queue:work --queue=coupon_events');
                $this->line('php artisan queue:work --queue=coupon_sync');
                $this->line('php artisan queue:work --queue=coupon_cleanup');
            }

            return true;
        } catch (\Exception $e) {
            $this->error("Queue system check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 설정 검증
     */
    private function validateSetup(): bool
    {
        $this->info('✅ Validating system setup...');

        try {
            // 이벤트 구독자 시작
            Artisan::call('coupon:subscribe-events');

            $this->line('✓ Event subscriber started');

            // 모니터링 상태 확인
            Artisan::call('coupon:monitor', ['action' => 'health']);

            return true;
        } catch (\Exception $e) {
            $this->error("Setup validation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 시스템 리셋
     */
    private function resetSystem(): void
    {
        if (!$this->confirm('This will reset all coupon indexer data. Continue?', false)) {
            $this->info('Reset cancelled');
            return;
        }

        $this->warn('🔄 Resetting system...');

        try {
            // Redis 데이터 삭제
            $redis = Redis::connection('coupon_index');
            $keys = $redis->keys('coupon:*');
            if (!empty($keys)) {
                $redis->del(...$keys);
                $this->line('✓ Redis indexes cleared');
            }

            // 큐 클리어
            Artisan::call('queue:clear');
            $this->line('✓ Queues cleared');

        } catch (\Exception $e) {
            $this->error("Reset failed: " . $e->getMessage());
        }
    }

    /**
     * 다음 단계 안내
     */
    private function showNextSteps(): void
    {
        $this->info('');
        $this->info('🎉 Next Steps:');
        $this->info('');
        $this->line('1. Start queue workers:');
        $this->line('   php artisan queue:work --queue=coupon_events');
        $this->line('   php artisan queue:work --queue=coupon_sync');
        $this->line('   php artisan queue:work --queue=coupon_cleanup');
        $this->line('');
        $this->line('2. Monitor system health:');
        $this->line('   php artisan coupon:monitor health');
        $this->line('');
        $this->line('3. Subscribe to events:');
        $this->line('   php artisan coupon:subscribe-events');
        $this->line('');
        $this->line('4. Test the API endpoints:');
        $this->line('   GET /api/coupons/user/{userId}/applicable');
        $this->line('   GET /api/coupons/user/{userId}/optimal');
        $this->line('');
        $this->line('📖 For more information, check the documentation.');
    }
}
