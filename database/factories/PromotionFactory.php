<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('now', '+1 month');
        $endDate = fake()->dateTimeBetween($startDate, '+3 months');

        return [
            'name' => fake()->catchPhrase() . ' 프로모션',
            'description' => fake()->sentence(10),
            'type' => fake()->randomElement(['percentage', 'fixed_amount', 'free_shipping']),
            'value' => fake()->randomFloat(2, 5, 50),
            'conditions' => $this->generateConditions(),
            'targeting_rules' => $this->generateTargetingRules(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_active' => fake()->boolean(80), // 80% chance of being active
            'max_usage_count' => fake()->randomElement([null, 100, 500, 1000, 5000]),
            'max_usage_per_user' => fake()->randomElement([null, 1, 2, 3, 5]),
            'current_usage_count' => 0,
            'priority' => fake()->numberBetween(1, 10),
            'metadata' => $this->generateMetadata(),
        ];
    }

    /**
     * Generate promotion conditions
     */
    private function generateConditions(): array
    {
        $conditions = [];

        // Minimum purchase amount
        if (fake()->boolean(80)) {
            $conditions['min_purchase_amount'] = fake()->randomElement([
                10000, 20000, 50000, 100000, 200000
            ]);
        }

        // Maximum purchase amount
        if (fake()->boolean(30)) {
            $conditions['max_purchase_amount'] = fake()->randomElement([
                500000, 1000000, 2000000
            ]);
        }

        // Category targeting
        if (fake()->boolean(60)) {
            $conditions['applicable_categories'] = fake()->randomElements([
                'electronics', 'books', 'clothing', 'home', 'sports',
                'beauty', 'toys', 'automotive', 'food', 'health'
            ], fake()->numberBetween(1, 4));
        }

        // Product targeting
        if (fake()->boolean(40)) {
            $conditions['applicable_products'] = fake()->randomElements(
                range(1, 100), // Product IDs from 1 to 100
                fake()->numberBetween(1, 10)
            );
        }

        // Time-based conditions
        if (fake()->boolean(30)) {
            $conditions['valid_days_of_week'] = fake()->randomElements(
                ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                fake()->numberBetween(1, 7)
            );
        }

        if (fake()->boolean(20)) {
            $conditions['valid_hours'] = [
                'start' => fake()->numberBetween(0, 12),
                'end' => fake()->numberBetween(13, 23)
            ];
        }

        // First purchase bonus
        if (fake()->boolean(15)) {
            $conditions['first_purchase_only'] = true;
        }

        return $conditions;
    }

    /**
     * Generate targeting rules
     */
    private function generateTargetingRules(): array
    {
        $rules = [];

        // User level targeting
        if (fake()->boolean(70)) {
            $rules['user_levels'] = fake()->randomElements([1, 2, 3, 4], fake()->numberBetween(1, 3));
        }

        // User points targeting
        if (fake()->boolean(40)) {
            $rules['min_user_points'] = fake()->randomElement([500, 1000, 2500, 5000]);
        }

        // VIP targeting
        if (fake()->boolean(25)) {
            $rules['vip_only'] = true;
        }

        // New user targeting
        if (fake()->boolean(20)) {
            $rules['new_users_only'] = true;
        }

        return $rules;
    }

    /**
     * Generate metadata
     */
    private function generateMetadata(): array
    {
        return [
            'campaign_id' => fake()->optional()->uuid(),
            'source' => fake()->randomElement(['web', 'mobile', 'email', 'admin']),
            'created_by' => fake()->name(),
        ];
    }

    /**
     * Indicate that the promotion should be active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'start_date' => now()->subDays(1),
            'end_date' => now()->addMonths(2),
        ]);
    }

    /**
     * Indicate that the promotion should be inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the promotion should be expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'start_date' => now()->subMonths(3),
            'end_date' => now()->subMonth(),
        ]);
    }

    /**
     * Indicate that the promotion should be for VIP users.
     */
    public function vipOnly(): static
    {
        return $this->state(function (array $attributes) {
            $targeting = $attributes['targeting_rules'] ?? [];
            $targeting['vip_only'] = true;
            $targeting['user_levels'] = [3, 4]; // Gold and Platinum

            return [
                'targeting_rules' => $targeting,
                'value' => fake()->randomFloat(2, 15, 50),
            ];
        });
    }

    /**
     * Indicate that the promotion should have high discount.
     */
    public function highDiscount(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'percentage',
            'value' => fake()->randomFloat(2, 30, 70),
        ]);
    }

    /**
     * Indicate that the promotion should target specific categories.
     */
    public function forCategories(array $categories): static
    {
        return $this->state(function (array $attributes) use ($categories) {
            $conditions = $attributes['conditions'] ?? [];
            $conditions['applicable_categories'] = $categories;

            return ['conditions' => $conditions];
        });
    }

    /**
     * Indicate that the promotion should have usage limits.
     */
    public function withUsageLimit(int $limit): static
    {
        return $this->state(fn (array $attributes) => [
            'max_usage_count' => $limit,
            'max_usage_per_user' => 1,
        ]);
    }
}
