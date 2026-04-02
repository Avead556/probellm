<?php

declare(strict_types=1);

namespace ProbeLLM\DTO;

/**
 * Aggregate results from an evaluation dataset run.
 */
final readonly class EvalSummary
{
    /**
     * @param int           $total    Total number of rows evaluated.
     * @param int           $passed   Number of rows where assertions passed.
     * @param list<array{index: int, input: string, error: string}> $failures Details of failed rows.
     */
    public function __construct(
        private int $total,
        private int $passed,
        private array $failures,
    ) {}

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getPassed(): int
    {
        return $this->passed;
    }

    public function getFailed(): int
    {
        return $this->total - $this->passed;
    }

    public function getPassRate(): float
    {
        return $this->total > 0 ? $this->passed / $this->total : 0.0;
    }

    /**
     * @return list<array{index: int, input: string, error: string}>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    public function isAllPassed(): bool
    {
        return $this->passed === $this->total;
    }

    /**
     * Format a human-readable summary.
     */
    public function toString(): string
    {
        $lines = [
            sprintf(
                'Eval: %d/%d passed (%.0f%%)',
                $this->passed,
                $this->total,
                $this->getPassRate() * 100,
            ),
        ];

        foreach ($this->failures as $f) {
            $lines[] = sprintf(
                '  FAIL [%d] %s: %s',
                $f['index'],
                mb_substr($f['input'], 0, 60),
                mb_substr($f['error'], 0, 120),
            );
        }

        return implode("\n", $lines);
    }
}
