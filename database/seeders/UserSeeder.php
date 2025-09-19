<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin test user
        User::factory()->create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'user_level_id' => 4, // Platinum
            'points' => 15000,
            'total_purchase_amount' => 2000000,
        ]);

        // Create regular test user
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'user_level_id' => 2, // Silver
            'points' => 1500,
            'total_purchase_amount' => 150000,
        ]);

        // Create VIP test user
        User::factory()->create([
            'name' => 'VIP User',
            'email' => 'vip@example.com',
            'user_level_id' => 3, // Gold
            'points' => 7500,
            'total_purchase_amount' => 750000,
        ]);

        // Create bronze users (10)
        User::factory()->count(10)->create([
            'user_level_id' => 1, // Bronze
            'points' => fake()->numberBetween(0, 999),
            'total_purchase_amount' => fake()->randomFloat(2, 0, 99999),
        ]);

        // Create silver users (15)
        User::factory()->count(15)->create([
            'user_level_id' => 2, // Silver
            'points' => fake()->numberBetween(1000, 4999),
            'total_purchase_amount' => fake()->randomFloat(2, 100000, 499999),
        ]);

        // Create gold users (10)
        User::factory()->count(10)->create([
            'user_level_id' => 3, // Gold
            'points' => fake()->numberBetween(5000, 9999),
            'total_purchase_amount' => fake()->randomFloat(2, 500000, 999999),
        ]);

        // Create platinum users (5)
        User::factory()->count(5)->create([
            'user_level_id' => 4, // Platinum
            'points' => fake()->numberBetween(10000, 25000),
            'total_purchase_amount' => fake()->randomFloat(2, 1000000, 5000000),
        ]);

        $this->command->info('Users seeded successfully.');
    }
}
