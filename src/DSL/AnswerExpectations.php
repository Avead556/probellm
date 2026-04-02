<?php

declare(strict_types=1);

namespace ProbeLLM\DSL;

use Closure;
use PHPUnit\Framework\Assert;
use ProbeLLM\Cassette\CassetteResolver;
use ProbeLLM\Cassette\CassetteStore;
use ProbeLLM\DSL\Concerns\ContentAssertions;
use ProbeLLM\DSL\Concerns\GuardrailAssertions;
use ProbeLLM\DSL\Concerns\JsonAssertions;
use ProbeLLM\DSL\Concerns\LatencyAssertions;
use ProbeLLM\DSL\Concerns\SnapshotAssertions;
use ProbeLLM\DSL\Concerns\UsageAssertions;
use ProbeLLM\DTO\CompletionOptions;
use ProbeLLM\DTO\ProviderResult;
use ProbeLLM\DTO\ToolCall;
use ProbeLLM\Provider\LLMProvider;

final class AnswerExpectations
{
    use ContentAssertions;
    use GuardrailAssertions;
    use JsonAssertions;
    use LatencyAssertions;
    use SnapshotAssertions;
    use UsageAssertions;

    private readonly CassetteResolver $cassetteResolver;
    private int $judgeIndex = 0;

    public function __construct(
        private readonly ProviderResult $result,
        private readonly LLMProvider $provider,
        private readonly CompletionOptions $providerOptions = new CompletionOptions(),
        ?CassetteResolver $cassetteResolver = null,
        private readonly string $testName = '',
        private readonly int $turnIndex = 0,
        private readonly ?LLMProvider $judgeProvider = null,
        private readonly ?string $judgeModel = null,
        private readonly ?float $judgeTemperature = null,
        private readonly string $systemPrompt = '',
    ) {
        $this->cassetteResolver = $cassetteResolver ?? new CassetteResolver(
            new CassetteStore(),
            false,
        );
    }

    /**
     * Raw assistant text content.
     */
    public function lastMessage(): string
    {
        return $this->result->getContent();
    }

    /**
     * Parsed tool calls from the result.
     *
     * @return list<ToolCall>
     */
    public function toolCalls(): array
    {
        return $this->result->getToolCalls();
    }

    /**
     * Assert that a specific tool was called N times.
     */
    public function assertToolCalled(string $name, int $times = 1): self
    {
        $count = 0;
        foreach ($this->result->getToolCalls() as $tc) {
            if ($tc->getName() === $name) {
                $count++;
            }
        }

        Assert::assertSame(
            $times,
            $count,
            "Expected tool '{$name}' to be called {$times} time(s), but it was called {$count} time(s).",
        );

        return $this;
    }

    /**
     * Assert on arguments of a specific tool call.
     *
     * @param Closure(array<string, mixed>): void $predicate Receives the arguments array.
     */
    public function assertToolArgs(string $name, Closure $predicate): self
    {
        foreach ($this->result->getToolCalls() as $tc) {
            if ($tc->getName() === $name) {
                $predicate($tc->getArguments());

                return $this;
            }
        }

        Assert::fail("Tool '{$name}' was not called — cannot assert arguments.");
    }

    /**
     * Assert the answer using another LLM call as judge.
     *
     * The judge receives the assistant's response and your criteria prompt,
     * then must return JSON: {"pass": true/false, "reason": "..."}.
     *
     * Priority for model/temperature: per-call argument > attribute > dialog default.
     *
     * @param string      $criteria    Natural-language description of what to check.
     * @param string|null $model       Override model for the judge call (null = use attribute or dialog default).
     * @param float|null  $temperature Override temperature for the judge call (null = use attribute default or 0.0).
     */
    public function assertByPrompt(
        string $criteria,
        ?string $model = null,
        ?float $temperature = null,
    ): self {
        $judgeModel = $model ?? $this->judgeModel ?? $this->providerOptions->getModel();
        $resolvedTemperature = $temperature ?? $this->judgeTemperature ?? 0.0;

        JudgeRunner::assertPassed(
            provider: $this->judgeProvider ?? $this->provider,
            resolver: $this->cassetteResolver,
            content: $this->result->getContent(),
            contentLabel: "Assistant's response",
            criteria: $criteria,
            testName: 'judge:' . $this->testName . ':' . $this->turnIndex,
            judgeIndex: $this->judgeIndex,
            model: $judgeModel,
            temperature: $resolvedTemperature,
        );

        return $this;
    }

    /*
    |----------------------------------------------------------------------
    | Trait abstract method implementations
    |----------------------------------------------------------------------
    */

    private function getContent(): string
    {
        return $this->result->getContent();
    }

    private function getSystemPrompt(): string
    {
        return $this->systemPrompt;
    }

    /**
     * @return array<string, mixed>
     */
    private function getResultMeta(): array
    {
        return $this->result->getMeta();
    }
}
