<?php

declare(strict_types=1);

namespace ProbeLLM\DSL\Concerns;

use PHPUnit\Framework\Assert;

/**
 * Latency / response time assertions for LLM responses.
 */
trait LatencyAssertions
{
    /**
     * @return array<string, mixed>
     */
    abstract private function getResultMeta(): array;

    /**
     * Assert that the LLM response was received within the given time limit.
     *
     * Only meaningful for live API calls — cassette-loaded results have no timing data
     * and this assertion will silently pass.
     *
     * @param int $maxMs Maximum allowed response time in milliseconds.
     */
    public function assertResponseTime(int $maxMs): self
    {
        $elapsed = $this->getResultMeta()['elapsed_ms'] ?? null;

        if ($elapsed === null) {
            return $this;
        }

        Assert::assertLessThanOrEqual(
            $maxMs,
            $elapsed,
            sprintf(
                'Response time %dms exceeded the limit of %dms.',
                $elapsed,
                $maxMs,
            ),
        );

        return $this;
    }

    /**
     * Assert that the LLM response took at least the given time.
     *
     * Useful for sanity checks or detecting suspiciously fast responses.
     *
     * @param int $minMs Minimum expected response time in milliseconds.
     */
    public function assertMinResponseTime(int $minMs): self
    {
        $elapsed = $this->getResultMeta()['elapsed_ms'] ?? null;

        if ($elapsed === null) {
            return $this;
        }

        Assert::assertGreaterThanOrEqual(
            $minMs,
            $elapsed,
            sprintf(
                'Response time %dms is below the minimum of %dms.',
                $elapsed,
                $minMs,
            ),
        );

        return $this;
    }

    /**
     * Get the response time in milliseconds, or null if not available.
     */
    public function responseTimeMs(): ?int
    {
        $elapsed = $this->getResultMeta()['elapsed_ms'] ?? null;

        return is_int($elapsed) ? $elapsed : null;
    }
}
