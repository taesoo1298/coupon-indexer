<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserLevelChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public ?int $previousLevelId,
        public ?int $currentLevelId
    ) {}
}
