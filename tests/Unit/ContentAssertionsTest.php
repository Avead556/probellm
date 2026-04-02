<?php

declare(strict_types=1);

namespace ProbeLLM\Tests\Unit;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use ProbeLLM\DSL\AnswerExpectations;
use ProbeLLM\DTO\ProviderResult;
use ProbeLLM\Provider\NullProvider;

class ContentAssertionsTest extends TestCase
{
    public function test_assert_contains_passes(): void
    {
        $this->make('The capital of France is Paris.')
            ->assertContains('Paris');
        $this->addToAssertionCount(1);
    }

    public function test_assert_contains_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->make('Hello world')->assertContains('Paris');
    }

    public function test_assert_contains_ignoring_case(): void
    {
        $this->make('The capital is PARIS.')
            ->assertContainsIgnoringCase('paris');
        $this->addToAssertionCount(1);
    }

    public function test_assert_not_contains_passes(): void
    {
        $this->make('Hello world')->assertNotContains('Paris');
        $this->addToAssertionCount(1);
    }

    public function test_assert_not_contains_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->make('Paris is great')->assertNotContains('Paris');
    }

    public function test_assert_matches_regex(): void
    {
        $this->make('The year is 2025.')
            ->assertMatchesRegex('/\d{4}/');
        $this->addToAssertionCount(1);
    }

    public function test_assert_matches_regex_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->make('No numbers here')->assertMatchesRegex('/\d{4}/');
    }

    public function test_assert_starts_with(): void
    {
        $this->make('Sure, I can help!')->assertStartsWith('Sure');
        $this->addToAssertionCount(1);
    }

    public function test_assert_ends_with(): void
    {
        $this->make('The answer is 42.')->assertEndsWith('42.');
        $this->addToAssertionCount(1);
    }

    public function test_assert_word_count_between(): void
    {
        $this->make('one two three four five')
            ->assertWordCountBetween(3, 10);
        $this->addToAssertionCount(1);
    }

    public function test_assert_word_count_below_min_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('expected at least 10');
        $this->make('two words')->assertWordCountBetween(10, 50);
    }

    public function test_assert_word_count_above_max_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('expected at most 2');
        $this->make('one two three four')->assertWordCountBetween(1, 2);
    }

    public function test_assert_not_empty(): void
    {
        $this->make('Hello')->assertNotEmpty();
        $this->addToAssertionCount(1);
    }

    public function test_assert_not_empty_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->make('   ')->assertNotEmpty();
    }

    public function test_assert_empty(): void
    {
        $this->make('')->assertEmpty();
        $this->addToAssertionCount(1);
    }

    public function test_assert_empty_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->make('not empty')->assertEmpty();
    }

    public function test_fluent_chain(): void
    {
        $result = $this->make('The capital of France is Paris.')
            ->assertContains('Paris')
            ->assertNotContains('London')
            ->assertStartsWith('The')
            ->assertEndsWith('.')
            ->assertMatchesRegex('/France/')
            ->assertWordCountBetween(1, 20)
            ->assertNotEmpty();

        $this->assertInstanceOf(AnswerExpectations::class, $result);
    }

    private function make(string $content): AnswerExpectations
    {
        return new AnswerExpectations(
            result: new ProviderResult($content),
            provider: new NullProvider(),
        );
    }
}
