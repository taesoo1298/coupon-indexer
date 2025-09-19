<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RedisPubSubService;

class PubSubDiagnostic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pubsub:diagnostic
                            {--test-publish : Test publishing a message}
                            {--info : Show channel information}
                            {--reconnect : Force reconnection}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose Redis Pub/Sub service issues';

    /**
     * Execute the console command.
     */
    public function handle(RedisPubSubService $pubsubService)
    {
        $this->info('🔍 Redis Pub/Sub 진단 시작...');
        $this->newLine();

        // 1. 연결 상태 확인
        $this->checkConnection($pubsubService);

        // 2. 옵션별 실행
        if ($this->option('test-publish')) {
            $this->testPublishing($pubsubService);
        }

        if ($this->option('info')) {
            $this->showChannelInfo($pubsubService);
        }

        if ($this->option('reconnect')) {
            $this->forceReconnect($pubsubService);
        }

        // 기본 실행: 전체 진단
        if (!$this->option('test-publish') && !$this->option('info') && !$this->option('reconnect')) {
            $this->fullDiagnostic($pubsubService);
        }

        $this->newLine();
        $this->info('✅ 진단 완료');
    }

    private function checkConnection(RedisPubSubService $pubsubService): void
    {
        $this->info('📡 연결 상태 확인');

        $stats = $pubsubService->getConnectionStats();
        $isConnected = $pubsubService->isConnected();

        if ($isConnected) {
            $this->line("  ✅ Redis 연결: <fg=green>성공</>");
        } else {
            $this->line("  ❌ Redis 연결: <fg=red>실패</>");
        }

        $this->line("  📊 재연결 시도: {$stats['reconnect_attempts']}/{$stats['max_attempts']}");
        $this->line("  📻 채널: {$stats['channel']}");
        $this->newLine();
    }

    private function testPublishing(RedisPubSubService $pubsubService): void
    {
        $this->info('📤 메시지 발행 테스트');

        $result = $pubsubService->testPublish();

        if ($result['success']) {
            $this->line("  ✅ 메시지 발행: <fg=green>성공</>");
            $this->line("  🆔 테스트 ID: {$result['test_data']['test_id']}");
        } else {
            $this->line("  ❌ 메시지 발행: <fg=red>실패</>");
            if ($result['error']) {
                $this->line("  🚫 오류: {$result['error']}");
            }
        }
        $this->newLine();
    }

    private function showChannelInfo(RedisPubSubService $pubsubService): void
    {
        $this->info('📊 채널 정보');

        $info = $pubsubService->getChannelInfo();

        $this->line("  📻 채널: {$info['channel']}");
        $this->line("  📡 상태: {$info['status']}");
        $this->line("  👥 구독자: {$info['subscribers']}");

        if (isset($info['redis_version'])) {
            $this->line("  🏷️  Redis 버전: {$info['redis_version']}");
        }

        if (isset($info['memory_usage'])) {
            $this->line("  💾 메모리 사용량: {$info['memory_usage']}");
        }

        if (isset($info['error'])) {
            $this->line("  🚫 오류: <fg=red>{$info['error']}</>");
        }
        $this->newLine();
    }

    private function forceReconnect(RedisPubSubService $pubsubService): void
    {
        $this->info('🔄 강제 재연결 시도');

        $reconnected = $pubsubService->forceReconnect();

        if ($reconnected) {
            $this->line("  ✅ 재연결: <fg=green>성공</>");
        } else {
            $this->line("  ❌ 재연결: <fg=red>실패</>");
        }
        $this->newLine();
    }

    private function fullDiagnostic(RedisPubSubService $pubsubService): void
    {
        $this->showChannelInfo($pubsubService);
        $this->testPublishing($pubsubService);

        // 추가 진단
        $this->info('🔧 추가 진단');

        try {
            // Redis 설정 확인
            $redisConfig = config('database.redis.pubsub');
            $this->line("  🏠 Redis 호스트: {$redisConfig['host']}:{$redisConfig['port']}");
            $this->line("  🗄️  데이터베이스: {$redisConfig['database']}");

        } catch (\Exception $e) {
            $this->line("  🚫 설정 오류: {$e->getMessage()}");
        }

        // 환경 변수 확인
        $this->line("  🌍 Redis 호스트 (env): " . (env('REDIS_HOST') ?: '설정되지 않음'));
        $this->line("  🔌 Redis 포트 (env): " . (env('REDIS_PORT') ?: '설정되지 않음'));

        $this->newLine();
    }
}
