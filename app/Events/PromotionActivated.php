<?php

namespace App\Events;

use App\Models\Promotion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PromotionActivated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Promotion $promotion
    ) {}
}
