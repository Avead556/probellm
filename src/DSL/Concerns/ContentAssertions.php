<?php

declare(strict_types=1);

namespace ProbeLLM\DSL\Concerns;

use PHPUnit\Framework\Assert;

/**
 * Content-based assertions for LLM responses.
 */
trait ContentAssertions
{
    abstract private function getContent(): string;

    /**
     * Assert the response contains a substring.
     */
    public function assertContains(string $needle): self
    {
        Assert::assertStringContainsString(
            $needle,
            $this->getContent(),
            "Expected response to contain '{$needle}'.",
        );

        return $this;
    }

    /**
     * Assert the response contains a substring (case-insensitive).
     */
    public function assertContainsIgnoringCase(string $needle): self
    {
        Assert::assertStringContainsStringIgnoringCase(
            $needle,
            $this->getContent(),
            "Expected response to contain '{$needle}' (case-insensitive).",
        );

        return $this;
    }

    /**
     * Assert the response does NOT contain a substring.
     */
    public function assertNotContains(string $needle): self
    {
        Assert::assertStringNotContainsString(
            $needle,
            $this->getContent(),
            "Expected response NOT to contain '{$needle}'.",
        );

        return $this;
    }

    /**
     * Assert the response matches a regular expression.
     */
    public function assertMatchesRegex(string $pattern): self
    {
        Assert::assertMatchesRegularExpression(
            $pattern,
            $this->getContent(),
            "Response does not match pattern '{$pattern}'.",
        );

        return $this;
    }

    /**
     * Assert the response starts with a prefix.
     *
     * @param non-empty-string $prefix
     */
    public function assertStartsWith(string $prefix): self
    {
        Assert::assertStringStartsWith(
            $prefix,
            $this->getContent(),
            "Expected response to start with '{$prefix}'.",
        );

        return $this;
    }

    /**
     * Assert the response ends with a suffix.
     *
     * @param non-empty-string $suffix
     */
    public function assertEndsWith(string $suffix): self
    {
        Assert::assertStringEndsWith(
            $suffix,
            $this->getContent(),
            "Expected response to end with '{$suffix}'.",
        );

        return $this;
    }

    /**
     * Assert the response word count is within a range.
     */
    public function assertWordCountBetween(int $min, int $max): self
    {
        $words = preg_split('/\s+/', trim($this->getContent()), -1, PREG_SPLIT_NO_EMPTY);
        $count = $words === false ? 0 : count($words);

        Assert::assertGreaterThanOrEqual(
            $min,
            $count,
            "Response has {$count} words, expected at least {$min}.",
        );

        Assert::assertLessThanOrEqual(
            $max,
            $count,
            "Response has {$count} words, expected at most {$max}.",
        );

        return $this;
    }

    /**
     * Assert the response content is not empty.
     */
    public function assertNotEmpty(): self
    {
        Assert::assertNotEmpty(
            trim($this->getContent()),
            'Expected response to be non-empty.',
        );

        return $this;
    }

    /**
     * Assert the response content is empty (e.g. tool-only responses).
     */
    public function assertEmpty(): self
    {
        Assert::assertEmpty(
            trim($this->getContent()),
            'Expected response to be empty, got: ' . mb_substr($this->getContent(), 0, 200),
        );

        return $this;
    }
}
