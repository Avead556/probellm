<?php

declare(strict_types=1);

namespace ProbeLLM\DTO;

final readonly class Message
{
    /**
     * @param list<ToolCall>|null $toolCalls
     */
    public function __construct(
        private MessageRole $role,
        private string $content,
        private ?array $toolCalls = null,
        private ?string $toolCallId = null,
        private ?string $name = null,
    ) {}

    public function getRole(): MessageRole
    {
        return $this->role;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return list<ToolCall>|null
     */
    public function getToolCalls(): ?array
    {
        return $this->toolCalls;
    }

    public function getToolCallId(): ?string
    {
        return $this->toolCallId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public static function system(string $content): self
    {
        return new self(role: MessageRole::SYSTEM, content: $content);
    }

    public static function user(string $content): self
    {
        return new self(role: MessageRole::USER, content: $content);
    }

    /**
     * @param list<ToolCall> $toolCalls
     */
    public static function assistant(string $content, array $toolCalls = []): self
    {
        return new self(
            role: MessageRole::ASSISTANT,
            content: $content,
            toolCalls: $toolCalls !== [] ? $toolCalls : null,
        );
    }

    public static function tool(string $toolCallId, string $name, string $content): self
    {
        return new self(
            role: MessageRole::TOOL,
            content: $content,
            toolCallId: $toolCallId,
            name: $name,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->role->isTool()) {
            $arr = ['role' => $this->role->value];

            if ($this->toolCallId !== null) {
                $arr['tool_call_id'] = $this->toolCallId;
            }

            if ($this->name !== null) {
                $arr['name'] = $this->name;
            }

            $arr['content'] = $this->content;

            return $arr;
        }

        $arr = ['role' => $this->role->value, 'content' => $this->content];

        if ($this->toolCalls !== null) {
            $arr['tool_calls'] = array_map(static fn(ToolCall $tc): array => [
                'id' => $tc->getId(),
                'type' => 'function',
                'function' => [
                    'name' => $tc->getName(),
                    'arguments' => json_encode($tc->getArguments(), JSON_THROW_ON_ERROR),
                ],
            ], $this->toolCalls);
        }

        return $arr;
    }
}
