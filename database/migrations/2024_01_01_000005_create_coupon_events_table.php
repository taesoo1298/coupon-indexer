<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_events', function (Blueprint $table) {
            $table->id();
            $table->enum('event_type', [
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
            $table->string('entity_type'); // coupon, promotion, user
            $table->unsignedBigInteger('entity_id'); // 관련 엔티티 ID
            $table->unsignedBigInteger('user_id')->nullable(); // 관련 사용자 ID
            $table->json('event_data'); // 이벤트 상세 데이터
            $table->json('previous_state')->nullable(); // 이전 상태 (업데이트의 경우)
            $table->json('current_state')->nullable(); // 현재 상태
            $table->timestamp('occurred_at'); // 이벤트 발생 시간
            $table->boolean('is_processed')->default(false); // 인덱싱 처리 완료 여부
            $table->timestamp('processed_at')->nullable(); // 처리 완료 시간
            $table->integer('retry_count')->default(0); // 재시도 횟수
            $table->json('processing_errors')->nullable(); // 처리 오류 정보
            $table->timestamps();

            $table->index(['event_type', 'occurred_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['is_processed', 'occurred_at']);
            $table->index(['retry_count', 'is_processed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_events');
    }
};
