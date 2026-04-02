<?php

declare(strict_types=1);

namespace ProbeLLM\Tests\Unit;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use ProbeLLM\Cassette\CassetteStore;
use ProbeLLM\DSL\DialogScenario;
use ProbeLLM\DTO\ProviderResult;
use ProbeLLM\DTO\ToolCall;
use ProbeLLM\Provider\NullProvider;

class AgentLoopTest extends TestCase
{
    public function test_loop_executes_tools_and_reaches_final_answer(): void
    {
        $scenario = $this->makeScenario();

        // Turn 1: model calls search tool.
        $scenario->withMockResult(new ProviderResult('', [
            new ToolCall('call_1', 'search', ['query' => 'PHP 9']),
        ]));

        // Turn 2: model gives final answer (no tool calls).
        $scenario->withMockResult(new ProviderResult('PHP 9 is expected in 2027.'));

        $executedWith = null;

        $scenario
            ->user('When is PHP 9 coming out?')
            ->agentLoop(
                maxIterations: 5,
                toolExecutors: [
                    'search' => function (array $args) use (&$executedWith) {
                        $executedWith = $args;

                        return ['result' => 'PHP 9 release is planned for 2027.'];
                    },
                ],
                assertions: fn($a) => $a->assertContains('2027'),
            );

        $this->assertSame(['query' => 'PHP 9'], $executedWith);
    }

    public function test_loop_handles_multiple_tool_calls_per_turn(): void
    {
        $scenario = $this->makeScenario();

        // Turn 1: model calls two tools.
        $scenario->withMockResult(new ProviderResult('', [
            new ToolCall('call_1', 'search', ['query' => 'A']),
            new ToolCall('call_2', 'calculate', ['expression' => '2+2']),
        ]));

        // Turn 2: final answer.
        $scenario->withMockResult(new ProviderResult('Done.'));

        $calls = [];

        $scenario
            ->user('Do both')
            ->agentLoop(
                maxIterations: 5,
                toolExecutors: [
                    'search' => function ($args) use (&$calls) {
                        $calls[] = 'search';

                        return 'found it';
                    },
                    'calculate' => function ($args) use (&$calls) {
                        $calls[] = 'calculate';

                        return '4';
                    },
                ],
                assertions: fn($a) => $a->assertContains('Done'),
            );

        $this->assertSame(['search', 'calculate'], $calls);
    }

    public function test_loop_stops_at_max_iterations(): void
    {
        $scenario = $this->makeScenario();

        // Every turn calls a tool — should stop at maxIterations.
        for ($i = 0; $i < 3; $i++) {
            $scenario->withMockResult(new ProviderResult('thinking...', [
                new ToolCall("call_{$i}", 'search', ['query' => "q{$i}"]),
            ]));
        }

        $iterations = 0;

        $scenario
            ->user('Loop forever')
            ->agentLoop(
                maxIterations: 3,
                toolExecutors: [
                    'search' => function ($args) use (&$iterations) {
                        $iterations++;

                        return 'result';
                    },
                ],
                assertions: fn($a) => $a->assertNotEmpty(),
            );

        $this->assertSame(3, $iterations);
    }

    public function test_loop_immediate_answer_without_tools(): void
    {
        $scenario = $this->makeScenario();

        // Model responds immediately without tool calls.
        $scenario->withMockResult(new ProviderResult('Direct answer.'));

        $scenario
            ->user('Simple question')
            ->agentLoop(
                maxIterations: 5,
                toolExecutors: [],
                assertions: fn($a) => $a->assertContains('Direct answer'),
            );

        $this->addToAssertionCount(1);
    }

    public function test_loop_fails_on_unregistered_tool(): void
    {
        $scenario = $this->makeScenario();

        $scenario->withMockResult(new ProviderResult('', [
            new ToolCall('call_1', 'unknown_tool', []),
        ]));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("tool 'unknown_tool' was called but no executor");

        $scenario
            ->user('test')
            ->agentLoop(
                maxIterations: 5,
                toolExecutors: ['search' => fn($a) => 'x'],
                assertions: fn($a) => null,
            );
    }

    public function test_loop_allows_chaining_after(): void
    {
        $scenario = $this->makeScenario();

        $scenario->withMockResult(new ProviderResult('First answer.'));
        $scenario->withMockResult(new ProviderResult('Second answer.'));

        $scenario
            ->user('First')
            ->agentLoop(
                maxIterations: 3,
                toolExecutors: [],
                assertions: fn($a) => $a->assertContains('First'),
            )
            ->user('Second')
            ->answer(fn($a) => $a->assertContains('Second'));

        $this->addToAssertionCount(1);
    }

    private function makeScenario(): DialogScenario
    {
        return new DialogScenario(new NullProvider(), new CassetteStore());
    }
}
