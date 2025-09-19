<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Events\UserLevelChanged;
use App\Events\UserProfileUpdated;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_level_id',
        'points',
        'total_purchase_amount',
        'level_updated_at',
        'preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'points' => 'integer',
            'total_purchase_amount' => 'decimal:2',
            'level_updated_at' => 'datetime',
            'preferences' => 'array',
        ];
    }

    public function userLevel(): BelongsTo
    {
        return $this->belongsTo(UserLevel::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    /**
     * 사용 가능한 쿠폰들 조회
     */
    public function availableCoupons(): HasMany
    {
        return $this->coupons()
            ->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    /**
     * 포인트 추가 및 등급 업데이트
     */
    public function addPoints(int $points): void
    {
        $previousLevel = $this->user_level_id;
        $this->increment('points', $points);

        $this->updateUserLevel();

        if ($this->user_level_id !== $previousLevel) {
            event(new UserLevelChanged($this, $previousLevel, $this->user_level_id));
        }
    }

    /**
     * 구매 금액 추가 및 등급 업데이트
     */
    public function addPurchaseAmount(float $amount): void
    {
        $previousLevel = $this->user_level_id;
        $this->increment('total_purchase_amount', $amount);

        $this->updateUserLevel();

        if ($this->user_level_id !== $previousLevel) {
            event(new UserLevelChanged($this, $previousLevel, $this->user_level_id));
        }
    }

    /**
     * 사용자 등급 업데이트
     */
    public function updateUserLevel(): void
    {
        $newLevel = UserLevel::getHighestAchievableLevel(
            $this->points,
            $this->total_purchase_amount
        );

        if ($newLevel && $newLevel->id !== $this->user_level_id) {
            $this->update([
                'user_level_id' => $newLevel->id,
                'level_updated_at' => now(),
            ]);
        }
    }

    /**
     * 사용자 프로필 업데이트 (이벤트 발생)
     */
    public function updateProfile(array $data): void
    {
        $previousData = $this->toArray();
        $this->update($data);

        event(new UserProfileUpdated($this, $previousData));
    }

    /**
     * 특정 프로모션에 대한 사용자의 쿠폰 사용 횟수 조회
     */
    public function getCouponUsageCount(int $promotionId): int
    {
        return $this->coupons()
            ->where('promotion_id', $promotionId)
            ->where('status', 'used')
            ->count();
    }

    /**
     * 사용자가 특정 타겟팅 조건을 만족하는지 확인
     */
    public function matchesTargeting(array $targetingRules): bool
    {
        foreach ($targetingRules as $rule => $criteria) {
            switch ($rule) {
                case 'user_level':
                    if (!in_array($this->userLevel?->code, $criteria)) {
                        return false;
                    }
                    break;

                case 'min_points':
                    if ($this->points < $criteria) {
                        return false;
                    }
                    break;

                case 'min_purchase_amount':
                    if ($this->total_purchase_amount < $criteria) {
                        return false;
                    }
                    break;

                case 'excluded_users':
                    if (in_array($this->id, $criteria)) {
                        return false;
                    }
                    break;

                case 'age_range':
                    // 생년월일이 있다면 나이 계산 로직 추가
                    break;

                case 'location':
                    // 위치 정보가 있다면 위치 기반 매칭 로직 추가
                    break;
            }
        }

        return true;
    }
}
