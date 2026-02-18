<?php

declare(strict_types=1);

namespace ProbeLLM\Cassette;

use JsonException;
use ProbeLLM\DTO\ElevenLabs\EvaluationCriterion;

final class ElevenLabsHasher
{
    /**
     * Build a deterministic cache key for an ElevenLabs simulation.
     *
     * @param array<string, string> $toolMocks tool_name => mock response
     * @param list<EvaluationCriterion> $evaluationCriteria
     * @param array<string, string|int|float|bool> $dynamicVariables
     * @throws JsonException
     */
    public static function make(
        string $agentId,
        string $userPrompt,
        string $firstMessage,
        array $toolMocks,
        array $evaluationCriteria,
        int $turnsLimit,
        string $testName,
        array $dynamicVariables = [],
    ): string {
        return Hasher::hash([
            'agentId' => $agentId,
            'userPrompt' => $userPrompt,
            'firstMessage' => $firstMessage,
            'toolMocks' => $toolMocks,
            'evaluationCriteria' => array_map(
                static fn(EvaluationCriterion $c): array => $c->toArray(),
                $evaluationCriteria,
            ),
            'turnsLimit' => $turnsLimit,
            'testName' => $testName,
            'dynamicVariables' => $dynamicVariables,
        ]);
    }
}
