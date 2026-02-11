<?php

declare(strict_types=1);

namespace ProbeLLM\DTO\OpenAI;

use ProbeLLM\DTO\CompletionOptions;
use ProbeLLM\DTO\Message;
use ProbeLLM\DTO\ToolDefinition;

final readonly class OpenAIRequest
{
    /**
     * @param list<array<string, mixed>> $messages Already serialized messages.
     * @param list<array<string, mixed>> $tools    Already serialized tools.
     */
    public function __construct(
        private string $model,
        private float $temperature,
        private array $messages,
        private array $tools = [],
    ) {}

    /**
     * @param list<Message>        $messages
     * @param list<ToolDefinition> $tools
     */
    public static function from(CompletionOptions $options, array $messages, array $tools): self
    {
        return new self(
            model: $options->getModel(),
            temperature: $options->getTemperature(),
            messages: array_map(static fn(Message $m): array => $m->toArray(), $messages),
            tools: array_map(static fn(ToolDefinition $def): array => [
                'type' => 'function',
                'function' => $def->toArray(),
            ], $tools),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $this->messages,
            'temperature' => $this->temperature,
        ];

        if ($this->tools !== []) {
            $payload['tools'] = $this->tools;
        }

        return $payload;
    }
}
