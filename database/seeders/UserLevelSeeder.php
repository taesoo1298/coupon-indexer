<?php

namespace Database\Seeders;

use App\Models\UserLevel;
use Illuminate\Database\Seeder;

class UserLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create standard user levels
        $levels = [
            [
                'name' => 'Bronze Level',
                'code' => 'bronze',
                'min_points' => 0,
                'min_purchase_amount' => 0,
                'benefits' => [
                    'discount_rate' => 0.0,
                    'free_shipping' => false,
                    'priority_support' => false,
                    'exclusive_deals' => false,
                    'welcome_bonus' => 1000,
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Silver Level',
                'code' => 'silver',
                'min_points' => 1000,
                'min_purchase_amount' => 100000,
                'benefits' => [
                    'discount_rate' => 2.0,
                    'free_shipping' => true,
                    'priority_support' => false,
                    'exclusive_deals' => false,
                    'birthday_coupon' => 5000,
                    'monthly_points' => 500,
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Gold Level',
                'code' => 'gold',
                'min_points' => 5000,
                'min_purchase_amount' => 500000,
                'benefits' => [
                    'discount_rate' => 5.0,
                    'free_shipping' => true,
                    'priority_support' => true,
                    'exclusive_deals' => false,
                    'birthday_coupon' => 10000,
                    'monthly_points' => 1000,
                    'early_access' => true,
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Platinum Level',
                'code' => 'platinum',
                'min_points' => 10000,
                'min_purchase_amount' => 1000000,
                'benefits' => [
                    'discount_rate' => 10.0,
                    'free_shipping' => true,
                    'priority_support' => true,
                    'exclusive_deals' => true,
                    'birthday_coupon' => 20000,
                    'monthly_points' => 2000,
                    'early_access' => true,
                    'concierge_service' => true,
                    'annual_gift' => true,
                ],
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($levels as $level) {
            UserLevel::updateOrCreate(
                ['code' => $level['code']],
                $level
            );
        }

        $this->command->info('User levels seeded successfully.');
    }
}
