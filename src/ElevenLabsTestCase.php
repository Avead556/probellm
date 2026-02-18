<?php

declare(strict_types=1);

namespace ProbeLLM;

use PHPUnit\Framework\TestCase;
use ProbeLLM\Attributes\ElevenLabsAgentId;
use ProbeLLM\Attributes\ElevenLabsTurnsLimit;
use ProbeLLM\Attributes\JudgeModel;
use ProbeLLM\Concerns\ResolvesAttributes;
use ProbeLLM\DSL\ElevenLabsScenario;
use ProbeLLM\Provider\ElevenLabsConvaiProvider;
use ProbeLLM\Provider\ElevenLabsProvider;
use ProbeLLM\Provider\LLMProvider;
use ProbeLLM\Provider\OpenAICompatibleProvider;
use ReflectionClass;

abstract class ElevenLabsTestCase extends TestCase
{
    use ResolvesAttributes;

    /**
     * Override to provide the ElevenLabs provider.
     */
    protected function resolveElevenLabsProvider(): ElevenLabsConvaiProvider
    {
        $apiKey = getenv('ELEVENLABS_API_KEY') ?: '';

        return new ElevenLabsProvider($apiKey);
    }

    /**
     * Override to provide an LLM provider for judge assertions.
     */
    protected function resolveJudgeProvider(): ?LLMProvider
    {
        $apiKey = getenv('LLM_API_KEY') ?: '';

        if ($apiKey === '') {
            return null;
        }

        $baseUrl = getenv('LLM_BASE_URL') ?: 'https://api.openai.com/v1';

        return new OpenAICompatibleProvider($apiKey, $baseUrl);
    }

    /**
     * Create a new ElevenLabs scenario pre-configured with attributes.
     */
    protected function elevenLabs(): ElevenLabsScenario
    {
        $classRef = new ReflectionClass($this);
        $methodRef = $classRef->getMethod($this->name());

        $agentId = $this->resolveAttribute($classRef, $methodRef, ElevenLabsAgentId::class, 'agentId', '');
        $turnsLimit = $this->resolveAttribute($classRef, $methodRef, ElevenLabsTurnsLimit::class, 'limit', 10);
        $replayMode = $this->resolveReplayMode($classRef, $methodRef);
        $judgeModel = $this->resolveAttribute($classRef, $methodRef, JudgeModel::class, 'model', null);

        $testName = static::class . '::' . $this->name();

        $scenario = new ElevenLabsScenario(
            $this->resolveElevenLabsProvider(),
            $this->getCassetteStore(),
        );

        $scenario
            ->withAgentId($agentId)
            ->withTurnsLimit($turnsLimit)
            ->withReplayMode($replayMode)
            ->withTestName($testName);

        $judgeProvider = $this->resolveJudgeProvider();

        if ($judgeProvider !== null) {
            $scenario->withJudgeProvider($judgeProvider, $judgeModel);
        }

        return $scenario;
    }
}
