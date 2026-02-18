<?php

declare(strict_types=1);

namespace ProbeLLM\DTO\ElevenLabs;

final readonly class TranscriptToolResult
{
    public function __construct(
        private string $toolName,
        private string $resultValue,
        private bool $isError = false,
    ) {}

    public function getToolName(): string
    {
        return $this->toolName;
    }

    public function getResultValue(): string
    {
        return $this->resultValue;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    /**
     * @return array{tool_name: string, result_value: string, is_error: bool}
     */
    public function toArray(): array
    {
        return [
            'tool_name' => $this->toolName,
            'result_value' => $this->resultValue,
            'is_error' => $this->isError,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            toolName: $data['tool_name'] ?? '',
            resultValue: $data['result_value'] ?? '',
            isError: $data['is_error'] ?? false,
        );
    }
}
