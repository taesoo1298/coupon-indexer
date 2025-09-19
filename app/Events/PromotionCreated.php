<?php

namespace App\Events;

use App\Models\Promotion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PromotionCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Promotion $promotion
    ) {}
}
