<?php

namespace App\Events;

use App\Models\Coupon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CouponIssued
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Coupon $coupon
    ) {}
}
