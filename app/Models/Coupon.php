<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Events\CouponIssued;
use App\Events\CouponUsed;
use App\Events\CouponExpired;
use App\Events\CouponRevoked;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'promotion_id',
        'code',
        'status',
        'user_id',
        'issued_at',
        'expires_at',
        'used_at',
        'usage_restrictions',
        'discount_amount',
        'metadata',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'usage_restrictions' => 'array',
        'metadata' => 'array',
        'discount_amount' => 'decimal:2',
    ];

    protected $dispatchesEvents = [
        'created' => CouponIssued::class,
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 쿠폰이 현재 사용 가능한지 확인
     */
    public function isUsable(): bool
    {
        return $this->status === 'active'
            && $this->expires_at > now()
            && $this->promotion?->isCurrentlyActive();
    }

    /**
     * 쿠폰이 만료되었는지 확인
     */
    public function isExpired(): bool
    {
        return $this->expires_at <= now() || $this->status === 'expired';
    }

    /**
     * 쿠폰 사용 처리
     */
    public function markAsUsed(float $discountAmount = null): void
    {
        $this->update([
            'status' => 'used',
            'used_at' => now(),
            'discount_amount' => $discountAmount,
        ]);

        $this->promotion?->incrementUsage();
        event(new CouponUsed($this));
    }

    /**
     * 쿠폰 만료 처리
     */
    public function markAsExpired(): void
    {
        if ($this->status !== 'expired') {
            $this->update(['status' => 'expired']);
            event(new CouponExpired($this));
        }
    }

    /**
     * 쿠폰 취소/회수 처리
     */
    public function revoke(string $reason = null): void
    {
        $this->update([
            'status' => 'revoked',
            'metadata' => array_merge($this->metadata ?? [], [
                'revoked_at' => now(),
                'revoke_reason' => $reason,
            ]),
        ]);

        event(new CouponRevoked($this));
    }

    /**
     * 특정 조건에 쿠폰이 적용 가능한지 확인
     */
    public function canApplyTo(array $conditions): bool
    {
        if (!$this->isUsable()) {
            return false;
        }

        if (empty($this->usage_restrictions)) {
            return true;
        }

        foreach ($this->usage_restrictions as $restriction => $criteria) {
            switch ($restriction) {
                case 'min_amount':
                    if (($conditions['amount'] ?? 0) < $criteria) {
                        return false;
                    }
                    break;

                case 'categories':
                    if (!empty($criteria) && !array_intersect($criteria, $conditions['categories'] ?? [])) {
                        return false;
                    }
                    break;

                case 'products':
                    if (!empty($criteria) && !array_intersect($criteria, $conditions['products'] ?? [])) {
                        return false;
                    }
                    break;

                case 'excluded_categories':
                    if (!empty($criteria) && array_intersect($criteria, $conditions['categories'] ?? [])) {
                        return false;
                    }
                    break;

                case 'excluded_products':
                    if (!empty($criteria) && array_intersect($criteria, $conditions['products'] ?? [])) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * 쿠폰 할인 금액 계산
     */
    public function calculateDiscount(float $amount): float
    {
        $promotion = $this->promotion;
        if (!$promotion) {
            return 0;
        }

        switch ($promotion->type) {
            case 'percentage':
                $discount = $amount * ($promotion->value / 100);
                break;

            case 'fixed_amount':
                $discount = $promotion->value;
                break;

            case 'free_shipping':
                // 배송비 할인은 별도 로직으로 처리
                return 0;

            default:
                return 0;
        }

        // 할인 금액이 주문 금액을 초과하지 않도록 제한
        return min($discount, $amount);
    }

    /**
     * 쿠폰 코드 생성 (정적 메서드)
     */
    public static function generateCode(int $length = 8): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        // 중복 체크
        while (static::where('code', $code)->exists()) {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        }

        return $code;
    }
}
