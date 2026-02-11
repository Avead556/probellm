<?php

declare(strict_types=1);

namespace ProbeLLM\DTO\OpenAI;

use ProbeLLM\Exception\InvalidResponseException;

final readonly class OpenAIResponse
{
    /**
     * @param list<OpenAIChoice> $choices
     */
    public function __construct(
        private array $choices,
        private OpenAIUsage $usage,
    ) {}

    /**
     * @param array<string, mixed> $data Raw decoded OpenAI JSON response.
     */
    public static function fromArray(array $data): self
    {
        $rawChoices = $data['choices'] ?? throw new InvalidResponseException('No choices in OpenAI response.');

        $choices = array_map(
            static fn(array $choice): OpenAIChoice => OpenAIChoice::fromArray($choice),
            $rawChoices,
        );

        return new self(
            choices: array_values($choices),
            usage: OpenAIUsage::fromArray($data['usage'] ?? []),
        );
    }

    public function getFirstChoice(): OpenAIChoice
    {
        return $this->choices[0] ?? throw new InvalidResponseException('No choices in OpenAI response.');
    }

    /**
     * @return list<OpenAIChoice>
     */
    public function getChoices(): array
    {
        return $this->choices;
    }

    public function getUsage(): OpenAIUsage
    {
        return $this->usage;
    }
}
