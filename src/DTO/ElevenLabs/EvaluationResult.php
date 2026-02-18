<?php

declare(strict_types=1);

namespace ProbeLLM\DTO\ElevenLabs;

final readonly class EvaluationResult
{
    public function __construct(
        private string $criteriaId,
        private bool $pass,
        private string $reason = '',
    ) {}

    public function getCriteriaId(): string
    {
        return $this->criteriaId;
    }

    public function isPassed(): bool
    {
        return $this->pass;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * @return array{criteria_id: string, pass: bool, reason: string}
     */
    public function toArray(): array
    {
        return [
            'criteria_id' => $this->criteriaId,
            'pass' => $this->pass,
            'reason' => $this->reason,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        // API returns "result": "success"/"failure", cassettes store "pass": true/false
        if (isset($data['result'])) {
            $pass = $data['result'] === 'success';
        } else {
            $pass = $data['pass'] ?? false;
        }

        return new self(
            criteriaId: $data['criteria_id'] ?? '',
            pass: $pass,
            reason: $data['rationale'] ?? $data['reason'] ?? '',
        );
    }
}
