<?php

declare(strict_types=1);

namespace ProbeLLM\DSL\Concerns;

use PHPUnit\Framework\Assert;

/**
 * Token usage / cost assertions for LLM responses.
 */
trait UsageAssertions
{
    /**
     * @return array<string, mixed>
     */
    abstract private function getResultMeta(): array;

    /**
     * Assert the total token count does not exceed a maximum.
     *
     * Works with both OpenAI (total_tokens) and Anthropic (input_tokens + output_tokens).
     * Silently passes when no usage data is available (cassette/mock).
     */
    public function assertMaxTotalTokens(int $max): self
    {
        $total = $this->totalTokens();

        if ($total === null) {
            return $this;
        }

        Assert::assertLessThanOrEqual(
            $max,
            $total,
            "Total tokens {$total} exceeded limit of {$max}.",
        );

        return $this;
    }

    /**
     * Assert the input/prompt token count does not exceed a maximum.
     */
    public function assertMaxInputTokens(int $max): self
    {
        $input = $this->inputTokens();

        if ($input === null) {
            return $this;
        }

        Assert::assertLessThanOrEqual(
            $max,
            $input,
            "Input tokens {$input} exceeded limit of {$max}.",
        );

        return $this;
    }

    /**
     * Assert the output/completion token count does not exceed a maximum.
     */
    public function assertMaxOutputTokens(int $max): self
    {
        $output = $this->outputTokens();

        if ($output === null) {
            return $this;
        }

        Assert::assertLessThanOrEqual(
            $max,
            $output,
            "Output tokens {$output} exceeded limit of {$max}.",
        );

        return $this;
    }

    /**
     * Get total tokens used, or null if usage data is unavailable.
     */
    public function totalTokens(): ?int
    {
        $usage = $this->getUsageData();

        if ($usage === null) {
            return null;
        }

        // OpenAI provides total_tokens directly.
        if (isset($usage['total_tokens'])) {
            return (int) $usage['total_tokens'];
        }

        // Anthropic: compute from input + output.
        $input = $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0;
        $output = $usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0;

        return (int) ($input + $output);
    }

    /**
     * Get input tokens used, or null if unavailable.
     */
    public function inputTokens(): ?int
    {
        $usage = $this->getUsageData();

        if ($usage === null) {
            return null;
        }

        $val = $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? null;

        return $val !== null ? (int) $val : null;
    }

    /**
     * Get output tokens used, or null if unavailable.
     */
    public function outputTokens(): ?int
    {
        $usage = $this->getUsageData();

        if ($usage === null) {
            return null;
        }

        $val = $usage['completion_tokens'] ?? $usage['output_tokens'] ?? null;

        return $val !== null ? (int) $val : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getUsageData(): ?array
    {
        $usage = $this->getResultMeta()['usage'] ?? null;

        return is_array($usage) ? $usage : null;
    }
}
