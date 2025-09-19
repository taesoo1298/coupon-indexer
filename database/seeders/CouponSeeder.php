<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\User;
use App\Models\Promotion;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $activePromotions = Promotion::where('is_active', true)->get();

        if ($users->isEmpty() || $activePromotions->isEmpty()) {
            $this->command->warn('No users or active promotions found. Run UserSeeder and PromotionSeeder first.');
            return;
        }

        // Create active coupons for test users
        $testUsers = $users->whereIn('email', ['admin@example.com', 'user@example.com', 'vip@example.com']);

        foreach ($testUsers as $user) {
            // Give each test user 3-5 active coupons from different promotions
            $userPromotions = $activePromotions->random(fake()->numberBetween(3, 5));

            foreach ($userPromotions as $promotion) {
                Coupon::factory()->active()->create([
                    'promotion_id' => $promotion->id,
                    'user_id' => $user->id,
                    'metadata' => [
                        'source' => 'seeder',
                        'campaign_id' => 'test_campaign_' . $promotion->id,
                        'notes' => 'Test coupon for ' . $user->name,
                    ],
                ]);
            }
        }

        // Create active coupons for regular users
        foreach ($users->whereNotIn('email', ['admin@example.com', 'user@example.com', 'vip@example.com']) as $user) {
            $couponCount = fake()->numberBetween(0, 3); // Some users may have no coupons

            if ($couponCount > 0) {
                $userPromotions = $activePromotions->random($couponCount);

                foreach ($userPromotions as $promotion) {
                    Coupon::factory()->active()->create([
                        'promotion_id' => $promotion->id,
                        'user_id' => $user->id,
                    ]);
                }
            }
        }

        // Create some used coupons for testing
        $usedCouponCount = fake()->numberBetween(20, 40);
        for ($i = 0; $i < $usedCouponCount; $i++) {
            Coupon::factory()->used()->create([
                'promotion_id' => $activePromotions->random()->id,
                'user_id' => $users->random()->id,
                'metadata' => [
                    'source' => fake()->randomElement(['web', 'mobile', 'email']),
                    'order_id' => fake()->randomNumber(8),
                ],
            ]);
        }

        // Create some expired coupons for testing
        $expiredCouponCount = fake()->numberBetween(10, 20);
        for ($i = 0; $i < $expiredCouponCount; $i++) {
            Coupon::factory()->expired()->create([
                'promotion_id' => $activePromotions->random()->id,
                'user_id' => $users->random()->id,
            ]);
        }

        // Create some revoked coupons for testing
        $revokedCouponCount = fake()->numberBetween(5, 10);
        for ($i = 0; $i < $revokedCouponCount; $i++) {
            Coupon::factory()->revoked()->create([
                'promotion_id' => $activePromotions->random()->id,
                'user_id' => $users->random()->id,
                'metadata' => [
                    'revoke_reason' => fake()->randomElement([
                        'fraud_detected',
                        'policy_violation',
                        'user_request'
                    ]),
                ],
            ]);
        }

        // Create some expiring soon coupons for testing
        $expiringSoonCount = fake()->numberBetween(5, 15);
        for ($i = 0; $i < $expiringSoonCount; $i++) {
            Coupon::factory()->expiringSoon()->create([
                'promotion_id' => $activePromotions->random()->id,
                'user_id' => $users->random()->id,
                'metadata' => [
                    'source' => 'seeder',
                    'expiring_soon' => true,
                ],
            ]);
        }

        $totalCoupons = Coupon::count();
        $this->command->info("Coupons seeded successfully. Total: {$totalCoupons}");

        // Show distribution
        $statusCounts = Coupon::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        foreach ($statusCounts as $status => $count) {
            $this->command->info("  {$status}: {$count}");
        }
    }
}
