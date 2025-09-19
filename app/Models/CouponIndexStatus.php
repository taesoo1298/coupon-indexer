<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CouponIndexStatus extends Model
{
    use HasFactory;

    protected $table = 'coupon_index_status';

    protected $fillable = [
        'index_type',
        'entity_key',
        'entity_id',
        'status',
        'last_updated_at',
        'last_indexed_at',
        'index_data',
        'error_message',
        'retry_count',
    ];

    protected $casts = [
        'last_updated_at' => 'datetime',
        'last_indexed_at' => 'datetime',
        'index_data' => 'array',
        'retry_count' => 'integer',
    ];

    /**
     * 인덱스 상태를 업데이트
     */
    public function updateStatus(string $status, array $indexData = null, string $errorMessage = null): void
    {
        $updateData = [
            'status' => $status,
            'last_indexed_at' => now(),
        ];

        if ($indexData !== null) {
            $updateData['index_data'] = $indexData;
        }

        if ($errorMessage !== null) {
            $updateData['error_message'] = $errorMessage;
        }

        if ($status === 'completed') {
            $updateData['retry_count'] = 0;
            $updateData['error_message'] = null;
        }

        $this->update($updateData);
    }

    /**
     * 처리 실패 시 재시도 횟수 증가
     */
    public function incrementRetry(string $errorMessage): void
    {
        $this->update([
            'retry_count' => $this->retry_count + 1,
            'error_message' => $errorMessage,
            'status' => 'failed',
        ]);
    }

    /**
     * 인덱스 상태 조회 또는 생성
     */
    public static function findOrCreate(string $indexType, string $entityKey, int $entityId = null): CouponIndexStatus
    {
        return static::firstOrCreate(
            [
                'index_type' => $indexType,
                'entity_key' => $entityKey,
            ],
            [
                'entity_id' => $entityId,
                'status' => 'pending',
                'last_updated_at' => now(),
                'retry_count' => 0,
            ]
        );
    }

    /**
     * 재처리가 필요한 인덱스들 조회
     */
    public static function getPendingIndexes(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereIn('status', ['pending', 'failed'])
            ->where('retry_count', '<', 5)
            ->orderBy('last_updated_at')
            ->limit($limit)
            ->get();
    }

    /**
     * 특정 타입의 인덱스들 조회
     */
    public static function getIndexesByType(string $indexType, int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('index_type', $indexType)
            ->orderBy('last_indexed_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 실패한 인덱스들 조회
     */
    public static function getFailedIndexes(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('status', 'failed')
            ->where('retry_count', '>=', 5)
            ->orderBy('last_updated_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 인덱스 상태 통계 조회
     */
    public static function getStatusStats(): array
    {
        return [
            'pending' => static::where('status', 'pending')->count(),
            'indexing' => static::where('status', 'indexing')->count(),
            'completed' => static::where('status', 'completed')->count(),
            'failed' => static::where('status', 'failed')->count(),
            'total' => static::count(),
        ];
    }
}
