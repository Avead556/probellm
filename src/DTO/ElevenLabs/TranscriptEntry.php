<?php

declare(strict_types=1);

namespace ProbeLLM\DTO\ElevenLabs;

final readonly class TranscriptEntry
{
    /**
     * @param list<TranscriptToolCall>   $toolCalls
     * @param list<TranscriptToolResult> $toolResults
     */
    public function __construct(
        private string $role,
        private string $content,
        private array $toolCalls = [],
        private array $toolResults = [],
        private ?string $agentId = null,
        private ?string $workflowNodeId = null,
    ) {}

    public function getRole(): string
    {
        return $this->role;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return list<TranscriptToolCall>
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * @return list<TranscriptToolResult>
     */
    public function getToolResults(): array
    {
        return $this->toolResults;
    }

    public function getAgentId(): ?string
    {
        return $this->agentId;
    }

    public function getWorkflowNodeId(): ?string
    {
        return $this->workflowNodeId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'role' => $this->role,
            'content' => $this->content,
        ];

        if ($this->toolCalls !== []) {
            $data['tool_calls'] = array_map(
                static fn(TranscriptToolCall $tc): array => $tc->toArray(),
                $this->toolCalls,
            );
        }

        if ($this->toolResults !== []) {
            $data['tool_results'] = array_map(
                static fn(TranscriptToolResult $tr): array => $tr->toArray(),
                $this->toolResults,
            );
        }

        if ($this->agentId !== null || $this->workflowNodeId !== null) {
            $data['agent_metadata'] = [
                'agent_id' => $this->agentId,
                'workflow_node_id' => $this->workflowNodeId,
            ];
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $toolCalls = array_map(
            static fn(array $tc): TranscriptToolCall => TranscriptToolCall::fromArray($tc),
            $data['tool_calls'] ?? [],
        );

        $toolResults = array_map(
            static fn(array $tr): TranscriptToolResult => TranscriptToolResult::fromArray($tr),
            $data['tool_results'] ?? [],
        );

        $meta = $data['agent_metadata'] ?? [];

        return new self(
            role: $data['role'] ?? '',
            content: $data['message'] ?? $data['content'] ?? '',
            toolCalls: $toolCalls,
            toolResults: $toolResults,
            agentId: $meta['agent_id'] ?? null,
            workflowNodeId: $meta['workflow_node_id'] ?? null,
        );
    }
}
