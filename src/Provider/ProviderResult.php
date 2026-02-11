<?php

declare(strict_types=1);

namespace ProbeLLM\Provider;

use ProbeLLM\DTO\ToolCall;

final class ProviderResult
{
    /**
     * @param string               $content   Assistant text content (may be empty when tool calls present).
     * @param list<ToolCall>       $toolCalls Parsed tool calls returned by the model.
     * @param array<string, mixed> $meta      Arbitrary metadata (model, usage, etc.).
     */
    public function __construct(
        private readonly string $content,
        private readonly array $toolCalls = [],
        private readonly array $meta = [],
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
