<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\ExpiredCouponCleanupJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Simple coupon cleanup command
Artisan::command('coupon:cleanup-expired', function () {
    $this->info('Starting expired coupon cleanup...');
    ExpiredCouponCleanupJob::dispatch();
    $this->info('Cleanup job has been queued');
})->purpose('Queue expired coupon cleanup job');
