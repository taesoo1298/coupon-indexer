<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 브론즈, 실버, 골드, 플래티넘 등
            $table->string('code')->unique(); // bronze, silver, gold, platinum
            $table->integer('min_points')->default(0); // 해당 등급 달성을 위한 최소 포인트
            $table->decimal('min_purchase_amount', 12, 2)->default(0); // 최소 구매 금액
            $table->json('benefits')->nullable(); // 등급별 혜택
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['min_points']);
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_levels');
    }
};
