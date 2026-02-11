<?php

declare(strict_types=1);

namespace ProbeLLM\DTO\Anthropic;

use ProbeLLM\DTO\ToolCall;

final readonly class AnthropicResponse
{
    /**
     * @param list<AnthropicContentBlock> $content
     */
    public function __construct(
        private array $content,
        private string $stopReason,
        private AnthropicUsage $usage,
    ) {}

    /**
     * @param array<string, mixed> $data Raw decoded Anthropic JSON response.
     */
    public static function fromArray(array $data): self
    {
        $blocks = array_map(
            static fn(array $block): AnthropicContentBlock => AnthropicContentBlock::fromArray($block),
            $data['content'] ?? [],
        );

        return new self(
            content: array_values($blocks),
            stopReason: $data['stop_reason'] ?? 'end_turn',
            usage: AnthropicUsage::fromArray($data['usage'] ?? []),
        );
    }

    public function getTextContent(): string
    {
        $parts = [];

        foreach ($this->content as $block) {
            if ($block->isText()) {
                $parts[] = $block->getText();
            }
        }

        return implode('', $parts);
    }

    /**
     * @return list<ToolCall>
     */
    public function getToolCalls(): array
    {
        $toolCalls = [];

        foreach ($this->content as $block) {
            if ($block->isToolUse()) {
                $toolCalls[] = new ToolCall(
                    id: $block->getId(),
                    name: $block->getName(),
                    arguments: $block->getInput() ?? [],
                );
            }
        }

        return $toolCalls;
    }

    /**
     * @return list<AnthropicContentBlock>
     */
    public function getContent(): array
    {
        return $this->content;
    }

    public function getStopReason(): string
    {
        return $this->stopReason;
    }

    public function getUsage(): AnthropicUsage
    {
        return $this->usage;
    }
}
