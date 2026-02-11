<?php

declare(strict_types=1);

namespace ProbeLLM\DTO;

final readonly class CompletionOptions
{
    public function __construct(
        private string $model = 'gpt-4o',
        private float $temperature = 0.7,
    ) {}

    public function getModel(): string
    {
        return $this->model;
    }

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    /**
     * @return array{model: string, temperature: float}
     */
    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'temperature' => $this->temperature,
        ];
    }
}
