<?php

declare(strict_types=1);

namespace ProbeLLM\DTO\ElevenLabs;

final readonly class SimulationRequest
{
    /**
     * @param list<EvaluationCriterion>                  $evaluationCriteria
     * @param array<string, string>                      $toolMockConfig      tool_name => mock JSON response
     * @param array<string, string|int|float|bool>       $dynamicVariables    agent {{placeholder}} values
     */
    public function __construct(
        private string $agentId,
        private SimulatedUserConfig $simulatedUserConfig,
        private array $evaluationCriteria = [],
        private array $toolMockConfig = [],
        private int $turnsLimit = 10,
        private array $dynamicVariables = [],
    ) {}

    public function getAgentId(): string
    {
        return $this->agentId;
    }

    public function getSimulatedUserConfig(): SimulatedUserConfig
    {
        return $this->simulatedUserConfig;
    }

    /**
     * @return list<EvaluationCriterion>
     */
    public function getEvaluationCriteria(): array
    {
        return $this->evaluationCriteria;
    }

    /**
     * @return array<string, string>
     */
    public function getToolMockConfig(): array
    {
        return $this->toolMockConfig;
    }

    public function getTurnsLimit(): int
    {
        return $this->turnsLimit;
    }

    /**
     * @return array<string, string|int|float|bool>
     */
    public function getDynamicVariables(): array
    {
        return $this->dynamicVariables;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $simulationSpec = [
            'simulated_user_config' => $this->simulatedUserConfig->toArray(),
        ];

        if ($this->toolMockConfig !== []) {
            $mocks = [];

            foreach ($this->toolMockConfig as $toolName => $mockResponse) {
                $mocks[$toolName] = [
                    'default_return_value' => $mockResponse,
                    'default_is_error' => false,
                ];
            }

            $simulationSpec['tool_mock_config'] = $mocks;
        }

        if ($this->dynamicVariables !== []) {
            $simulationSpec['dynamic_variables'] = $this->dynamicVariables;
        }

        $payload = [
            'simulation_specification' => $simulationSpec,
            'new_turns_limit' => $this->turnsLimit,
        ];

        if ($this->evaluationCriteria !== []) {
            $payload['extra_evaluation_criteria'] = array_map(
                static fn(EvaluationCriterion $c): array => $c->toArray(),
                $this->evaluationCriteria,
            );
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $spec = $data['simulation_specification'] ?? $data;

        $userConfig = SimulatedUserConfig::fromArray($spec['simulated_user_config'] ?? []);

        $criteria = array_map(
            static fn(array $c): EvaluationCriterion => EvaluationCriterion::fromArray($c),
            $data['extra_evaluation_criteria'] ?? $data['evaluation_criteria'] ?? [],
        );

        $toolMocks = [];
        $toolMockConfig = $spec['tool_mock_config'] ?? $data['tool_overrides'] ?? [];

        foreach ($toolMockConfig as $key => $value) {
            if (is_array($value) && isset($value['default_return_value'])) {
                $toolMocks[$key] = $value['default_return_value'];
            } elseif (is_array($value) && isset($value['tool_name'])) {
                $toolMocks[$value['tool_name']] = $value['mock_response'];
            }
        }

        $turnsLimit = $data['new_turns_limit']
            ?? $data['extra_body']['simulation']['max_turns']
            ?? 10;

        $dynamicVariables = $spec['dynamic_variables'] ?? [];

        return new self(
            agentId: $data['agent_id'] ?? ($spec['agent_id'] ?? ''),
            simulatedUserConfig: $userConfig,
            evaluationCriteria: $criteria,
            toolMockConfig: $toolMocks,
            turnsLimit: $turnsLimit,
            dynamicVariables: $dynamicVariables,
        );
    }
}
