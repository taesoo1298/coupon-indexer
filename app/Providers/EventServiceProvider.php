<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\CouponIssued;
use App\Events\CouponUsed;
use App\Events\CouponExpired;
use App\Events\CouponRevoked;
use App\Events\PromotionCreated;
use App\Events\PromotionUpdated;
use App\Events\PromotionActivated;
use App\Events\PromotionDeactivated;
use App\Events\UserLevelChanged;
use App\Events\UserProfileUpdated;
use App\Listeners\CouponEventListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        CouponIssued::class => [
            'App\Listeners\CouponEventListener@handleCouponIssued',
        ],
        CouponUsed::class => [
            'App\Listeners\CouponEventListener@handleCouponUsed',
        ],
        CouponExpired::class => [
            'App\Listeners\CouponEventListener@handleCouponExpired',
        ],
        CouponRevoked::class => [
            'App\Listeners\CouponEventListener@handleCouponRevoked',
        ],
        PromotionCreated::class => [
            'App\Listeners\CouponEventListener@handlePromotionCreated',
        ],
        PromotionUpdated::class => [
            'App\Listeners\CouponEventListener@handlePromotionUpdated',
        ],
        PromotionActivated::class => [
            'App\Listeners\CouponEventListener@handlePromotionActivated',
        ],
        PromotionDeactivated::class => [
            'App\Listeners\CouponEventListener@handlePromotionDeactivated',
        ],
        UserLevelChanged::class => [
            'App\Listeners\CouponEventListener@handleUserLevelChanged',
        ],
        UserProfileUpdated::class => [
            'App\Listeners\CouponEventListener@handleUserProfileUpdated',
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
