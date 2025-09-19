<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'min_points',
        'min_purchase_amount',
        'benefits',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'benefits' => 'array',
        'min_points' => 'integer',
        'min_purchase_amount' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * 사용자가 이 등급에 도달할 수 있는지 확인
     */
    public function canUserAchieve(User $user): bool
    {
        return $user->points >= $this->min_points
            && $user->total_purchase_amount >= $this->min_purchase_amount;
    }

    /**
     * 특정 포인트와 구매 금액으로 달성 가능한 최고 등급 조회
     */
    public static function getHighestAchievableLevel(int $points, float $purchaseAmount): ?UserLevel
    {
        return static::where('is_active', true)
            ->where('min_points', '<=', $points)
            ->where('min_purchase_amount', '<=', $purchaseAmount)
            ->orderBy('sort_order', 'desc')
            ->first();
    }

    /**
     * 활성화된 모든 등급을 순서대로 조회
     */
    public static function getActiveLevels(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
}
