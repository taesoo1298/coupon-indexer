<?php

namespace App\Console\Commands;

use App\Jobs\FullSyncCouponIndexJob;
use App\Models\CouponIndexStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class CouponIndexSync extends Command
{
    protected $signature = 'coupon:sync-index
                           {type? : Sync type (coupons, promotions, users, or all)}
                           {--ids= : Comma-separated list of specific entity IDs to sync}
                           {--force : Force sync even if recently synced}
                           {--async : Run sync asynchronously via queue}';

    protected $description = 'Synchronize coupon index with database';

    public function handle(): int
    {
        $syncType = $this->argument('type') ?? 'all';
        $entityIds = $this->option('ids') ? explode(',', $this->option('ids')) : null;
        $force = $this->option('force');
        $async = $this->option('async');

        // 유효한 sync 타입 체크
        $validTypes = ['coupons', 'promotions', 'users', 'all'];
        if (!in_array($syncType, $validTypes)) {
            $this->error("Invalid sync type. Must be one of: " . implode(', ', $validTypes));
            return Command::FAILURE;
        }

        $this->info("Starting coupon index synchronization...");
        $this->info("Sync type: {$syncType}");

        if ($entityIds) {
            $this->info("Entity IDs: " . implode(', ', $entityIds));
        }

        try {
            // 최근 동기화 체크 (force 옵션이 없는 경우)
            if (!$force && $this->wasRecentlySynced()) {
                if (!$this->confirm('Index was recently synchronized. Continue anyway?')) {
                    $this->info('Sync cancelled');
                    return Command::SUCCESS;
                }
            }

            if ($async) {
                $this->runAsyncSync($syncType, $entityIds);
            } else {
                $this->runSyncSync($syncType, $entityIds);
            }

            $this->info('Coupon index synchronization completed successfully');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Synchronization failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * 비동기 동기화 실행
     */
    private function runAsyncSync(?string $syncType, ?array $entityIds): void
    {
        $jobSyncType = $syncType === 'all' ? null : $syncType;

        FullSyncCouponIndexJob::dispatch($jobSyncType, $entityIds);

        $this->info('Synchronization job has been queued');
        $this->line('You can monitor the progress in the logs');
    }

    /**
     * 동기 동기화 실행 (현재 프로세스에서)
     */
    private function runSyncSync(?string $syncType, ?array $entityIds): void
    {
        $jobSyncType = $syncType === 'all' ? null : $syncType;

        $job = new FullSyncCouponIndexJob($jobSyncType, $entityIds);

        $this->line('Running synchronization...');

        // Job을 직접 실행
        $indexService = app(\App\Services\CouponIndexService::class);
        $eventLogger = app(\App\Services\CouponEventLogger::class);

        $job->handle($indexService, $eventLogger);

        $this->info('Synchronization completed');
    }

    /**
     * 최근 동기화 여부 확인
     */
    private function wasRecentlySynced(): bool
    {
        $syncInterval = config('coupon-indexer.sync.interval', 3600); // 기본 1시간
        $lastSyncTime = Redis::connection('coupon_index')->get('last_full_sync');

        if (!$lastSyncTime) {
            return false;
        }

        $lastSync = \Carbon\Carbon::createFromTimestamp($lastSyncTime);
        $now = now();

        return $now->diffInSeconds($lastSync) < $syncInterval;
    }
}
