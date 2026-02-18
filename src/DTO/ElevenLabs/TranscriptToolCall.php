<?php

declare(strict_types=1);

namespace ProbeLLM\DTO\ElevenLabs;

final readonly class TranscriptToolCall
{
    public function __construct(
        private string $toolName,
        private string $paramsAsJson,
        private bool $toolHasBeenCalled,
        private string $type = 'tool_call',
    ) {}

    public function getToolName(): string
    {
        return $this->toolName;
    }

    public function getParamsAsJson(): string
    {
        return $this->paramsAsJson;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        $decoded = json_decode($this->paramsAsJson, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function hasBeenCalled(): bool
    {
        return $this->toolHasBeenCalled;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array{tool_name: string, params_as_json: string, tool_has_been_called: bool, type: string}
     */
    public function toArray(): array
    {
        return [
            'tool_name' => $this->toolName,
            'params_as_json' => $this->paramsAsJson,
            'tool_has_been_called' => $this->toolHasBeenCalled,
            'type' => $this->type,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            toolName: $data['tool_name'] ?? '',
            paramsAsJson: $data['params_as_json'] ?? '{}',
            toolHasBeenCalled: $data['tool_has_been_called'] ?? false,
            type: $data['type'] ?? 'tool_call',
        );
    }
}
