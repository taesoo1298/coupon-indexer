<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\RedisPubSubService;
use Illuminate\Support\Facades\Redis;

class RedisPubSubServiceTest extends TestCase
{
    private RedisPubSubService $pubsubService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pubsubService = app(RedisPubSubService::class);
    }

    /** @test */
    public function Redis_연결_상태를_확인할_수_있다()
    {
        // When: 연결 상태 확인
        $isConnected = $this->pubsubService->isConnected();
        $stats = $this->pubsubService->getConnectionStats();

        // Then: 연결 상태 정보가 반환됨
        $this->assertIsBool($isConnected);
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('connected', $stats);
        $this->assertArrayHasKey('channel', $stats);
        $this->assertArrayHasKey('reconnect_attempts', $stats);
    }

    /** @test */
    public function 채널_정보를_조회할_수_있다()
    {
        // When: 채널 정보 조회
        $channelInfo = $this->pubsubService->getChannelInfo();

        // Then: 채널 정보가 반환됨
        $this->assertIsArray($channelInfo);
        $this->assertArrayHasKey('channel', $channelInfo);
        $this->assertArrayHasKey('status', $channelInfo);
        $this->assertArrayHasKey('subscribers', $channelInfo);
        $this->assertArrayHasKey('connected', $channelInfo);

        // 채널명이 올바른지 확인
        $this->assertIsString($channelInfo['channel']);
        $this->assertContains($channelInfo['status'], ['active', 'error', 'disconnected']);
    }

    /** @test */
    public function 테스트_메시지를_발행할_수_있다()
    {
        // When: 테스트 메시지 발행
        $testResult = $this->pubsubService->testPublish();

        // Then: 테스트 결과가 배열로 반환됨
        $this->assertIsArray($testResult);
        $this->assertArrayHasKey('success', $testResult);
        $this->assertArrayHasKey('connection_status', $testResult);
        $this->assertArrayHasKey('channel', $testResult);

        if ($testResult['success']) {
            $this->assertArrayHasKey('test_data', $testResult);
            $this->assertIsArray($testResult['test_data']);
            $this->assertTrue($testResult['test_data']['test']);
        }
    }

    /** @test */
    public function 이벤트_메시지를_발행할_수_있다()
    {
        // Given: 테스트 이벤트 데이터
        $eventType = 'coupon_issued';
        $eventData = [
            'coupon_id' => 123,
            'user_id' => 456,
            'promotion_id' => 789,
        ];

        // When: 이벤트 발행
        $published = $this->pubsubService->publishEvent($eventType, $eventData);

        // Then: 발행 결과 확인
        $this->assertIsBool($published);

        // Redis가 연결되어 있다면 성공해야 함
        if ($this->pubsubService->isConnected()) {
            $this->assertTrue($published, '연결된 상태에서 이벤트 발행이 실패했습니다');
        }
    }

    /** @test */
    public function 잘못된_데이터로_이벤트_발행시_실패를_처리한다()
    {
        // Given: 잘못된 이벤트 데이터 (순환 참조)
        $circularArray = [];
        $circularArray['self'] = &$circularArray;

        // When: 순환 참조 데이터로 이벤트 발행 시도
        $published = $this->pubsubService->publishEvent('test_event', $circularArray);

        // Then: 실패를 올바르게 처리해야 함
        $this->assertFalse($published, '잘못된 데이터로 발행이 성공하면 안됩니다');
    }

    /** @test */
    public function 재연결을_시도할_수_있다()
    {
        // When: 강제 재연결 시도
        $reconnected = $this->pubsubService->forceReconnect();

        // Then: 재연결 시도 결과가 반환됨
        $this->assertIsBool($reconnected);

        // 재연결 후 연결 상태 확인
        $stats = $this->pubsubService->getConnectionStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('connected', $stats);
    }

    /** @test */
    public function 여러_이벤트를_연속으로_발행할_수_있다()
    {
        // Given: 여러 이벤트 데이터
        $events = [
            ['type' => 'coupon_issued', 'data' => ['coupon_id' => 1]],
            ['type' => 'coupon_used', 'data' => ['coupon_id' => 2]],
            ['type' => 'promotion_created', 'data' => ['promotion_id' => 3]],
        ];

        $results = [];

        // When: 여러 이벤트 연속 발행
        foreach ($events as $event) {
            $results[] = $this->pubsubService->publishEvent($event['type'], $event['data']);
        }

        // Then: 모든 이벤트가 처리됨 (성공 여부는 연결 상태에 따라 다름)
        $this->assertCount(3, $results);

        foreach ($results as $result) {
            $this->assertIsBool($result);
        }

        // 연결되어 있다면 모든 이벤트가 성공해야 함
        if ($this->pubsubService->isConnected()) {
            foreach ($results as $result) {
                $this->assertTrue($result, '연결된 상태에서 이벤트 발행이 실패했습니다');
            }
        }
    }

    protected function tearDown(): void
    {
        // 테스트 후 정리 작업
        try {
            // Redis 연결이 있다면 정리
            if ($this->pubsubService->isConnected()) {
                // 필요시 정리 작업 수행
            }
        } catch (\Exception $e) {
            // 정리 중 오류는 무시
        }

        parent::tearDown();
    }
}
