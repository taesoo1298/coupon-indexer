<?php

namespace App\Events;

use App\Models\Promotion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PromotionUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Promotion $promotion
    ) {}
}
