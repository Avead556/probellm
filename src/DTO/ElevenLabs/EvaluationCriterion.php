<?php

declare(strict_types=1);

namespace ProbeLLM\DTO\ElevenLabs;

final readonly class EvaluationCriterion
{
    public function __construct(
        private string $id,
        private string $criteria,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getCriteria(): string
    {
        return $this->criteria;
    }

    /**
     * @return array{id: string, name: string, type: string, conversation_goal_prompt: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->id,
            'type' => 'prompt',
            'conversation_goal_prompt' => $this->criteria,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            criteria: $data['conversation_goal_prompt'] ?? $data['criteria'] ?? '',
        );
    }
}
