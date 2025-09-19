<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->onDelete('cascade');
            $table->string('code')->unique();
            $table->enum('status', ['active', 'used', 'expired', 'revoked'])->default('active');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // 발급받은 사용자
            $table->datetime('issued_at');
            $table->datetime('expires_at');
            $table->datetime('used_at')->nullable();
            $table->json('usage_restrictions')->nullable(); // 사용 제한 조건
            $table->decimal('discount_amount', 10, 2)->nullable(); // 실제 할인된 금액
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['user_id', 'status']);
            $table->index(['promotion_id', 'status']);
            $table->index(['code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
