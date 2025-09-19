<?php

namespace App\Console\Commands;

use App\Services\CouponMonitoringService;
use Illuminate\Console\Command;

class CouponSystemMonitor extends Command
{
    protected $signature = 'coupon:monitor
                           {action=health : Action to perform (health, retry-failed, consistency-check, maintenance)}
                           {--format=table : Output format (table, json)}
                           {--limit=100 : Limit for retry-failed action}';

    protected $description = 'Monitor and maintain the coupon indexer system';

    public function __construct(
        private CouponMonitoringService $monitoringService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $action = $this->argument('action');
        $format = $this->option('format');

        try {
            switch ($action) {
                case 'health':
                    return $this->showHealthStatus($format);

                case 'retry-failed':
                    return $this->retryFailedEvents();

                case 'consistency-check':
                    return $this->runConsistencyCheck($format);

                case 'maintenance':
                    return $this->runMaintenance();

                default:
                    $this->error("Unknown action: {$action}");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("Command failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * 시스템 상태 표시
     */
    private function showHealthStatus(string $format): int
    {
        $this->info('Checking system health...');

        $health = $this->monitoringService->getSystemHealthStatus();

        if ($format === 'json') {
            $this->line(json_encode($health, JSON_PRETTY_PRINT));
        } else {
            $this->displayHealthTable($health);
        }

        return $health['overall_status'] === 'healthy' ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * 실패한 이벤트 재시도
     */
    private function retryFailedEvents(): int
    {
        $limit = (int)$this->option('limit');

        $this->info("Retrying failed events (limit: {$limit})...");

        $results = $this->monitoringService->retryFailedEvents($limit);

        $this->table(['Metric', 'Count'], [
            ['Total Found', $results['total_found']],
            ['Retried', $results['retried']],
            ['Skipped', $results['skipped']],
            ['Errors', count($results['errors'])],
        ]);

        if (!empty($results['errors'])) {
            $this->warn('Some events could not be retried:');
            foreach ($results['errors'] as $error) {
                $this->line("  Event {$error['event_id']}: {$error['error']}");
            }
        }

        $this->info("Retry operation completed");
        return Command::SUCCESS;
    }

    /**
     * 일관성 검사 실행
     */
    private function runConsistencyCheck(string $format): int
    {
        $this->info('Running consistency check...');

        $results = $this->monitoringService->performConsistencyCheck();

        if ($format === 'json') {
            $this->line(json_encode($results, JSON_PRETTY_PRINT));
        } else {
            $this->displayConsistencyResults($results);
        }

        return Command::SUCCESS;
    }

    /**
     * 유지보수 작업 실행
     */
    private function runMaintenance(): int
    {
        $this->info('Running maintenance cleanup...');

        $results = $this->monitoringService->performMaintenanceCleanup();

        $this->table(['Operation', 'Result'], [
            ['Old events cleaned', $results['old_events_cleaned']],
            ['Expired indexes cleaned', $results['expired_indexes_cleaned']],
            ['Orphaned data cleaned', $results['orphaned_data_cleaned']],
            ['Cache cleared', $results['cache_cleared'] ? 'Yes' : 'No'],
        ]);

        if (isset($results['error'])) {
            $this->warn("Some operations failed: " . $results['error']);
        }

        $this->info("Maintenance completed");
        return Command::SUCCESS;
    }

    /**
     * 상태 정보를 테이블로 표시
     */
    private function displayHealthTable(array $health): void
    {
        // 전체 상태
        $statusColor = match($health['overall_status']) {
            'healthy' => 'info',
            'warning' => 'comment',
            'critical' => 'error',
            default => 'line'
        };

        $this->newLine();
        $this->$statusColor("Overall Status: " . strtoupper($health['overall_status']));
        $this->newLine();

        // 각 체크 결과
        $checkRows = [];
        foreach ($health['checks'] as $check => $result) {
            $checkRows[] = [
                ucwords(str_replace('_', ' ', $check)),
                ucfirst($result['status']),
                $result['error'] ?? ($result['response_time_ms'] ?? '') .
                ($result['queue_length'] ?? '') .
                ($result['failure_rate_percent'] ?? ''),
            ];
        }

        $this->table(['Check', 'Status', 'Details'], $checkRows);

        // 메트릭
        if (!empty($health['metrics'])) {
            $this->newLine();
            $this->info('System Metrics:');

            $metricRows = [];
            foreach ($health['metrics'] as $metric => $value) {
                $metricRows[] = [
                    ucwords(str_replace('_', ' ', $metric)),
                    number_format($value)
                ];
            }

            $this->table(['Metric', 'Value'], $metricRows);
        }

        // 알림
        if (!empty($health['alerts'])) {
            $this->newLine();
            $this->warn('Alerts:');

            foreach ($health['alerts'] as $alert) {
                $levelMethod = $alert['level'] === 'critical' ? 'error' : 'comment';
                $this->$levelMethod("• " . $alert['message']);
                $this->line("  Action: " . $alert['action_required']);
            }
        }
    }

    /**
     * 일관성 검사 결과 표시
     */
    private function displayConsistencyResults(array $results): void
    {
        $this->info('Consistency Check Results:');
        $this->newLine();

        // 데이터베이스 vs 인덱스
        $dbIndex = $results['check_results']['database_vs_index'];
        $this->table(['Check', 'Total Checked', 'Issues Found'], [
            [
                'Database vs Index',
                $dbIndex['total_checked'],
                $dbIndex['inconsistency_count']
            ]
        ]);

        if ($dbIndex['inconsistency_count'] > 0) {
            $this->warn('Inconsistencies found:');
            foreach (array_slice($dbIndex['inconsistencies'], 0, 10) as $issue) {
                $this->line("  • {$issue}");
            }
            if ($dbIndex['inconsistency_count'] > 10) {
                $this->line("  ... and " . ($dbIndex['inconsistency_count'] - 10) . " more");
            }
        }

        // 자동 수정
        if (!empty($results['auto_fixes'])) {
            $this->newLine();
            $this->info('Auto-fixes applied:');
            foreach ($results['auto_fixes'] as $fix) {
                $this->line("  ✓ {$fix}");
            }
        }
    }
}
