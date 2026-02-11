<?php

declare(strict_types=1);

namespace ProbeLLM\DTO;

use ProbeLLM\Exception\InvalidResponseException;

final readonly class JudgeVerdict
{
    public function __construct(
        private bool $pass,
        private string $reason,
    ) {}

    public function isPassed(): bool
    {
        return $this->pass;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded) || ! array_key_exists('pass', $decoded)) {
            throw new InvalidResponseException(
                "Judge returned invalid response (expected JSON with 'pass' key). "
                . 'Raw judge output: ' . mb_substr($json, 0, 500),
            );
        }

        return new self(
            pass: (bool) $decoded['pass'],
            reason: $decoded['reason'] ?? 'no reason provided',
        );
    }
}
