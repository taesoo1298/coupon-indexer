<?php

namespace Database\Factories;

use App\Models\CouponEvent;
use App\Models\User;
use App\Models\Coupon;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CouponEvent>
 */
class CouponEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventType = fake()->randomElement([
            'coupon_issued',
            'coupon_used',
            'coupon_expired',
            'coupon_revoked',
            'promotion_created',
            'promotion_updated',
            'promotion_activated',
            'promotion_deactivated',
            'user_level_changed',
            'user_profile_updated'
        ]);

        $entityInfo = $this->getEntityInfo($eventType);

        return [
            'event_type' => $eventType,
            'entity_type' => $entityInfo['type'],
            'entity_id' => $entityInfo['id'],
            'user_id' => fake()->optional(0.8)->numberBetween(1, 50),
            'event_data' => $this->generateEventData($eventType),
            'previous_state' => fake()->optional(0.3)->randomElements(['status' => 'active'], 1),
            'current_state' => fake()->optional(0.3)->randomElements(['status' => 'used'], 1),
            'occurred_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'is_processed' => fake()->boolean(70),
            'processed_at' => fake()->optional(0.7)->dateTimeBetween('-1 month', 'now'),
            'retry_count' => fake()->numberBetween(0, 3),
            'processing_errors' => fake()->optional(0.1)->randomElements(['error' => 'timeout'], 1),
        ];
    }

    /**
     * Get entity information based on event type
     */
    private function getEntityInfo(string $eventType): array
    {
        return match($eventType) {
            'coupon_issued', 'coupon_used', 'coupon_expired', 'coupon_revoked' => [
                'type' => 'coupon',
                'id' => fake()->numberBetween(1, 100),
            ],
            'promotion_created', 'promotion_updated', 'promotion_activated', 'promotion_deactivated' => [
                'type' => 'promotion',
                'id' => fake()->numberBetween(1, 20),
            ],
            'user_level_changed', 'user_profile_updated' => [
                'type' => 'user',
                'id' => fake()->numberBetween(1, 50),
            ],
            default => [
                'type' => 'unknown',
                'id' => fake()->numberBetween(1, 100),
            ],
        };
    }

    /**
     * Generate event-specific data
     */
    private function generateEventData(string $eventType): array
    {
        return match($eventType) {
            'coupon_issued' => [
                'source' => fake()->randomElement(['web', 'mobile', 'email', 'admin']),
                'campaign_id' => fake()->optional()->randomNumber(5),
                'issued_by' => fake()->optional()->name(),
            ],

            'coupon_used' => [
                'order_id' => fake()->randomNumber(8),
                'order_amount' => fake()->randomFloat(2, 1000, 500000),
                'discount_amount' => fake()->randomFloat(2, 500, 50000),
                'used_at' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d H:i:s'),
            ],

            'coupon_expired' => [
                'expired_at' => fake()->dateTimeBetween('-1 week', 'now')->format('Y-m-d H:i:s'),
                'was_used' => fake()->boolean(30),
            ],

            'coupon_revoked' => [
                'revoked_by' => fake()->name(),
                'reason' => fake()->randomElement([
                    'fraud_detected',
                    'policy_violation',
                    'user_request',
                    'technical_issue'
                ]),
                'revoked_at' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d H:i:s'),
            ],

            'promotion_created' => [
                'created_by' => fake()->name(),
                'promotion_type' => fake()->randomElement(['discount', 'cashback', 'gift']),
                'target_audience' => fake()->randomElement(['all', 'vip', 'new_users', 'high_spenders']),
            ],

            'promotion_updated' => [
                'updated_by' => fake()->name(),
                'updated_fields' => fake()->randomElements([
                    'rules', 'discount_value', 'end_date', 'usage_limit'
                ], fake()->numberBetween(1, 3)),
                'previous_values' => [
                    'discount_value' => fake()->randomFloat(2, 5, 30),
                    'usage_limit' => fake()->numberBetween(100, 1000),
                ],
            ],

            'promotion_activated' => [
                'activated_by' => fake()->name(),
                'activation_reason' => fake()->randomElement([
                    'scheduled', 'manual', 'event_triggered'
                ]),
            ],

            'promotion_deactivated' => [
                'deactivated_by' => fake()->name(),
                'deactivation_reason' => fake()->randomElement([
                    'expired', 'manual', 'usage_limit_reached', 'suspended'
                ]),
            ],

            'user_level_changed' => [
                'previous_level' => fake()->randomElement(['bronze', 'silver', 'gold']),
                'new_level' => fake()->randomElement(['silver', 'gold', 'platinum']),
                'trigger_type' => fake()->randomElement([
                    'purchase_amount', 'purchase_count', 'manual_upgrade'
                ]),
                'qualifying_amount' => fake()->randomFloat(2, 100000, 1000000),
            ],

            'user_profile_updated' => [
                'updated_fields' => fake()->randomElements([
                    'email', 'phone', 'address', 'preferences'
                ], fake()->numberBetween(1, 2)),
                'update_source' => fake()->randomElement(['web', 'mobile', 'api', 'admin']),
            ],

            default => [],
        };
    }

    /**
     * Create a pending event
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_processed' => false,
            'processed_at' => null,
            'processing_errors' => null,
        ]);
    }

    /**
     * Create a completed event
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_processed' => true,
            'processed_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'processing_errors' => null,
        ]);
    }

    /**
     * Create a failed event
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_processed' => false,
            'processed_at' => null,
            'processing_errors' => [
                'error' => fake()->randomElement([
                    'Redis connection timeout',
                    'Invalid event data format',
                    'Entity not found',
                    'User validation failed',
                    'Database connection error'
                ]),
                'occurred_at' => now()->toISOString(),
            ],
            'retry_count' => fake()->numberBetween(1, 5),
        ]);
    }

    /**
     * Create event for specific type
     */
    public function ofType(string $eventType): static
    {
        return $this->state(function (array $attributes) use ($eventType) {
            $entityInfo = $this->getEntityInfo($eventType);

            return [
                'event_type' => $eventType,
                'entity_type' => $entityInfo['type'],
                'entity_id' => $entityInfo['id'],
                'event_data' => $this->generateEventData($eventType),
            ];
        });
    }

    /**
     * Create event for specific user
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create event for specific entity
     */
    public function forEntity(string $entityType, int $entityId): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }

    /**
     * Create event with retry attempts
     */
    public function withRetries(int $retryCount): static
    {
        return $this->state(fn (array $attributes) => [
            'retry_count' => $retryCount,
            'is_processed' => false,
        ]);
    }
}
