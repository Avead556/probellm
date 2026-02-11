<?php

declare(strict_types=1);

namespace ProbeLLM\Cassette;

use ProbeLLM\DTO\Message;
use ProbeLLM\DTO\ToolDefinition;

final class Hasher
{
    /**
     * Build a deterministic cache key for a single dialog turn.
     *
     * @param list<Message>         $messages
     * @param list<ToolDefinition>  $tools
     * @param string                $testName    Fully-qualified test method name.
     * @param int                   $turnIndex   Zero-based turn counter inside one test.
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
        $blob = json_encode([
            'systemPrompt' => $systemPrompt,
            'messages' => array_map(static fn(Message $m): array => $m->toArray(), $messages),
            'model' => $model,
            'temperature' => $temperature,
            'tools' => array_map(static fn(ToolDefinition $t): array => $t->toArray(), $tools),
            'testName' => $testName,
            'turnIndex' => $turnIndex,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $blob);
    }
}
