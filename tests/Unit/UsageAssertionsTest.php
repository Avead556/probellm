<?php

declare(strict_types=1);

namespace ProbeLLM\Tests\Unit;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use ProbeLLM\DSL\AnswerExpectations;
use ProbeLLM\DTO\ProviderResult;
use ProbeLLM\Provider\NullProvider;

class UsageAssertionsTest extends TestCase
{
    public function test_max_total_tokens_passes(): void
    {
        $this->makeWithUsage(['total_tokens' => 500])
            ->assertMaxTotalTokens(1000);
        $this->addToAssertionCount(1);
    }

    public function test_max_total_tokens_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('1500 exceeded limit of 1000');

        $this->makeWithUsage(['total_tokens' => 1500])
            ->assertMaxTotalTokens(1000);
    }

    public function test_max_total_tokens_computes_from_parts(): void
    {
        // Anthropic style: no total_tokens, compute from parts.
        $this->makeWithUsage(['input_tokens' => 200, 'output_tokens' => 300])
            ->assertMaxTotalTokens(600);
        $this->addToAssertionCount(1);
    }

    public function test_max_input_tokens_openai(): void
    {
        $this->makeWithUsage(['prompt_tokens' => 100, 'completion_tokens' => 50])
            ->assertMaxInputTokens(200);
        $this->addToAssertionCount(1);
    }

    public function test_max_input_tokens_anthropic(): void
    {
        $this->makeWithUsage(['input_tokens' => 100, 'output_tokens' => 50])
            ->assertMaxInputTokens(200);
        $this->addToAssertionCount(1);
    }

    public function test_max_output_tokens_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('500 exceeded limit of 200');

        $this->makeWithUsage(['prompt_tokens' => 100, 'completion_tokens' => 500])
            ->assertMaxOutputTokens(200);
    }

    public function test_skips_silently_when_no_usage(): void
    {
        $expectations = new AnswerExpectations(
            result: new ProviderResult('Hello'),
            provider: new NullProvider(),
        );

        // Should not throw.
        $expectations
            ->assertMaxTotalTokens(10)
            ->assertMaxInputTokens(10)
            ->assertMaxOutputTokens(10);
        $this->addToAssertionCount(1);
    }

    public function test_total_tokens_accessor(): void
    {
        $e = $this->makeWithUsage(['total_tokens' => 750]);
        $this->assertSame(750, $e->totalTokens());
    }

    public function test_input_output_tokens_accessors(): void
    {
        $e = $this->makeWithUsage(['prompt_tokens' => 200, 'completion_tokens' => 100]);
        $this->assertSame(200, $e->inputTokens());
        $this->assertSame(100, $e->outputTokens());
    }

    public function test_tokens_null_when_no_usage(): void
    {
        $e = new AnswerExpectations(
            result: new ProviderResult('Hello'),
            provider: new NullProvider(),
        );
        $this->assertNull($e->totalTokens());
        $this->assertNull($e->inputTokens());
        $this->assertNull($e->outputTokens());
    }

    private function makeWithUsage(array $usage): AnswerExpectations
    {
        return new AnswerExpectations(
            result: new ProviderResult('Hello', [], ['usage' => $usage]),
            provider: new NullProvider(),
        );
    }
}
