<?php

declare(strict_types=1);

namespace ProbeLLM\Cassette;

use JsonException;
use ProbeLLM\DTO\Message;
use ProbeLLM\DTO\ToolDefinition;

final class Hasher
{
    /**
     * Compute a deterministic SHA256 hash from an arbitrary payload.
     *
     * @param array<string, mixed> $payload
     * @throws JsonException
     */
    public static function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Build a deterministic cache key for a single dialog turn.
     *
     * @param list<Message> $messages
     * @param list<ToolDefinition> $tools
     * @param string $testName Fully-qualified test method name.
     * @param int $turnIndex Zero-based turn counter inside one test.
     * @throws JsonException
     */
    public static function make(
        string $systemPrompt,
        array $messages,
        string $model,
        float $temperature,
        array $tools,
        string $testName,
        int $turnIndex,
    ): string {
        return self::hash([
            'systemPrompt' => $systemPrompt,
            'messages' => array_map(static fn(Message $m): array => $m->toArray(), $messages),
            'model' => $model,
            'temperature' => $temperature,
            'tools' => array_map(static fn(ToolDefinition $t): array => $t->toArray(), $tools),
            'testName' => $testName,
            'turnIndex' => $turnIndex,
        ]);
    }
}
