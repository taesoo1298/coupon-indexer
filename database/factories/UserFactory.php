<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'user_level_id' => fake()->numberBetween(1, 4), // 1-4 corresponds to bronze-platinum
            'points' => fake()->numberBetween(0, 15000),
            'total_purchase_amount' => fake()->randomFloat(2, 0, 1000000),
            'level_updated_at' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
            'preferences' => [
                'newsletter' => fake()->boolean(),
                'sms_notifications' => fake()->boolean(),
                'favorite_categories' => fake()->randomElements(['electronics', 'books', 'clothing'], fake()->numberBetween(0, 3)),
            ],
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user should have a specific level.
     */
    public function withLevel(int $levelId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_level_id' => $levelId,
            'level_updated_at' => now(),
        ]);
    }

    /**
     * Indicate that the user should be a VIP (Gold or Platinum).
     */
    public function vip(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_level_id' => fake()->randomElement([3, 4]), // Gold or Platinum
            'points' => fake()->numberBetween(5000, 20000),
            'total_purchase_amount' => fake()->randomFloat(2, 500000, 2000000),
        ]);
    }

    /**
     * Indicate that the user should have high purchase amounts.
     */
    public function highSpender(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_purchase_amount' => fake()->randomFloat(2, 500000, 2000000),
            'points' => fake()->numberBetween(5000, 20000),
            'user_level_id' => fake()->randomElement([3, 4]), // Gold or Platinum
        ]);
    }
}
