<?php

declare(strict_types=1);

namespace ProbeLLM;

use Closure;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use ProbeLLM\Attributes\AgentEvalDataset;
use ProbeLLM\Attributes\AgentModel;
use ProbeLLM\Attributes\AgentSystem;
use ProbeLLM\Attributes\AgentSystemFile;
use ProbeLLM\Attributes\AgentTemperature;
use ProbeLLM\Attributes\AgentTools;
use ProbeLLM\Attributes\JudgeModel;
use ProbeLLM\Attributes\JudgeTemperature;
use ProbeLLM\Cassette\CassetteStore;
use ProbeLLM\Concerns\ResolvesAttributes;
use ProbeLLM\Dataset\DatasetLoader;
use ProbeLLM\DSL\DialogScenario;
use ProbeLLM\DTO\DatasetRow;
use ProbeLLM\DTO\EvalSummary;
use ProbeLLM\Exception\ConfigurationException;
use ProbeLLM\Provider\LLMProvider;
use ProbeLLM\Provider\NullProvider;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

abstract class AgentTestCase extends TestCase
{
    use ResolvesAttributes;

    /**
     * Read an environment variable with a consistent lookup order.
     */
    protected static function envVar(string $name, string $default = ''): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name) ?: null;

        return is_string($value) ? $value : $default;
    }

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

    /**
     * Run an evaluation dataset against the agent.
     *
     * Loads the dataset from the #[AgentEvalDataset] attribute or the provided path.
     * Each row creates a fresh dialog, sends the input, and runs the callback's assertions.
     *
     * @param Closure(DatasetRow, DialogScenario): void $callback Receives the row DTO and a pre-configured dialog.
     * @param string|null $path Dataset file path. When null, resolved from #[AgentEvalDataset] attribute.
     * @param float $requiredPassRate Minimum fraction of rows that must pass (0.0–1.0). Default 1.0 (all must pass).
     */
    protected function evalDataset(
        Closure $callback,
        ?string $path = null,
        float $requiredPassRate = 1.0,
    ): EvalSummary {
        $datasetPath = $path ?? $this->resolveDatasetPath();
        $rows = DatasetLoader::load($datasetPath);

        if ($rows === []) {
            throw new ConfigurationException("Dataset is empty: '{$datasetPath}'.");
        }

        $passed = 0;
        $failures = [];

        foreach ($rows as $row) {
            try {
                $callback($row, $this->dialog());
                $passed++;
            } catch (Throwable $e) {
                $input = '';

                try {
                    $input = $row->getInput();
                } catch (Throwable) {
                    $input = json_encode($row->toArray(), JSON_UNESCAPED_UNICODE) ?: '(unknown)';
                }

                $failures[] = [
                    'index' => $row->getIndex(),
                    'input' => $input,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $summary = new EvalSummary(count($rows), $passed, $failures);

        $actualRate = $summary->getPassRate();

        Assert::assertGreaterThanOrEqual(
            $requiredPassRate,
            $actualRate,
            sprintf(
                "Eval dataset failed: %d/%d rows passed (%.0f%%), required %.0f%%.\n\n%s",
                $passed,
                count($rows),
                $actualRate * 100,
                $requiredPassRate * 100,
                $summary->toString(),
            ),
        );

        return $summary;
    }

    private function resolveDatasetPath(): string
    {
        $classRef = new ReflectionClass($this);
        $methodRef = $classRef->getMethod($this->name());

        $attrs = $methodRef->getAttributes(AgentEvalDataset::class);

        if ($attrs !== []) {
            return $attrs[0]->newInstance()->path;
        }

        $classAttrs = $classRef->getAttributes(AgentEvalDataset::class);

        if ($classAttrs !== []) {
            return $classAttrs[0]->newInstance()->path;
        }

        throw new ConfigurationException(
            'No dataset path provided. Use #[AgentEvalDataset("path")] attribute or pass $path to evalDataset().',
        );
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $classRef
     */
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
