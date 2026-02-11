<?php

declare(strict_types=1);

namespace ProbeLLM\DTO\Anthropic;

final readonly class AnthropicContentBlock
{
    /**
     * @param array<string, mixed>|null $input Tool input (only for tool_use blocks).
     */
    public function __construct(
        private string $type,
        private ?string $text = null,
        private ?string $id = null,
        private ?string $name = null,
        private ?array $input = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            text: $data['text'] ?? null,
            id: $data['id'] ?? null,
            name: $data['name'] ?? null,
            input: $data['input'] ?? null,
        );
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isText(): bool
    {
        return $this->type === 'text';
    }

    public function isToolUse(): bool
    {
        return $this->type === 'tool_use';
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getInput(): ?array
    {
        return $this->input;
    }
}
