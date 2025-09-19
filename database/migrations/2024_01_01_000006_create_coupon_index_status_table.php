<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_index_status', function (Blueprint $table) {
            $table->id();
            $table->string('index_type'); // coupon, user_coupons, promotion_coupons 등
            $table->string('entity_key'); // Redis 키 또는 엔티티 식별자
            $table->unsignedBigInteger('entity_id')->nullable(); // 관련 엔티티 ID
            $table->enum('status', ['pending', 'indexing', 'completed', 'failed'])->default('pending');
            $table->timestamp('last_updated_at'); // DB에서 마지막 업데이트 시간
            $table->timestamp('last_indexed_at')->nullable(); // 인덱스 마지막 업데이트 시간
            $table->json('index_data')->nullable(); // 인덱스 데이터 요약
            $table->text('error_message')->nullable(); // 오류 메시지
            $table->integer('retry_count')->default(0);
            $table->timestamps();

            $table->unique(['index_type', 'entity_key']);
            $table->index(['status', 'last_updated_at']);
            $table->index(['entity_id', 'index_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_index_status');
    }
};
