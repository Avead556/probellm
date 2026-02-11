<?php

declare(strict_types=1);

namespace ProbeLLM;

use ProbeLLM\Attributes\AgentModel;
use ProbeLLM\Attributes\AgentReplayMode;
use ProbeLLM\Attributes\AgentSystem;
use ProbeLLM\Attributes\AgentSystemFile;
use ProbeLLM\Attributes\AgentTemperature;
use ProbeLLM\Attributes\AgentTools;
use ProbeLLM\Attributes\JudgeModel;
use ProbeLLM\Attributes\JudgeTemperature;
use ProbeLLM\Cassette\CassetteStore;
use ProbeLLM\DSL\DialogScenario;
use ProbeLLM\Exception\ConfigurationException;
use ProbeLLM\Provider\LLMProvider;
use ProbeLLM\Provider\NullProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

abstract class AgentTestCase extends TestCase
{
    private ?CassetteStore $cassetteStore = null;

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
     * Override to change cassette directory.
     */
    protected function cassettesDirectory(): ?string
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
        $model = $this->resolveModel($classRef, $methodRef);
        $temperature = $this->resolveTemperature($classRef, $methodRef);
        $toolClasses = $this->resolveTools($classRef, $methodRef);
        $replayMode = $this->resolveReplayMode($classRef, $methodRef);
        $judgeModel = $this->resolveJudgeModel($classRef, $methodRef);
        $judgeTemperature = $this->resolveJudgeTemperature($classRef, $methodRef);

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

    private function resolveModel(ReflectionClass $classRef, ReflectionMethod $methodRef): string
    {
        $methodAttrs = $methodRef->getAttributes(AgentModel::class);
        if ($methodAttrs !== []) {
            return end($methodAttrs)->newInstance()->model;
        }

        $classAttrs = $classRef->getAttributes(AgentModel::class);
        if ($classAttrs !== []) {
            return end($classAttrs)->newInstance()->model;
        }

        return 'gpt-4o';
    }

    private function resolveTemperature(ReflectionClass $classRef, ReflectionMethod $methodRef): float
    {
        $methodAttrs = $methodRef->getAttributes(AgentTemperature::class);
        if ($methodAttrs !== []) {
            return end($methodAttrs)->newInstance()->value;
        }

        $classAttrs = $classRef->getAttributes(AgentTemperature::class);
        if ($classAttrs !== []) {
            return end($classAttrs)->newInstance()->value;
        }

        return 0.7;
    }

    /**
     * @return list<class-string>
     */
    private function resolveTools(ReflectionClass $classRef, ReflectionMethod $methodRef): array
    {
        $methodAttrs = $methodRef->getAttributes(AgentTools::class);
        if ($methodAttrs !== []) {
            return $methodAttrs[0]->newInstance()->toolClasses;
        }

        $classAttrs = $classRef->getAttributes(AgentTools::class);
        if ($classAttrs !== []) {
            return $classAttrs[0]->newInstance()->toolClasses;
        }

        return [];
    }

    private function resolveJudgeModel(ReflectionClass $classRef, ReflectionMethod $methodRef): ?string
    {
        $methodAttrs = $methodRef->getAttributes(JudgeModel::class);
        if ($methodAttrs !== []) {
            return end($methodAttrs)->newInstance()->model;
        }

        $classAttrs = $classRef->getAttributes(JudgeModel::class);
        if ($classAttrs !== []) {
            return end($classAttrs)->newInstance()->model;
        }

        return null;
    }

    private function resolveJudgeTemperature(ReflectionClass $classRef, ReflectionMethod $methodRef): ?float
    {
        $methodAttrs = $methodRef->getAttributes(JudgeTemperature::class);
        if ($methodAttrs !== []) {
            return end($methodAttrs)->newInstance()->value;
        }

        $classAttrs = $classRef->getAttributes(JudgeTemperature::class);
        if ($classAttrs !== []) {
            return end($classAttrs)->newInstance()->value;
        }

        return null;
    }

    private function resolveReplayMode(ReflectionClass $classRef, ReflectionMethod $methodRef): bool
    {
        if ($methodRef->getAttributes(AgentReplayMode::class) !== []) {
            return true;
        }

        if ($classRef->getAttributes(AgentReplayMode::class) !== []) {
            return true;
        }

        return false;
    }

    private function getCassetteStore(): CassetteStore
    {
        if ($this->cassetteStore === null) {
            $this->cassetteStore = new CassetteStore($this->cassettesDirectory());
        }

        return $this->cassetteStore;
    }
}
