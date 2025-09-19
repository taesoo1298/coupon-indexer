<?php

namespace Database\Seeders;

use App\Models\Promotion;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create active general promotions
        Promotion::factory()->active()->create([
            'name' => '신규 가입자 환영 할인',
            'description' => '새로 가입한 사용자를 위한 15% 할인 혜택',
            'type' => 'percentage',
            'value' => 15.0,
            'conditions' => [
                'first_purchase_only' => true,
                'min_purchase_amount' => 30000,
            ],
            'targeting_rules' => [
                'new_users_only' => true,
            ],
            'max_usage_count' => 1000,
            'max_usage_per_user' => 1,
            'priority' => 1,
        ]);

        Promotion::factory()->active()->create([
            'name' => 'VIP 고객 특별 할인',
            'description' => 'VIP 고객을 위한 프리미엄 할인 혜택',
            'type' => 'percentage',
            'value' => 25.0,
            'conditions' => [
                'min_purchase_amount' => 100000,
            ],
            'targeting_rules' => [
                'vip_only' => true,
                'user_levels' => [3, 4], // Gold, Platinum
            ],
            'max_usage_count' => 500,
            'max_usage_per_user' => 2,
            'priority' => 2,
        ]);

        Promotion::factory()->active()->create([
            'name' => '전자제품 카테고리 할인',
            'description' => '모든 전자제품 20% 할인',
            'type' => 'percentage',
            'value' => 20.0,
            'conditions' => [
                'applicable_categories' => ['electronics'],
                'min_purchase_amount' => 50000,
            ],
            'max_usage_count' => 2000,
            'max_usage_per_user' => 2,
            'priority' => 3,
        ]);

        Promotion::factory()->active()->create([
            'name' => '도서 카테고리 정액 할인',
            'description' => '모든 도서 구매 시 5,000원 할인',
            'type' => 'fixed_amount',
            'value' => 5000,
            'conditions' => [
                'applicable_categories' => ['books'],
                'min_purchase_amount' => 15000,
            ],
            'max_usage_count' => 1500,
            'max_usage_per_user' => 3,
            'priority' => 4,
        ]);

        Promotion::factory()->active()->create([
            'name' => '실버 등급 특별 혜택',
            'description' => '실버 등급 고객을 위한 특별 할인',
            'type' => 'percentage',
            'value' => 12.0,
            'conditions' => [
                'min_purchase_amount' => 80000,
            ],
            'targeting_rules' => [
                'user_levels' => [2], // Silver
            ],
            'max_usage_count' => 800,
            'max_usage_per_user' => 1,
            'priority' => 5,
        ]);

        // Create weekend special promotion
        Promotion::factory()->active()->create([
            'name' => '주말 특가 프로모션',
            'description' => '주말에만 제공되는 특별 할인',
            'type' => 'percentage',
            'value' => 18.0,
            'conditions' => [
                'valid_days_of_week' => ['saturday', 'sunday'],
                'min_purchase_amount' => 40000,
            ],
            'max_usage_count' => 600,
            'max_usage_per_user' => 1,
            'priority' => 6,
        ]);

        // Create high-value promotion
        Promotion::factory()->active()->create([
            'name' => '고액 구매자 프리미엄 혜택',
            'description' => '500만원 이상 구매 시 특별 할인',
            'type' => 'fixed_amount',
            'value' => 500000,
            'conditions' => [
                'min_purchase_amount' => 5000000,
            ],
            'targeting_rules' => [
                'user_levels' => [3, 4], // Gold, Platinum
            ],
            'max_usage_count' => 50,
            'max_usage_per_user' => 1,
            'priority' => 1,
        ]);

        // Create random active promotions
        Promotion::factory()->active()->count(8)->create();

        // Create some inactive promotions for testing
        Promotion::factory()->inactive()->count(3)->create();

        // Create some expired promotions for testing
        Promotion::factory()->expired()->count(2)->create();

        $this->command->info('Promotions seeded successfully.');
    }
}
