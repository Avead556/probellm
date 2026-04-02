<?php

declare(strict_types=1);

namespace ProbeLLM\DSL;

use Closure;
use PHPUnit\Framework\Assert;
use ProbeLLM\Cassette\CassetteResolver;
use ProbeLLM\Cassette\CassetteStore;
use ProbeLLM\Cassette\Hasher;
use ProbeLLM\DTO\Attachment;
use ProbeLLM\DTO\CompletionOptions;
use ProbeLLM\DTO\Message;
use ProbeLLM\DTO\ProviderResult;
use ProbeLLM\DTO\StatisticalResult;
use ProbeLLM\DTO\ToolDefinition;
use ProbeLLM\Exception\ToolResolutionException;
use ProbeLLM\Provider\LLMProvider;
use ProbeLLM\Tools\ToolContract;
use Throwable;

final class DialogScenario
{
    /** @var list<Message> */
    private array $messages = [];

    private string $systemPrompt = '';
    private string $model = 'gpt-4o';
    private float $temperature = 0.7;

    /** @var list<class-string<ToolContract>> */
    private array $toolClasses = [];

    private bool $replayMode = false;

    private string $testName = '';
    private int $turnIndex = 0;
    private ?ProviderResult $lastResult = null;

    private ?LLMProvider $judgeProvider = null;
    private ?string $judgeModel = null;
    private ?float $judgeTemperature = null;

    /** @var list<ProviderResult> */
    private array $mockResults = [];

    private ?CassetteResolver $cassetteResolver = null;

    public function __construct(
        private LLMProvider $provider,
        private CassetteStore $cassetteStore,
    ) {}

    public function withSystem(string $prompt): self
    {
        $this->systemPrompt = $prompt;

        return $this;
    }

    public function withModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function withTemperature(float $temperature): self
    {
        $this->temperature = $temperature;

        return $this;
    }

    /**
     * @param list<class-string<ToolContract>> $toolClasses
     */
    public function withTools(array $toolClasses): self
    {
        $this->toolClasses = $toolClasses;

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

    public function withJudgeProvider(LLMProvider $provider): self
    {
        $this->judgeProvider = $provider;

        return $this;
    }

    public function withJudgeModel(?string $model): self
    {
        $this->judgeModel = $model;

        return $this;
    }

    public function withJudgeTemperature(?float $temperature): self
    {
        $this->judgeTemperature = $temperature;

        return $this;
    }

    /**
     * Provide a pre-built result for the next answer() call.
     */
    public function withMockResult(ProviderResult $result): self
    {
        $this->mockResults[] = $result;

        return $this;
    }

    /**
     * Alias for withSystem() — shorthand for inline DSL usage.
     */
    public function system(string $prompt): self
    {
        return $this->withSystem($prompt);
    }

    /**
     * Append a user message.
     */
    public function user(string $text): self
    {
        $this->messages[] = Message::user($text);

        return $this;
    }

    /**
     * Append a user message with file attachments.
     * Strings starting with http(s):// are treated as URLs, otherwise as local file paths.
     *
     * @param list<Attachment|string> $attachments
     */
    public function userWithAttachments(string $text, array $attachments): self
    {
        $resolved = array_map(
            static fn(Attachment|string $a): Attachment => match (true) {
                $a instanceof Attachment => $a,
                str_starts_with($a, 'http://'), str_starts_with($a, 'https://') => Attachment::fromUrl($a),
                default => Attachment::fromFile($a),
            },
            $attachments,
        );

        $this->messages[] = Message::userWithAttachments($text, $resolved);

        return $this;
    }

    /**
     * Append a tool result message.
     *
     * When $toolCallId is null, it is auto-resolved from the last assistant response's
     * tool call matching $toolName.
     *
     * @param array<string, mixed> $payload
     */
    public function toolResult(string $toolName, array $payload, ?string $toolCallId = null): self
    {
        if ($toolCallId === null) {
            $toolCallId = $this->resolveToolCallId($toolName);
        }

        $this->messages[] = Message::tool(
            $toolCallId,
            $toolName,
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        return $this;
    }

    /**
     * Find tool_call_id from the last assistant response by tool name.
     */
    private function resolveToolCallId(string $toolName): string
    {
        if ($this->lastResult === null) {
            throw new ToolResolutionException(
                "Cannot auto-resolve tool_call_id for '{$toolName}': no previous answer() call.",
            );
        }

        foreach ($this->lastResult->getToolCalls() as $tc) {
            if ($tc->getName() === $toolName) {
                return $tc->getId();
            }
        }

        throw new ToolResolutionException(
            "Cannot auto-resolve tool_call_id: tool '{$toolName}' was not called in the last answer.",
        );
    }

    /**
     * Execute one turn and run assertions.
     *
     * @param Closure(AnswerExpectations): void $assertions
     */
    public function answer(Closure $assertions): self
    {
        $result = $this->executeTurn();

        $this->messages[] = Message::assistant($result->getContent(), $result->getToolCalls());
        $this->lastResult = $result;

        $assertions($this->buildExpectations($result));

        $this->turnIndex++;

        return $this;
    }

    /**
     * Run an agentic loop: send message, get response, execute tools, feed results back, repeat.
     *
     * Automatically handles the tool call → tool result → next turn cycle until
     * the model stops calling tools or maxIterations is reached.
     *
     * @param int $maxIterations Safety limit for the loop.
     * @param array<string, Closure(array<string, mixed>): mixed> $toolExecutors Map of tool name → executor function.
     * @param Closure(AnswerExpectations): void $assertions Assertions to run on the final answer.
     */
    public function agentLoop(
        int $maxIterations,
        array $toolExecutors,
        Closure $assertions,
    ): self {
        for ($i = 0; $i < $maxIterations; $i++) {
            $result = $this->executeTurn();
            $this->messages[] = Message::assistant($result->getContent(), $result->getToolCalls());
            $this->lastResult = $result;
            $this->turnIndex++;

            $toolCalls = $result->getToolCalls();

            if ($toolCalls === []) {
                // No tool calls — this is the final answer.
                $assertions($this->buildExpectations($result));

                return $this;
            }

            // Execute each tool call and feed results back.
            foreach ($toolCalls as $tc) {
                $name = $tc->getName();

                if (! isset($toolExecutors[$name])) {
                    Assert::fail(
                        "Agent loop: tool '{$name}' was called but no executor is registered. "
                        . 'Registered executors: ' . implode(', ', array_keys($toolExecutors)) . '.',
                    );
                }

                $output = $toolExecutors[$name]($tc->getArguments());
                $json = is_string($output) ? $output : json_encode($output, JSON_THROW_ON_ERROR);

                $this->messages[] = Message::tool($tc->getId(), $name, $json);
            }
        }

        // Max iterations reached — run assertions on the last result.
        if ($this->lastResult !== null) {
            $assertions($this->buildExpectations($this->lastResult));
        }

        return $this;
    }

    /**
     * Execute one turn N times and assert that at least passRate fraction of runs pass.
     *
     * Each run gets a unique cassette key (via runIndex) so different responses are recorded.
     * After completion, the conversation continues with the first result (for multi-turn chains).
     *
     * @param int   $runs       Number of times to execute and test this turn.
     * @param float $passRate   Required fraction of passing runs (0.0–1.0).
     * @param Closure(AnswerExpectations): void $assertions  Assertions to run on each result.
     */
    public function answerSampled(int $runs, float $passRate, Closure $assertions): self
    {
        $stat = $this->collectStatisticalRuns($runs, $assertions);

        Assert::assertGreaterThanOrEqual(
            $passRate,
            $stat->getPassRate(),
            sprintf(
                "Statistical assertion failed: %d/%d runs passed (%.0f%%), required %.0f%%.\n\nFailures:\n%s",
                $stat->getPassed(),
                $runs,
                $stat->getPassRate() * 100,
                $passRate * 100,
                implode("\n", $stat->getFailures()),
            ),
        );

        return $this;
    }

    /**
     * Execute one turn N times and assert all runs produce the same response.
     *
     * Optionally runs assertions on each result too.
     *
     * @param int $runs Number of times to execute this turn.
     * @param (Closure(AnswerExpectations): void)|null $assertions Optional assertions per run.
     */
    public function answerConsistently(int $runs, ?Closure $assertions = null): self
    {
        $stat = $this->collectStatisticalRuns($runs, $assertions);

        $normalized = array_map(
            static fn(string $c): string => mb_strtolower(trim($c)),
            $stat->getContents(),
        );

        $unique = array_unique($normalized);

        Assert::assertCount(
            1,
            $unique,
            sprintf(
                "Consistency check failed: %d unique responses out of %d runs.\nResponses:\n%s",
                count($unique),
                $runs,
                implode("\n---\n", array_map(
                    static fn(string $c, int $i): string => "Run {$i}: " . mb_substr($c, 0, 200),
                    $stat->getContents(),
                    array_keys($stat->getContents()),
                )),
            ),
        );

        return $this;
    }

    /**
     * Execute one turn N times and return the statistical result without asserting.
     *
     * Useful for custom analysis or reporting.
     *
     * @param int $runs Number of times to execute this turn.
     * @param Closure(AnswerExpectations): void $assertions Assertions to run on each result.
     */
    public function sampleAnswer(int $runs, Closure $assertions): StatisticalResult
    {
        return $this->collectStatisticalRuns($runs, $assertions);
    }

    /**
     * Execute one turn N times, collect results and continue conversation with the first result.
     *
     * @param Closure(AnswerExpectations): void|null $assertions Assertions to run on each result.
     */
    private function collectStatisticalRuns(int $runs, ?Closure $assertions): StatisticalResult
    {
        $passed = 0;
        $failures = [];
        $contents = [];
        $firstResult = null;

        for ($run = 0; $run < $runs; $run++) {
            $result = $this->executeTurn($run);
            $contents[] = $result->getContent();

            if ($firstResult === null) {
                $firstResult = $result;
            }

            if ($assertions !== null) {
                try {
                    $assertions($this->buildExpectations($result));
                    $passed++;
                } catch (Throwable $e) {
                    $failures[] = "Run {$run}: {$e->getMessage()}";
                }
            } else {
                $passed++;
            }
        }

        // Continue conversation with the first result for multi-turn chains.
        if ($firstResult !== null) {
            $this->messages[] = Message::assistant($firstResult->getContent(), $firstResult->getToolCalls());
            $this->lastResult = $firstResult;
        }

        $this->turnIndex++;

        return new StatisticalResult($runs, $passed, $failures, $contents);
    }

    private function buildExpectations(ProviderResult $result): AnswerExpectations
    {
        return new AnswerExpectations(
            result: $result,
            provider: $this->provider,
            providerOptions: new CompletionOptions(model: $this->model, temperature: $this->temperature),
            cassetteResolver: $this->getCassetteResolver(),
            testName: $this->testName,
            turnIndex: $this->turnIndex,
            judgeProvider: $this->judgeProvider,
            judgeModel: $this->judgeModel,
            judgeTemperature: $this->judgeTemperature,
            systemPrompt: $this->systemPrompt,
        );
    }

    private function executeTurn(int $runIndex = 0): ProviderResult
    {
        if ($this->mockResults !== []) {
            return array_shift($this->mockResults);
        }

        $tools = $this->resolveToolDefinitions();
        $fullMessages = $this->buildMessages();

        $options = new CompletionOptions(
            model: $this->model,
            temperature: $this->temperature,
        );

        // Include runIndex in the test name so each statistical run gets a unique cassette key.
        $testNameForHash = $runIndex > 0
            ? $this->testName . ':run:' . $runIndex
            : $this->testName;

        $cassetteKey = Hasher::make(
            $this->systemPrompt,
            $fullMessages,
            $this->model,
            $this->temperature,
            $tools,
            $testNameForHash,
            $this->turnIndex,
        );

        return $this->getCassetteResolver()->resolve(
            $cassetteKey,
            fn(): ProviderResult => $this->provider->complete($fullMessages, $tools, $options),
            fn(): array => [
                'messages' => array_map(static fn(Message $m): array => $m->toArray(), $fullMessages),
                'options' => $options->toArray(),
                'tools' => array_map(static fn(ToolDefinition $t): array => $t->toArray(), $tools),
            ],
            ['model' => $this->model, 'temperature' => $this->temperature, 'provider' => $this->provider::class],
        );
    }

    /**
     * @return list<Message>
     */
    private function buildMessages(): array
    {
        $result = [];

        if ($this->systemPrompt !== '') {
            $result[] = Message::system($this->systemPrompt);
        }

        return [...$result, ...$this->messages];
    }

    private function getCassetteResolver(): CassetteResolver
    {
        return $this->cassetteResolver ??= new CassetteResolver($this->cassetteStore, $this->replayMode);
    }

    /**
     * @return list<ToolDefinition>
     */
    private function resolveToolDefinitions(): array
    {
        $defs = [];

        foreach ($this->toolClasses as $class) {
            if (! is_a($class, ToolContract::class, true)) { // @phpstan-ignore function.alreadyNarrowedType
                throw new ToolResolutionException("Tool class '{$class}' must implement " . ToolContract::class);
            }

            $defs[] = $class::definition();
        }

        return $defs;
    }
}
