<?php

declare(strict_types=1);

namespace ProbeLLM\DTO;

use ProbeLLM\Exception\InvalidResponseException;
use ProbeLLM\Provider\ProviderResult;

final readonly class CassetteData
{
    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $meta
     */
    public function __construct(
        private array $request,
        private ProviderResult $result,
        private array $meta,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    public function getResult(): ProviderResult
    {
        return $this->result;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @param array<string, mixed> $data Raw decoded cassette JSON.
     */
    public static function fromArray(array $data, string $path = ''): self
    {
        $response = $data['response'] ?? throw new InvalidResponseException(
            "Cassette {$path} is missing the 'response' key.",
        );

        $toolCalls = array_map(
            static fn(array $tc): ToolCall => ToolCall::fromArray($tc),
            $response['tool_calls'] ?? [],
        );

        $meta = $data['meta'] ?? [];

        return new self(
            request: $data['request'] ?? [],
            result: new ProviderResult(
                content: $response['content'] ?? '',
                toolCalls: $toolCalls,
                meta: $meta,
            ),
            meta: $meta,
        );
    }

    /**
     * @return array{request: array<string, mixed>, response: array<string, mixed>, meta: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'request' => $this->request,
            'response' => [
                'content' => $this->result->getContent(),
                'tool_calls' => array_map(
                    static fn(ToolCall $tc): array => $tc->toArray(),
                    $this->result->getToolCalls(),
                ),
            ],
            'meta' => $this->meta,
        ];
    }
}
