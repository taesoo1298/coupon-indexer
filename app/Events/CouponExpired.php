<?php

namespace App\Events;

use App\Models\Coupon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CouponExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Coupon $coupon
    ) {}
}
