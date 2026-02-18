<?php

declare(strict_types=1);

namespace ProbeLLM;

use PHPUnit\Framework\TestCase;
use ProbeLLM\Attributes\AgentModel;
use ProbeLLM\Attributes\AgentSystem;
use ProbeLLM\Attributes\AgentSystemFile;
use ProbeLLM\Attributes\AgentTemperature;
use ProbeLLM\Attributes\AgentTools;
use ProbeLLM\Attributes\JudgeModel;
use ProbeLLM\Attributes\JudgeTemperature;
use ProbeLLM\Cassette\CassetteStore;
use ProbeLLM\Concerns\ResolvesAttributes;
use ProbeLLM\DSL\DialogScenario;
use ProbeLLM\Exception\ConfigurationException;
use ProbeLLM\Provider\LLMProvider;
use ProbeLLM\Provider\NullProvider;
use ReflectionClass;
use ReflectionMethod;

abstract class AgentTestCase extends TestCase
{
    use ResolvesAttributes;

    /**
     * Override to provide a real LLM provider for live tests.
     */
    protected function resolveProvider(): LLMProvider
    {
        return new NullProvider();
    }

    /**
     * Override to use a different provider for LLM-as-judge calls.
     * Returns null to reuse the main provider.
     */
    protected function resolveJudgeProvider(): ?LLMProvider
    {
        return null;
    }

    /**
     * Create a new dialog scenario pre-configured with attributes from the current test.
     */
    protected function dialog(): DialogScenario
    {
        $classRef = new ReflectionClass($this);
        $methodRef = $classRef->getMethod($this->name());

        $systemPrompt = $this->resolveSystemPrompt($classRef, $methodRef);
        $model = $this->resolveAttribute($classRef, $methodRef, AgentModel::class, 'model', 'gpt-4o');
        $temperature = $this->resolveAttribute($classRef, $methodRef, AgentTemperature::class, 'value', 0.7);
        $toolClasses = $this->resolveAttribute($classRef, $methodRef, AgentTools::class, 'toolClasses', []);
        $replayMode = $this->resolveReplayMode($classRef, $methodRef);
        $judgeModel = $this->resolveAttribute($classRef, $methodRef, JudgeModel::class, 'model');
        $judgeTemperature = $this->resolveAttribute($classRef, $methodRef, JudgeTemperature::class, 'value');

        $testName = static::class . '::' . $this->name();

        $scenario = new DialogScenario(
            $this->resolveProvider(),
            $this->getCassetteStore(),
        );

        $scenario
            ->withSystem($systemPrompt)
            ->withModel($model)
            ->withTemperature($temperature)
            ->withTools($toolClasses)
            ->withReplayMode($replayMode)
            ->withTestName($testName)
            ->withJudgeModel($judgeModel)
            ->withJudgeTemperature($judgeTemperature);

        $judgeProvider = $this->resolveJudgeProvider();
        if ($judgeProvider !== null) {
            $scenario->withJudgeProvider($judgeProvider);
        }

        return $scenario;
    }

    private function resolveSystemPrompt(ReflectionClass $classRef, ReflectionMethod $methodRef): string
    {
        $parts = [];

        foreach ($classRef->getAttributes(AgentSystemFile::class) as $attr) {
            $parts[] = $this->readSystemFile($attr->newInstance()->path);
        }

        foreach ($classRef->getAttributes(AgentSystem::class) as $attr) {
            $parts[] = $attr->newInstance()->prompt;
        }

        foreach ($methodRef->getAttributes(AgentSystemFile::class) as $attr) {
            $parts[] = $this->readSystemFile($attr->newInstance()->path);
        }

        foreach ($methodRef->getAttributes(AgentSystem::class) as $attr) {
            $parts[] = $attr->newInstance()->prompt;
        }

        return implode("\n", $parts);
    }

    private function readSystemFile(string $path): string
    {
        $base = CassetteStore::resolveBasePath();
        $absolute = $base . '/' . ltrim($path, '/');

        if (! file_exists($absolute)) {
            throw new ConfigurationException(
                "AgentSystemFile: file not found at '{$absolute}' (declared path: '{$path}').",
            );
        }

        $content = file_get_contents($absolute);

        if ($content === false) {
            throw new ConfigurationException("AgentSystemFile: failed to read '{$absolute}'.");
        }

        return $content;
    }
}
