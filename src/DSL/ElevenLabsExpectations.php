<?php

declare(strict_types=1);

namespace ProbeLLM\DSL;

use PHPUnit\Framework\Assert;
use ProbeLLM\Cassette\CassetteResolver;
use ProbeLLM\Cassette\CassetteStore;
use ProbeLLM\DTO\ElevenLabs\SimulationResponse;
use ProbeLLM\DTO\ElevenLabs\TranscriptToolCall;
use ProbeLLM\Provider\LLMProvider;

final class ElevenLabsExpectations
{
    private int $judgeIndex = 0;

    public function __construct(
        private readonly SimulationResponse $response,
        private readonly ?LLMProvider $judgeProvider = null,
        private readonly ?CassetteResolver $cassetteResolver = null,
        private readonly string $testName = '',
        private readonly ?string $judgeModel = null,
    ) {}

    public function getResponse(): SimulationResponse
    {
        return $this->response;
    }

    /**
     * Assert a specific tool was called at least once.
     */
    public function assertToolCalled(string $toolName): self
    {
        Assert::assertNotNull(
            $this->findToolCall($toolName),
            "Expected tool '{$toolName}' to be called, but it was not.",
        );

        return $this;
    }

    /**
     * Assert a specific tool was NOT called.
     */
    public function assertToolNotCalled(string $toolName): self
    {
        Assert::assertNull(
            $this->findToolCall($toolName),
            "Expected tool '{$toolName}' NOT to be called, but it was.",
        );

        return $this;
    }

    /**
     * Assert a tool was called exactly N times.
     */
    public function assertToolCalledTimes(string $toolName, int $expected): self
    {
        $count = 0;

        foreach ($this->response->getToolCalls() as $tc) {
            if ($tc->getToolName() === $toolName) {
                $count++;
            }
        }

        Assert::assertSame(
            $expected,
            $count,
            "Expected tool '{$toolName}' to be called {$expected} time(s), but it was called {$count} time(s).",
        );

        return $this;
    }

    /**
     * Assert on the arguments of a specific tool call via callback.
     *
     * @param callable(array<string, mixed>): void $predicate
     */
    public function assertToolArgs(string $toolName, callable $predicate): self
    {
        $tc = $this->findToolCall($toolName);

        if ($tc === null) {
            Assert::fail("Tool '{$toolName}' was not called — cannot assert arguments.");
        }

        $predicate($tc->getParams());

        return $this;
    }

    /**
     * Assert that a tool was called AND actually executed (tool_has_been_called = true).
     */
    public function assertToolExecuted(string $toolName): self
    {
        $found = false;

        foreach ($this->response->getToolCalls() as $tc) {
            if ($tc->getToolName() === $toolName && $tc->hasBeenCalled()) {
                $found = true;

                break;
            }
        }

        Assert::assertTrue(
            $found,
            "Expected tool '{$toolName}' to be executed (tool_has_been_called=true), but it was not.",
        );

        return $this;
    }

    /**
     * Assert the total number of tool calls across the transcript.
     */
    public function assertToolCallCount(int $expected): self
    {
        $count = count($this->response->getToolCalls());

        Assert::assertSame(
            $expected,
            $count,
            "Expected {$expected} tool call(s), but got {$count}.",
        );

        return $this;
    }

    /**
     * Assert no tools were called.
     */
    public function assertNoToolsCalled(): self
    {
        return $this->assertToolCallCount(0);
    }

    /**
     * Assert a specific tool call parameter value.
     */
    public function assertToolCallParam(string $toolName, string $paramKey, mixed $expected): self
    {
        $tc = $this->findToolCall($toolName);

        if ($tc === null) {
            Assert::fail("Tool '{$toolName}' was not called — cannot assert param '{$paramKey}'.");
        }

        $params = $tc->getParams();

        Assert::assertArrayHasKey(
            $paramKey,
            $params,
            "Tool '{$toolName}' params missing key '{$paramKey}'.",
        );
        Assert::assertSame(
            $expected,
            $params[$paramKey],
            "Tool '{$toolName}' param '{$paramKey}' does not match expected value.",
        );

        return $this;
    }

    /**
     * Assert a tool call parameter contains a substring.
     */
    public function assertToolCallParamContains(string $toolName, string $paramKey, string $needle): self
    {
        $tc = $this->findToolCall($toolName);

        if ($tc === null) {
            Assert::fail("Tool '{$toolName}' was not called — cannot assert param '{$paramKey}'.");
        }

        $params = $tc->getParams();

        Assert::assertArrayHasKey($paramKey, $params, "Tool '{$toolName}' params missing key '{$paramKey}'.");
        Assert::assertIsString($params[$paramKey], "Tool '{$toolName}' param '{$paramKey}' is not a string.");
        Assert::assertStringContainsString(
            $needle,
            $params[$paramKey],
            "Tool '{$toolName}' param '{$paramKey}' does not contain '{$needle}'.",
        );

        return $this;
    }

    /**
     * Assert a tool call has a specific parameter key (regardless of value).
     */
    public function assertToolCallHasParam(string $toolName, string $paramKey): self
    {
        $tc = $this->findToolCall($toolName);

        if ($tc === null) {
            Assert::fail("Tool '{$toolName}' was not called — cannot assert param '{$paramKey}'.");
        }

        Assert::assertArrayHasKey(
            $paramKey,
            $tc->getParams(),
            "Tool '{$toolName}' params missing key '{$paramKey}'.",
        );

        return $this;
    }

    /**
     * Assert a specific evaluation criterion passed.
     */
    public function assertEvaluation(string $criteriaId): self
    {
        foreach ($this->response->getEvaluationResults() as $result) {
            if ($result->getCriteriaId() === $criteriaId) {
                Assert::assertTrue(
                    $result->isPassed(),
                    "Evaluation '{$criteriaId}' failed. Reason: " . $result->getReason(),
                );

                return $this;
            }
        }

        Assert::fail("Evaluation criterion '{$criteriaId}' not found in results.");
    }

    /**
     * Assert a specific evaluation criterion failed.
     */
    public function assertEvaluationFailed(string $criteriaId): self
    {
        foreach ($this->response->getEvaluationResults() as $result) {
            if ($result->getCriteriaId() === $criteriaId) {
                Assert::assertFalse(
                    $result->isPassed(),
                    "Expected evaluation '{$criteriaId}' to fail, but it passed.",
                );

                return $this;
            }
        }

        Assert::fail("Evaluation criterion '{$criteriaId}' not found in results.");
    }

    /**
     * Assert all evaluation criteria passed.
     */
    public function assertAllEvaluationsPassed(): self
    {
        $results = $this->response->getEvaluationResults();

        Assert::assertNotEmpty($results, 'No evaluation results found.');

        foreach ($results as $result) {
            Assert::assertTrue(
                $result->isPassed(),
                "Evaluation '{$result->getCriteriaId()}' failed. Reason: " . $result->getReason(),
            );
        }

        return $this;
    }

    /**
     * Assert the number of evaluation results.
     */
    public function assertEvaluationCount(int $expected): self
    {
        $count = count($this->response->getEvaluationResults());

        Assert::assertSame(
            $expected,
            $count,
            "Expected {$expected} evaluation result(s), but got {$count}.",
        );

        return $this;
    }

    /**
     * Assert the full transcript contains a string.
     */
    public function assertTranscriptContains(string $needle): self
    {
        Assert::assertStringContainsString(
            $needle,
            $this->buildTranscriptText(),
            "Expected transcript to contain '{$needle}', but it did not.",
        );

        return $this;
    }

    /**
     * Assert the full transcript does NOT contain a string.
     */
    public function assertTranscriptNotContains(string $needle): self
    {
        Assert::assertStringNotContainsString(
            $needle,
            $this->buildTranscriptText(),
            "Expected transcript NOT to contain '{$needle}', but it did.",
        );

        return $this;
    }

    /**
     * Assert the transcript matches a regex pattern.
     */
    public function assertTranscriptMatchesRegex(string $pattern): self
    {
        Assert::assertMatchesRegularExpression(
            $pattern,
            $this->buildTranscriptText(),
            "Transcript does not match pattern '{$pattern}'.",
        );

        return $this;
    }

    /**
     * Assert that only agent messages contain a string.
     */
    public function assertAgentSaid(string $needle): self
    {
        $agentText = $this->buildTranscriptTextForRole('agent');

        Assert::assertStringContainsString(
            $needle,
            $agentText,
            "Expected agent to say '{$needle}', but it did not.",
        );

        return $this;
    }

    /**
     * Assert that agent never said a specific string.
     */
    public function assertAgentNeverSaid(string $needle): self
    {
        $agentText = $this->buildTranscriptTextForRole('agent');

        Assert::assertStringNotContainsString(
            $needle,
            $agentText,
            "Expected agent to never say '{$needle}', but it did.",
        );

        return $this;
    }

    /**
     * Assert the first agent message contains a string.
     */
    public function assertFirstAgentMessage(string $needle): self
    {
        foreach ($this->response->getTranscript() as $entry) {
            if ($entry->getRole() === 'agent') {
                Assert::assertStringContainsString(
                    $needle,
                    $entry->getContent(),
                    "Expected first agent message to contain '{$needle}', got: " . mb_substr($entry->getContent(), 0, 200),
                );

                return $this;
            }
        }

        Assert::fail('No agent message found in transcript.');
    }

    /**
     * Assert the last agent message contains a string.
     */
    public function assertLastAgentMessage(string $needle): self
    {
        $lastAgent = null;

        foreach ($this->response->getTranscript() as $entry) {
            if ($entry->getRole() === 'agent') {
                $lastAgent = $entry;
            }
        }

        Assert::assertNotNull($lastAgent, 'No agent message found in transcript.');
        Assert::assertStringContainsString(
            $needle,
            $lastAgent->getContent(),
            "Expected last agent message to contain '{$needle}', got: " . mb_substr($lastAgent->getContent(), 0, 200),
        );

        return $this;
    }

    /**
     * Assert a transcript entry's role at a given index.
     */
    public function assertTranscriptRole(int $index, string $expectedRole): self
    {
        $transcript = $this->response->getTranscript();

        Assert::assertArrayHasKey($index, $transcript, "Transcript entry [{$index}] does not exist.");
        Assert::assertSame(
            $expectedRole,
            $transcript[$index]->getRole(),
            "Expected transcript[{$index}] role to be '{$expectedRole}'.",
        );

        return $this;
    }

    /**
     * Assert a transcript entry's content at a given index.
     */
    public function assertTranscriptContent(int $index, string $expected): self
    {
        $transcript = $this->response->getTranscript();

        Assert::assertArrayHasKey($index, $transcript, "Transcript entry [{$index}] does not exist.");
        Assert::assertSame(
            $expected,
            $transcript[$index]->getContent(),
            "Expected transcript[{$index}] content to equal '{$expected}'.",
        );

        return $this;
    }

    /**
     * Assert the transcript has at least N entries.
     */
    public function assertMinTurns(int $min): self
    {
        $count = count($this->response->getTranscript());

        Assert::assertGreaterThanOrEqual(
            $min,
            $count,
            "Expected at least {$min} transcript entries, but got {$count}.",
        );

        return $this;
    }

    /**
     * Assert the transcript has at most N entries.
     */
    public function assertMaxTurns(int $max): self
    {
        $count = count($this->response->getTranscript());

        Assert::assertLessThanOrEqual(
            $max,
            $count,
            "Expected at most {$max} transcript entries, but got {$count}.",
        );

        return $this;
    }

    /**
     * Assert that a specific agent handled part of the conversation.
     */
    public function assertAgentHandled(string $agentId): self
    {
        foreach ($this->response->getTranscript() as $entry) {
            if ($entry->getAgentId() === $agentId) {
                return $this;
            }
        }

        Assert::fail("Expected agent '{$agentId}' to appear in transcript, but it did not.");
    }

    /**
     * Assert that the conversation was transferred from one agent to another.
     */
    public function assertTransferredToAgent(string $targetAgentId): self
    {
        $agentIds = $this->getAgentIds();

        Assert::assertTrue(
            in_array($targetAgentId, $agentIds, true),
            "Expected transfer to agent '{$targetAgentId}', but conversation only involved: " . implode(', ', $agentIds),
        );

        Assert::assertTrue(
            count($agentIds) >= 2,
            'Expected at least 2 agents in conversation (transfer), but found: ' . implode(', ', $agentIds),
        );

        return $this;
    }

    /**
     * Assert that a specific workflow node was reached.
     */
    public function assertWorkflowNodeReached(string $nodeId): self
    {
        foreach ($this->response->getTranscript() as $entry) {
            if ($entry->getWorkflowNodeId() === $nodeId) {
                return $this;
            }
        }

        Assert::fail("Expected workflow node '{$nodeId}' to be reached, but it was not.");
    }

    /**
     * Assert the number of unique agents in the conversation.
     */
    public function assertAgentCount(int $expected): self
    {
        $count = count($this->getAgentIds());

        Assert::assertSame(
            $expected,
            $count,
            "Expected {$expected} unique agent(s), but got {$count}.",
        );

        return $this;
    }

    /**
     * Get all unique agent IDs that participated in the conversation.
     *
     * @return list<string>
     */
    public function getAgentIds(): array
    {
        $ids = [];

        foreach ($this->response->getTranscript() as $entry) {
            $id = $entry->getAgentId();

            if ($id !== null && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Assert the call was successful (analysis.call_successful = "success").
     */
    public function assertCallSuccessful(): self
    {
        $raw = $this->response->getRawData();
        $status = $raw['analysis']['call_successful'] ?? null;

        Assert::assertSame(
            'success',
            $status,
            "Expected call_successful to be 'success', got: " . ($status ?? 'null'),
        );

        return $this;
    }

    /**
     * Assert the transcript summary contains a string.
     */
    public function assertTranscriptSummaryContains(string $needle): self
    {
        $raw = $this->response->getRawData();
        $summary = $raw['analysis']['transcript_summary'] ?? '';

        Assert::assertStringContainsString(
            $needle,
            $summary,
            "Expected transcript summary to contain '{$needle}'.",
        );

        return $this;
    }

    /**
     * Assert the conversation using an LLM judge.
     *
     * The judge receives the full transcript and evaluates it against the given criteria.
     * Returns JSON: {"pass": true/false, "reason": "..."}.
     */
    public function assertByPrompt(
        string $criteria,
        ?string $model = null,
        ?float $temperature = null,
    ): self {
        Assert::assertNotNull(
            $this->judgeProvider,
            'Cannot use assertByPrompt() without a judge provider. Call withJudgeProvider() on ElevenLabsScenario or override resolveJudgeProvider() in your test case.',
        );

        $resolver = $this->cassetteResolver ?? new CassetteResolver(new CassetteStore(), false);

        JudgeRunner::assertPassed(
            provider: $this->judgeProvider,
            resolver: $resolver,
            content: $this->buildTranscriptText(),
            contentLabel: 'Conversation transcript',
            criteria: $criteria,
            testName: 'judge:elevenlabs:' . $this->testName,
            judgeIndex: $this->judgeIndex,
            model: $model ?? $this->judgeModel ?? 'gpt-4o',
            temperature: $temperature ?? 0.0,
        );

        return $this;
    }

    private function buildTranscriptText(): string
    {
        $lines = [];

        foreach ($this->response->getTranscript() as $entry) {
            $lines[] = '[' . $entry->getRole() . ']: ' . $entry->getContent();
        }

        return implode("\n", $lines);
    }

    private function buildTranscriptTextForRole(string $role): string
    {
        $lines = [];

        foreach ($this->response->getTranscript() as $entry) {
            if ($entry->getRole() === $role) {
                $lines[] = $entry->getContent();
            }
        }

        return implode("\n", $lines);
    }

    private function findToolCall(string $toolName): ?TranscriptToolCall
    {
        foreach ($this->response->getToolCalls() as $tc) {
            if ($tc->getToolName() === $toolName) {
                return $tc;
            }
        }

        return null;
    }
}
