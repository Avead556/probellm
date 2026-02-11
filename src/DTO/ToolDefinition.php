<?php

declare(strict_types=1);

namespace ProbeLLM\DTO;

final readonly class ToolDefinition
{
    /**
     * @param array<string, mixed> $parameters JSON Schema
     */
    public function __construct(
        private string $name,
        private string $description,
        private array $parameters,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @return array{name: string, description: string, parameters: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->parameters,
        ];
    }
}
