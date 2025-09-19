<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Events\PromotionUpdated;
use App\Events\PromotionCreated;
use App\Events\PromotionActivated;
use App\Events\PromotionDeactivated;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'value',
        'conditions',
        'targeting_rules',
        'start_date',
        'end_date',
        'is_active',
        'max_usage_count',
        'max_usage_per_user',
        'current_usage_count',
        'priority',
        'metadata',
    ];

    protected $casts = [
        'conditions' => 'array',
        'targeting_rules' => 'array',
        'metadata' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'value' => 'decimal:2',
        'max_usage_count' => 'integer',
        'max_usage_per_user' => 'integer',
        'current_usage_count' => 'integer',
        'priority' => 'integer',
    ];

    protected $dispatchesEvents = [
        'created' => PromotionCreated::class,
        'updated' => PromotionUpdated::class,
    ];

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    /**
     * 프로모션이 현재 활성화된 상태인지 확인
     */
    public function isCurrentlyActive(): bool
    {
        $now = now();
        return $this->is_active
            && $this->start_date <= $now
            && $this->end_date >= $now;
    }

    /**
     * 사용 가능한 쿠폰 수량이 남아있는지 확인
     */
    public function hasAvailableUsage(): bool
    {
        if ($this->max_usage_count === null) {
            return true;
        }

        return $this->current_usage_count < $this->max_usage_count;
    }

    /**
     * 사용자가 이 프로모션을 사용할 수 있는지 확인
     */
    public function canUserUse(User $user): bool
    {
        if ($this->max_usage_per_user === null) {
            return true;
        }

        $userUsageCount = $this->coupons()
            ->where('user_id', $user->id)
            ->where('status', 'used')
            ->count();

        return $userUsageCount < $this->max_usage_per_user;
    }

    /**
     * 타겟팅 규칙에 따라 사용자가 대상인지 확인
     */
    public function matchesTargeting(User $user): bool
    {
        if (empty($this->targeting_rules)) {
            return true;
        }

        foreach ($this->targeting_rules as $rule => $criteria) {
            switch ($rule) {
                case 'user_level':
                    if (!in_array($user->user_level?->code, $criteria)) {
                        return false;
                    }
                    break;

                case 'min_points':
                    if ($user->points < $criteria) {
                        return false;
                    }
                    break;

                case 'min_purchase_amount':
                    if ($user->total_purchase_amount < $criteria) {
                        return false;
                    }
                    break;

                case 'excluded_users':
                    if (in_array($user->id, $criteria)) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * 프로모션 활성화
     */
    public function activate(): void
    {
        $wasActive = $this->is_active;
        $this->update(['is_active' => true]);

        if (!$wasActive) {
            event(new PromotionActivated($this));
        }
    }

    /**
     * 프로모션 비활성화
     */
    public function deactivate(): void
    {
        $wasActive = $this->is_active;
        $this->update(['is_active' => false]);

        if ($wasActive) {
            event(new PromotionDeactivated($this));
        }
    }

    /**
     * 프로모션 사용 횟수 증가
     */
    public function incrementUsage(): void
    {
        $this->increment('current_usage_count');
    }
}
