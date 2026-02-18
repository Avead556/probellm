<?php

declare(strict_types=1);

namespace ProbeLLM\DSL;

use PHPUnit\Framework\Assert;
use ProbeLLM\Cassette\CassetteResolver;
use ProbeLLM\Cassette\CassetteStore;
use ProbeLLM\Cassette\ElevenLabsCassetteAdapter;
use ProbeLLM\Cassette\ElevenLabsHasher;
use ProbeLLM\DTO\ElevenLabs\EvaluationCriterion;
use ProbeLLM\DTO\ElevenLabs\SimulatedUserConfig;
use ProbeLLM\DTO\ElevenLabs\SimulationRequest;
use ProbeLLM\DTO\ProviderResult;
use ProbeLLM\Enum\CassetteSource;
use ProbeLLM\Exception\ProviderException;
use ProbeLLM\Provider\ElevenLabsConvaiProvider;
use ProbeLLM\Provider\LLMProvider;

final class ElevenLabsScenario
{
    private string $agentId = '';
    private string $userPrompt = '';
    private string $firstMessage = '';
    private int $turnsLimit = 10;
    private bool $replayMode = false;
    private string $testName = '';

    /** @var array<string, string> */
    private array $toolMocks = [];

    /** @var array<string, string|int|float|bool> */
    private array $dynamicVariables = [];

    private ?LLMProvider $judgeProvider = null;
    private ?string $judgeModel = null;

    /** @var list<EvaluationCriterion> */
    private array $evaluationCriteria = [];

    public function __construct(
        private readonly ElevenLabsConvaiProvider $provider,
        private readonly CassetteStore $cassetteStore,
    ) {}

    public function withAgentId(string $agentId): self
    {
        $this->agentId = $agentId;

        return $this;
    }

    public function withUserPrompt(string $prompt): self
    {
        $this->userPrompt = $prompt;

        return $this;
    }

    public function withFirstMessage(string $message): self
    {
        $this->firstMessage = $message;

        return $this;
    }

    public function withToolMock(string $toolName, array $mockResponse): self
    {
        $this->toolMocks[$toolName] = json_encode($mockResponse, JSON_THROW_ON_ERROR);

        return $this;
    }

    public function withDynamicVariable(string $name, string|int|float|bool $value): self
    {
        $this->dynamicVariables[$name] = $value;

        return $this;
    }

    /**
     * @param array<string, string|int|float|bool> $variables
     */
    public function withDynamicVariables(array $variables): self
    {
        $this->dynamicVariables = array_merge($this->dynamicVariables, $variables);

        return $this;
    }

    public function withJudgeProvider(LLMProvider $provider, ?string $model = null): self
    {
        $this->judgeProvider = $provider;
        $this->judgeModel = $model;

        return $this;
    }

    public function withEvaluation(string $id, string $criteria): self
    {
        $this->evaluationCriteria[] = new EvaluationCriterion($id, $criteria);

        return $this;
    }

    public function withTurnsLimit(int $limit): self
    {
        $this->turnsLimit = $limit;

        return $this;
    }

    public function withReplayMode(bool $replay = true): self
    {
        $this->replayMode = $replay;

        return $this;
    }

    public function withTestName(string $name): self
    {
        $this->testName = $name;

        return $this;
    }

    /**
     * Execute the simulation and run assertions.
     *
     * @param callable(ElevenLabsExpectations): void $assertions
     */
    public function run(callable $assertions): self
    {
        $request = new SimulationRequest(
            agentId: $this->agentId,
            simulatedUserConfig: new SimulatedUserConfig(
                prompt: $this->userPrompt,
                firstMessage: $this->firstMessage,
            ),
            evaluationCriteria: $this->evaluationCriteria,
            toolMockConfig: $this->toolMocks,
            turnsLimit: $this->turnsLimit,
            dynamicVariables: $this->dynamicVariables,
        );

        $cassetteKey = ElevenLabsHasher::make(
            $this->agentId,
            $this->userPrompt,
            $this->firstMessage,
            $this->toolMocks,
            $this->evaluationCriteria,
            $this->turnsLimit,
            $this->testName,
            $this->dynamicVariables,
        );

        $resolver = new CassetteResolver($this->cassetteStore, $this->replayMode);

        try {
            $providerResult = $resolver->resolve(
                $cassetteKey,
                function () use ($request): ProviderResult {
                    $response = $this->provider->simulateConversation($request);

                    return ElevenLabsCassetteAdapter::toProviderResult($response);
                },
                fn(): array => ['request' => $request->toArray()],
                ['provider' => CassetteSource::ELEVENLABS->value, 'agent_id' => $this->agentId],
            );
        } catch (ProviderException $e) {
            Assert::markTestSkipped('ElevenLabs API unavailable: ' . $e->getMessage());
        }

        $response = ElevenLabsCassetteAdapter::toSimulationResponse($providerResult);

        $assertions(new ElevenLabsExpectations(
            response: $response,
            judgeProvider: $this->judgeProvider,
            cassetteResolver: $resolver,
            testName: $this->testName,
            judgeModel: $this->judgeModel,
        ));

        return $this;
    }
}
