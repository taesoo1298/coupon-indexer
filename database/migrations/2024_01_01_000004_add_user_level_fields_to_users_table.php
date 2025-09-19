<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('user_level_id')->nullable()->after('email')->constrained()->onDelete('set null');
            $table->integer('points')->default(0)->after('user_level_id'); // 적립 포인트
            $table->decimal('total_purchase_amount', 12, 2)->default(0)->after('points'); // 총 구매 금액
            $table->datetime('level_updated_at')->nullable()->after('total_purchase_amount'); // 등급 변경 일시
            $table->json('preferences')->nullable()->after('level_updated_at'); // 사용자 선호도/프로필

            $table->index(['user_level_id', 'points']);
            $table->index(['total_purchase_amount']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['user_level_id']);
            $table->dropColumn([
                'user_level_id',
                'points',
                'total_purchase_amount',
                'level_updated_at',
                'preferences'
            ]);
        });
    }
};
