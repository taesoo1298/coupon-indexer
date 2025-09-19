<?php

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issuedAt = fake()->dateTimeBetween('-6 months', 'now');
        $expiresAt = fake()->dateTimeBetween($issuedAt, '+3 months');

        return [
            'promotion_id' => Promotion::factory(),
            'user_id' => User::factory(),
            'code' => $this->generateCouponCode(),
            'status' => fake()->randomElement(['active', 'used', 'expired', 'revoked']),
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'used_at' => null,
            'metadata' => $this->generateMetadata(),
        ];
    }

    /**
     * Generate a unique coupon code
     */
    private function generateCouponCode(): string
    {
        $prefix = fake()->randomElement(['SAVE', 'DISC', 'DEAL', 'PROMO', 'SPEC']);
        $number = fake()->unique()->numberBetween(100000, 999999);

        return $prefix . $number;
    }

    /**
     * Generate coupon metadata
     */
    private function generateMetadata(): array
    {
        $metadata = [
            'source' => fake()->randomElement(['web', 'mobile', 'email', 'sms', 'admin']),
            'campaign_id' => fake()->optional()->randomNumber(5),
        ];

        // Add optional fields
        if (fake()->boolean(30)) {
            $metadata['referrer'] = fake()->url();
        }

        if (fake()->boolean(20)) {
            $metadata['user_agent'] = fake()->userAgent();
        }

        if (fake()->boolean(40)) {
            $metadata['notes'] = fake()->sentence();
        }

        return $metadata;
    }

    /**
     * Indicate that the coupon should be active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'used_at' => null,
            'expires_at' => now()->addMonths(2),
        ]);
    }

    /**
     * Indicate that the coupon should be used.
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'used',
            'used_at' => fake()->dateTimeBetween($attributes['issued_at'] ?? '-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the coupon should be expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => fake()->dateTimeBetween('-2 months', '-1 day'),
            'used_at' => null,
        ]);
    }

    /**
     * Indicate that the coupon should be revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'revoked',
            'used_at' => null,
        ]);
    }

    /**
     * Indicate that the coupon should be for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Indicate that the coupon should be for a specific promotion.
     */
    public function forPromotion(Promotion $promotion): static
    {
        return $this->state(fn (array $attributes) => [
            'promotion_id' => $promotion->id,
        ]);
    }

    /**
     * Indicate that the coupon should expire soon.
     */
    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'expires_at' => now()->addDays(fake()->numberBetween(1, 7)),
        ]);
    }

    /**
     * Indicate that the coupon should have specific metadata.
     */
    public function withMetadata(array $metadata): static
    {
        return $this->state(function (array $attributes) use ($metadata) {
            $existingMetadata = $attributes['metadata'] ?? [];

            return [
                'metadata' => array_merge($existingMetadata, $metadata),
            ];
        });
    }

    /**
     * Indicate that the coupon should have a specific source.
     */
    public function fromSource(string $source): static
    {
        return $this->withMetadata(['source' => $source]);
    }

    /**
     * Indicate that the coupon should be from a campaign.
     */
    public function fromCampaign(string $campaignId): static
    {
        return $this->withMetadata(['campaign_id' => $campaignId]);
    }
}
