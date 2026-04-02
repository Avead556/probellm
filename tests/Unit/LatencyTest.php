<?php

declare(strict_types=1);

namespace ProbeLLM\Tests\Unit;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use ProbeLLM\DSL\AnswerExpectations;
use ProbeLLM\DTO\ProviderResult;
use ProbeLLM\Provider\NullProvider;

class LatencyTest extends TestCase
{
    public function test_assert_response_time_passes_when_within_limit(): void
    {
        $expectations = $this->makeExpectations(elapsedMs: 500);

        $expectations->assertResponseTime(maxMs: 1000);
        $this->addToAssertionCount(1);
    }

    public function test_assert_response_time_passes_at_exact_limit(): void
    {
        $expectations = $this->makeExpectations(elapsedMs: 3000);

        $expectations->assertResponseTime(maxMs: 3000);
        $this->addToAssertionCount(1);
    }

    public function test_assert_response_time_fails_when_exceeded(): void
    {
        $expectations = $this->makeExpectations(elapsedMs: 5000);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('5000ms exceeded the limit of 3000ms');

        $expectations->assertResponseTime(maxMs: 3000);
    }

    public function test_assert_response_time_skips_when_no_timing_data(): void
    {
        // Mock/cassette result with no elapsed_ms in meta.
        $expectations = new AnswerExpectations(
            result: new ProviderResult('Hello'),
            provider: new NullProvider(),
        );

        // Should pass silently — no timing data available.
        $expectations->assertResponseTime(maxMs: 100);
        $this->addToAssertionCount(1);
    }

    public function test_assert_min_response_time_passes(): void
    {
        $expectations = $this->makeExpectations(elapsedMs: 500);

        $expectations->assertMinResponseTime(minMs: 100);
        $this->addToAssertionCount(1);
    }

    public function test_assert_min_response_time_fails(): void
    {
        $expectations = $this->makeExpectations(elapsedMs: 50);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('50ms is below the minimum of 100ms');

        $expectations->assertMinResponseTime(minMs: 100);
    }

    public function test_response_time_ms_returns_value(): void
    {
        $expectations = $this->makeExpectations(elapsedMs: 1234);

        $this->assertSame(1234, $expectations->responseTimeMs());
    }

    public function test_response_time_ms_returns_null_when_missing(): void
    {
        $expectations = new AnswerExpectations(
            result: new ProviderResult('Hello'),
            provider: new NullProvider(),
        );

        $this->assertNull($expectations->responseTimeMs());
    }

    public function test_assert_response_time_is_chainable(): void
    {
        $expectations = $this->makeExpectations(elapsedMs: 200);

        $result = $expectations
            ->assertResponseTime(maxMs: 1000)
            ->assertMinResponseTime(minMs: 100);

        $this->assertInstanceOf(AnswerExpectations::class, $result);
    }

    private function makeExpectations(int $elapsedMs): AnswerExpectations
    {
        return new AnswerExpectations(
            result: new ProviderResult('Hello', [], ['elapsed_ms' => $elapsedMs]),
            provider: new NullProvider(),
        );
    }
}
