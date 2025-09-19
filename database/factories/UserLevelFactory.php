<?php

namespace Database\Factories;

use App\Models\UserLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserLevel>
 */
class UserLevelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $level = fake()->randomElement(['bronze', 'silver', 'gold', 'platinum']);

        return [
            'name' => ucfirst($level) . ' Level',
            'code' => $level,
            'min_points' => $this->getMinPoints($level),
            'min_purchase_amount' => $this->getMinPurchaseAmount($level),
            'benefits' => $this->getBenefits($level),
            'is_active' => true,
            'sort_order' => $this->getSortOrder($level),
        ];
    }

    /**
     * Get minimum purchase amount for level
     */
    private function getMinPurchaseAmount(string $level): int
    {
        return match($level) {
            'bronze' => 0,
            'silver' => 100000,
            'gold' => 500000,
            'platinum' => 1000000,
            default => 0,
        };
    }

    /**
     * Get minimum points for level
     */
    private function getMinPoints(string $level): int
    {
        return match($level) {
            'bronze' => 0,
            'silver' => 1000,
            'gold' => 5000,
            'platinum' => 10000,
            default => 0,
        };
    }

    /**
     * Get benefits for level
     */
    private function getBenefits(string $level): array
    {
        $baseBenefits = [
            'discount_rate' => $this->getDiscountRate($level),
            'free_shipping' => $level !== 'bronze',
            'priority_support' => in_array($level, ['gold', 'platinum']),
            'exclusive_deals' => $level === 'platinum',
        ];

        // Add level-specific benefits
        $specificBenefits = match($level) {
            'bronze' => [
                'welcome_bonus' => 1000,
            ],
            'silver' => [
                'birthday_coupon' => 5000,
                'monthly_points' => 500,
            ],
            'gold' => [
                'birthday_coupon' => 10000,
                'monthly_points' => 1000,
                'early_access' => true,
            ],
            'platinum' => [
                'birthday_coupon' => 20000,
                'monthly_points' => 2000,
                'early_access' => true,
                'concierge_service' => true,
                'annual_gift' => true,
            ],
            default => [],
        };

        return array_merge($baseBenefits, $specificBenefits);
    }

    /**
     * Get discount rate for level
     */
    private function getDiscountRate(string $level): float
    {
        return match($level) {
            'bronze' => 0.0,
            'silver' => 2.0,
            'gold' => 5.0,
            'platinum' => 10.0,
            default => 0.0,
        };
    }

    /**
     * Get sort order for level
     */
    private function getSortOrder(string $level): int
    {
        return match($level) {
            'bronze' => 1,
            'silver' => 2,
            'gold' => 3,
            'platinum' => 4,
            default => 999,
        };
    }

    /**
     * Create bronze level
     */
    public function bronze(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Bronze Level',
            'code' => 'bronze',
            'min_points' => 0,
            'min_purchase_amount' => 0,
            'benefits' => $this->getBenefits('bronze'),
            'sort_order' => 1,
        ]);
    }

    /**
     * Create silver level
     */
    public function silver(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Silver Level',
            'code' => 'silver',
            'min_points' => 1000,
            'min_purchase_amount' => 100000,
            'benefits' => $this->getBenefits('silver'),
            'sort_order' => 2,
        ]);
    }

    /**
     * Create gold level
     */
    public function gold(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Gold Level',
            'code' => 'gold',
            'min_points' => 5000,
            'min_purchase_amount' => 500000,
            'benefits' => $this->getBenefits('gold'),
            'sort_order' => 3,
        ]);
    }

    /**
     * Create platinum level
     */
    public function platinum(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Platinum Level',
            'code' => 'platinum',
            'min_points' => 10000,
            'min_purchase_amount' => 1000000,
            'benefits' => $this->getBenefits('platinum'),
            'sort_order' => 4,
        ]);
    }

    /**
     * Create inactive level
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create level with custom benefits
     */
    public function withBenefits(array $benefits): static
    {
        return $this->state(fn (array $attributes) => [
            'benefits' => array_merge($attributes['benefits'] ?? [], $benefits),
        ]);
    }
}
