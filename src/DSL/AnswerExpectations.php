<?php

declare(strict_types=1);

namespace ProbeLLM\DSL;

use PHPUnit\Framework\Assert;
use ProbeLLM\Cassette\CassetteResolver;
use ProbeLLM\Cassette\CassetteStore;
use ProbeLLM\DTO\CompletionOptions;
use ProbeLLM\DTO\ProviderResult;
use ProbeLLM\DTO\ToolCall;
use ProbeLLM\Exception\InvalidResponseException;
use ProbeLLM\Provider\LLMProvider;

final class AnswerExpectations
{
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
     * Decode assistant content as JSON array.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $decoded = json_decode(self::stripMarkdownFences($this->result->getContent()), true);

        if (! is_array($decoded)) {
            throw new InvalidResponseException(
                'Assistant content is not valid JSON: ' . mb_substr($this->result->getContent(), 0, 200),
            );
        }

        return $decoded;
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
     * Assert that assistant content is valid JSON.
     */
    public function assertJson(): self
    {
        $decoded = json_decode(self::stripMarkdownFences($this->result->getContent()), true);

        Assert::assertNotNull(
            $decoded,
            'Expected assistant content to be valid JSON, got: ' . mb_substr($this->result->getContent(), 0, 200),
        );

        return $this;
    }

    /**
     * Assert on a JSONPath value.
     *
     * Supported path syntax: $.key.nested[0].child
     *
     * At least one of $equals / $notEmpty / $contains / $notContains should be specified.
     */
    public function assertJsonPath(
        string $path,
        mixed $equals = null,
        bool $notEmpty = false,
        ?string $contains = null,
        ?string $notContains = null,
    ): self {
        $data = $this->json();
        $resolved = self::resolvePath($data, $path);

        if ($equals !== null) {
            Assert::assertEquals($equals, $resolved, "JSONPath '{$path}' does not equal expected value.");
        }

        if ($notEmpty) {
            Assert::assertNotEmpty($resolved, "JSONPath '{$path}' is empty.");
        }

        if ($contains !== null) {
            Assert::assertIsString($resolved, "JSONPath '{$path}' must be a string for 'contains' check.");
            Assert::assertStringContainsString($contains, $resolved, "JSONPath '{$path}' does not contain '{$contains}'.");
        }

        if ($notContains !== null) {
            Assert::assertIsString($resolved, "JSONPath '{$path}' must be a string for 'notContains' check.");
            Assert::assertStringNotContainsString($notContains, $resolved, "JSONPath '{$path}' should not contain '{$notContains}'.");
        }

        return $this;
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
     * @param callable(array<string, mixed>): void $predicate Receives the arguments array.
     */
    public function assertToolArgs(string $name, callable $predicate): self
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

    /**
     * Strip markdown code fences (```json ... ``` or ``` ... ```) from LLM output.
     */
    private static function stripMarkdownFences(string $content): string
    {
        $trimmed = trim($content);

        if (preg_match('/^```(?:json)?\s*\n?(.*?)\n?\s*```$/s', $trimmed, $matches)) {
            return trim($matches[1]);
        }

        return $trimmed;
    }

    /**
     * Resolve a simple JSONPath like $.a.b[0].c against an array.
     */
    private static function resolvePath(array $data, string $path): mixed
    {
        $normalized = preg_replace('/^\$\.?/', '', $path);

        if ($normalized === '' || $normalized === null) {
            return $data;
        }

        $segments = preg_split('/\./', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        if ($segments === false) {
            throw new InvalidResponseException("Failed to parse JSONPath: {$path}");
        }

        $current = $data;

        foreach ($segments as $segment) {
            if (preg_match('/^(.+?)\[(\d+)]$/', $segment, $m)) {
                $key = $m[1];
                $index = (int) $m[2];

                if (! is_array($current) || ! array_key_exists($key, $current)) {
                    Assert::fail("JSONPath segment '{$key}' not found. Full path: {$path}");
                }

                $current = $current[$key];

                if (! is_array($current) || ! array_key_exists($index, $current)) {
                    Assert::fail("JSONPath index [{$index}] out of bounds on segment '{$key}'. Full path: {$path}");
                }

                $current = $current[$index];
            } else {
                if (! is_array($current) || ! array_key_exists($segment, $current)) {
                    Assert::fail("JSONPath segment '{$segment}' not found. Full path: {$path}");
                }

                $current = $current[$segment];
            }
        }

        return $current;
    }
}
