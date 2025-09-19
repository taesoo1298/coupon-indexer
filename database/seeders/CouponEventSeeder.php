<?php

namespace Database\Seeders;

use App\Models\CouponEvent;
use App\Models\User;
use App\Models\Coupon;
use App\Models\Promotion;
use Illuminate\Database\Seeder;

class CouponEventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $coupons = Coupon::all();
        $promotions = Promotion::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Run UserSeeder first.');
            return;
        }

        // Create coupon issued events (one for each coupon)
        foreach ($coupons->take(20) as $coupon) {
            CouponEvent::factory()->completed()->create([
                'event_type' => 'coupon_issued',
                'entity_type' => 'coupon',
                'entity_id' => $coupon->id,
                'user_id' => $coupon->user_id,
                'event_data' => [
                    'source' => fake()->randomElement(['web', 'mobile', 'admin']),
                    'campaign_id' => 'campaign_' . $coupon->promotion_id,
                    'issued_by' => fake()->optional()->name(),
                    'promotion_id' => $coupon->promotion_id,
                ],
            ]);
        }

        // Create coupon used events for used coupons
        $usedCoupons = $coupons->where('status', 'used');
        foreach ($usedCoupons->take(15) as $coupon) {
            CouponEvent::factory()->completed()->create([
                'event_type' => 'coupon_used',
                'entity_type' => 'coupon',
                'entity_id' => $coupon->id,
                'user_id' => $coupon->user_id,
                'event_data' => [
                    'order_id' => fake()->randomNumber(8),
                    'order_amount' => fake()->randomFloat(2, 10000, 500000),
                    'discount_amount' => fake()->randomFloat(2, 1000, 50000),
                    'used_at' => $coupon->used_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
                    'promotion_id' => $coupon->promotion_id,
                ],
            ]);
        }

        // Create promotion events
        foreach ($promotions->take(10) as $promotion) {
            // Promotion created event
            CouponEvent::factory()->completed()->create([
                'event_type' => 'promotion_created',
                'entity_type' => 'promotion',
                'entity_id' => $promotion->id,
                'event_data' => [
                    'created_by' => fake()->name(),
                    'promotion_type' => fake()->randomElement(['discount', 'cashback', 'gift']),
                    'target_audience' => fake()->randomElement(['all', 'vip', 'new_users']),
                ],
            ]);

            // Sometimes add promotion activated event
            if (fake()->boolean(70)) {
                CouponEvent::factory()->completed()->create([
                    'event_type' => 'promotion_activated',
                    'entity_type' => 'promotion',
                    'entity_id' => $promotion->id,
                    'event_data' => [
                        'activated_by' => fake()->name(),
                        'activation_reason' => 'scheduled',
                    ],
                ]);
            }
        }

        // Create user level changed events
        foreach ($users->take(15) as $user) {
            if (fake()->boolean(40)) {
                $previousLevel = fake()->numberBetween(1, 3);
                $newLevel = fake()->numberBetween(2, 4);

                if ($previousLevel !== $newLevel) {
                    CouponEvent::factory()->completed()->create([
                        'event_type' => 'user_level_changed',
                        'entity_type' => 'user',
                        'entity_id' => $user->id,
                        'user_id' => $user->id,
                        'event_data' => [
                            'previous_level_id' => $previousLevel,
                            'new_level_id' => $newLevel,
                            'trigger_type' => fake()->randomElement(['purchase_amount', 'points']),
                            'qualifying_amount' => fake()->randomFloat(2, 100000, 1000000),
                        ],
                    ]);
                }
            }
        }

        // Create some pending events for testing
        for ($i = 0; $i < 5; $i++) {
            CouponEvent::factory()->pending()->create([
                'event_type' => 'coupon_issued',
                'entity_type' => 'coupon',
                'entity_id' => $coupons->random()->id,
                'user_id' => $users->random()->id,
            ]);
        }

        // Create some failed events for testing
        for ($i = 0; $i < 3; $i++) {
            $eventType = fake()->randomElement([
                'coupon_issued',
                'promotion_updated',
                'user_level_changed'
            ]);

            $entityInfo = match($eventType) {
                'coupon_issued' => ['type' => 'coupon', 'id' => $coupons->random()->id],
                'promotion_updated' => ['type' => 'promotion', 'id' => $promotions->random()->id],
                'user_level_changed' => ['type' => 'user', 'id' => $users->random()->id],
            };

            CouponEvent::factory()->failed()->create([
                'event_type' => $eventType,
                'entity_type' => $entityInfo['type'],
                'entity_id' => $entityInfo['id'],
                'user_id' => $users->random()->id,
            ]);
        }

        // Create some processing events for testing
        for ($i = 0; $i < 2; $i++) {
            CouponEvent::factory()->create([
                'event_type' => 'promotion_updated',
                'entity_type' => 'promotion',
                'entity_id' => $promotions->random()->id,
                'is_processed' => false,
                'processed_at' => null,
            ]);
        }

        $totalEvents = CouponEvent::count();
        $this->command->info("Coupon events seeded successfully. Total: {$totalEvents}");

        // Show distribution by processing status
        $statusCounts = CouponEvent::selectRaw('is_processed, count(*) as count')
            ->groupBy('is_processed')
            ->pluck('count', 'is_processed')
            ->toArray();

        $this->command->info('Event processing status:');
        foreach ($statusCounts as $processed => $count) {
            $status = $processed ? 'processed' : 'pending';
            $this->command->info("  {$status}: {$count}");
        }

        // Show distribution by event type
        $typeCounts = CouponEvent::selectRaw('event_type, count(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type')
            ->toArray();

        $this->command->info('Event type distribution:');
        foreach ($typeCounts as $type => $count) {
            $this->command->info("  {$type}: {$count}");
        }
    }
}
