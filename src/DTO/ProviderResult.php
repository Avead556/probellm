<?php

declare(strict_types=1);

namespace ProbeLLM\DTO;

final readonly class ProviderResult
{
    /**
     * @param string               $content   Assistant text content (may be empty when tool calls present).
     * @param list<ToolCall>       $toolCalls Parsed tool calls returned by the model.
     * @param array<string, mixed> $meta      Arbitrary metadata (model, usage, etc.).
     */
    public function __construct(
        private string $content,
        private array $toolCalls = [],
        private array $meta = [],
    ) {}

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

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }
}
