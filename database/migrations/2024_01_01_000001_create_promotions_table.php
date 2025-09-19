<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed_amount', 'free_shipping', 'buy_x_get_y']);
            $table->decimal('value', 10, 2)->nullable(); // 할인값 또는 비율
            $table->json('conditions')->nullable(); // 적용 조건 (최소 주문금액, 카테고리 등)
            $table->json('targeting_rules')->nullable(); // 타겟 사용자 조건
            $table->datetime('start_date');
            $table->datetime('end_date');
            $table->boolean('is_active')->default(true);
            $table->integer('max_usage_count')->nullable(); // 전체 사용 제한
            $table->integer('max_usage_per_user')->nullable(); // 사용자당 사용 제한
            $table->integer('current_usage_count')->default(0);
            $table->integer('priority')->default(0); // 우선순위 (높을수록 우선)
            $table->json('metadata')->nullable(); // 추가 메타데이터
            $table->timestamps();

            $table->index(['is_active', 'start_date', 'end_date']);
            $table->index(['priority']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
