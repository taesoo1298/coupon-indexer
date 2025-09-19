<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CouponEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type',
        'entity_type',
        'entity_id',
        'user_id',
        'event_data',
        'previous_state',
        'current_state',
        'occurred_at',
        'is_processed',
        'processed_at',
        'retry_count',
        'processing_errors',
    ];

    protected $casts = [
        'event_data' => 'array',
        'previous_state' => 'array',
        'current_state' => 'array',
        'occurred_at' => 'datetime',
        'processed_at' => 'datetime',
        'is_processed' => 'boolean',
        'retry_count' => 'integer',
        'processing_errors' => 'array',
    ];

    /**
     * 이벤트를 처리 완료로 표시
     */
    public function markAsProcessed(): void
    {
        $this->update([
            'is_processed' => true,
            'processed_at' => now(),
        ]);
    }

    /**
     * 처리 실패 시 재시도 횟수 증가
     */
    public function incrementRetry(array $errors = []): void
    {
        $this->update([
            'retry_count' => $this->retry_count + 1,
            'processing_errors' => $errors,
        ]);
    }

    /**
     * 미처리 이벤트들 조회
     */
    public static function getPendingEvents(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_processed', false)
            ->where('retry_count', '<', 5) // 최대 5회 재시도
            ->orderBy('occurred_at')
            ->limit($limit)
            ->get();
    }

    /**
     * 특정 타입의 이벤트들 조회
     */
    public static function getEventsByType(string $eventType, int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('event_type', $eventType)
            ->orderBy('occurred_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 특정 엔티티의 이벤트들 조회
     */
    public static function getEventsForEntity(string $entityType, int $entityId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('occurred_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 이벤트 생성 헬퍼 메서드
     */
    public static function createEvent(
        string $eventType,
        string $entityType,
        int $entityId,
        array $eventData,
        int $userId = null,
        array $previousState = null,
        array $currentState = null
    ): CouponEvent {
        return static::create([
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $userId,
            'event_data' => $eventData,
            'previous_state' => $previousState,
            'current_state' => $currentState,
            'occurred_at' => now(),
        ]);
    }

    /**
     * 이벤트가 재처리 가능한지 확인
     */
    public function canRetry(): bool
    {
        return !$this->is_processed && $this->retry_count < 5;
    }

    /**
     * 이벤트 처리 실패 여부 확인
     */
    public function hasFailed(): bool
    {
        return !$this->is_processed && $this->retry_count >= 5;
    }

    /**
     * 관련 사용자 조회
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 스코프: 특정 이벤트 타입
     */
    public function scopeOfType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * 스코프: 처리되지 않은 이벤트
     */
    public function scopeUnprocessed($query)
    {
        return $query->where('is_processed', false);
    }

    /**
     * 스코프: 실패한 이벤트
     */
    public function scopeFailed($query)
    {
        return $query->where('is_processed', false)
                    ->where('retry_count', '>=', 5);
    }
}
