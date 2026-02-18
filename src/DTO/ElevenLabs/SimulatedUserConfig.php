<?php

declare(strict_types=1);

namespace ProbeLLM\DTO\ElevenLabs;

final readonly class SimulatedUserConfig
{
    public function __construct(
        private string $prompt,
        private string $firstMessage = '',
    ) {}

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getFirstMessage(): string
    {
        return $this->firstMessage;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'prompt' => [
                'prompt' => $this->prompt,
            ],
        ];

        if ($this->firstMessage !== '') {
            $data['first_message'] = $this->firstMessage;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $prompt = is_array($data['prompt'] ?? null)
            ? ($data['prompt']['prompt'] ?? '')
            : ($data['prompt'] ?? '');

        return new self(
            prompt: $prompt,
            firstMessage: $data['first_message'] ?? '',
        );
    }
}
