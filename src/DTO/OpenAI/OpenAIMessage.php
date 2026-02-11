<?php

declare(strict_types=1);

namespace ProbeLLM\DTO\OpenAI;

use ProbeLLM\DTO\ToolCall;
use JsonException;

final readonly class OpenAIMessage
{
    /**
     * @param list<ToolCall> $toolCalls
     */
    public function __construct(
        private string $content,
        private array $toolCalls,
    ) {}

    /**
     * @param array<string, mixed> $data Raw message object from OpenAI response.
     *
     * @throws JsonException
     */
    public static function fromArray(array $data): self
    {
        $toolCalls = [];

        foreach ($data['tool_calls'] ?? [] as $tc) {
            $toolCalls[] = new ToolCall(
                id: $tc['id'],
                name: $tc['function']['name'],
                arguments: json_decode($tc['function']['arguments'], true, 512, JSON_THROW_ON_ERROR),
            );
        }

        return new self(
            content: $data['content'] ?? '',
            toolCalls: $toolCalls,
        );
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return list<ToolCall>
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }
}
