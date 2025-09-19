<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserLevelSeeder::class,
            UserSeeder::class,
            PromotionSeeder::class,
            CouponSeeder::class,
            CouponEventSeeder::class,
        ]);
    }
}
