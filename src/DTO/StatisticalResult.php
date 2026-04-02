<?php

declare(strict_types=1);

namespace ProbeLLM\DTO;

/**
 * Holds aggregate results from a statistical (multi-run) assertion.
 */
final readonly class StatisticalResult
{
    /**
     * @param int           $totalRuns   Number of runs executed.
     * @param int           $passed      Number of runs where all assertions passed.
     * @param list<string>  $failures    Failure messages from failed runs.
     * @param list<string>  $contents    Raw assistant content from each run.
     */
    public function __construct(
        private int $totalRuns,
        private int $passed,
        private array $failures,
        private array $contents,
    ) {}

    public function getTotalRuns(): int
    {
        return $this->totalRuns;
    }

    public function getPassed(): int
    {
        return $this->passed;
    }

    public function getFailed(): int
    {
        return $this->totalRuns - $this->passed;
    }

    public function getPassRate(): float
    {
        return $this->totalRuns > 0 ? $this->passed / $this->totalRuns : 0.0;
    }

    /**
     * @return list<string>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    /**
     * @return list<string>
     */
    public function getContents(): array
    {
        return $this->contents;
    }

    /**
     * Whether all runs produced the same content (case-insensitive, trimmed).
     */
    public function isConsistent(): bool
    {
        if ($this->contents === []) {
            return true;
        }

        $normalized = array_map(
            static fn(string $c): string => mb_strtolower(trim($c)),
            $this->contents,
        );

        return count(array_unique($normalized)) === 1;
    }
}
