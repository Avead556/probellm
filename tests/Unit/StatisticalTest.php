<?php

declare(strict_types=1);

namespace ProbeLLM\Tests\Unit;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use ProbeLLM\Cassette\CassetteStore;
use ProbeLLM\DSL\DialogScenario;
use ProbeLLM\DTO\ProviderResult;
use ProbeLLM\DTO\StatisticalResult;
use ProbeLLM\Provider\NullProvider;

class StatisticalTest extends TestCase
{
    /*
    |----------------------------------------------------------------------
    | answerSampled
    |----------------------------------------------------------------------
    */

    public function test_answer_sampled_passes_when_all_runs_pass(): void
    {
        $scenario = $this->makeScenario();

        // 3 mock results, all valid JSON with "answer" key.
        $scenario
            ->withMockResult(new ProviderResult('{"answer": "Paris"}'))
            ->withMockResult(new ProviderResult('{"answer": "Paris"}'))
            ->withMockResult(new ProviderResult('{"answer": "Paris"}'));

        $scenario
            ->user('Capital of France?')
            ->answerSampled(runs: 3, passRate: 1.0, assertions: fn($a) => $a->assertJson());

        $this->addToAssertionCount(1);
    }

    public function test_answer_sampled_passes_with_partial_failures_within_threshold(): void
    {
        $scenario = $this->makeScenario();

        // 2 valid JSON, 1 invalid — 66% pass rate, threshold 60%.
        $scenario
            ->withMockResult(new ProviderResult('{"answer": "Paris"}'))
            ->withMockResult(new ProviderResult('not json'))
            ->withMockResult(new ProviderResult('{"answer": "Tokyo"}'));

        $scenario
            ->user('Capital?')
            ->answerSampled(runs: 3, passRate: 0.6, assertions: fn($a) => $a->assertJson());

        $this->addToAssertionCount(1);
    }

    public function test_answer_sampled_fails_when_pass_rate_not_met(): void
    {
        $scenario = $this->makeScenario();

        // 1 valid, 2 invalid — 33% pass rate, threshold 80%.
        $scenario
            ->withMockResult(new ProviderResult('{"answer": "Paris"}'))
            ->withMockResult(new ProviderResult('not json'))
            ->withMockResult(new ProviderResult('also not json'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Statistical assertion failed');

        $scenario
            ->user('Capital?')
            ->answerSampled(runs: 3, passRate: 0.8, assertions: fn($a) => $a->assertJson());
    }

    public function test_answer_sampled_failure_message_includes_details(): void
    {
        $scenario = $this->makeScenario();

        $scenario
            ->withMockResult(new ProviderResult('not json'))
            ->withMockResult(new ProviderResult('not json'));

        try {
            $scenario
                ->user('test')
                ->answerSampled(runs: 2, passRate: 1.0, assertions: fn($a) => $a->assertJson());
            $this->fail('Expected exception');
        } catch (AssertionFailedError $e) {
            $this->assertStringContainsString('0/2 runs passed', $e->getMessage());
            $this->assertStringContainsString('0%', $e->getMessage());
            $this->assertStringContainsString('100%', $e->getMessage());
        }
    }

    /*
    |----------------------------------------------------------------------
    | answerConsistently
    |----------------------------------------------------------------------
    */

    public function test_answer_consistently_passes_when_all_same(): void
    {
        $scenario = $this->makeScenario();

        $scenario
            ->withMockResult(new ProviderResult('Paris'))
            ->withMockResult(new ProviderResult('Paris'))
            ->withMockResult(new ProviderResult('Paris'));

        $scenario
            ->user('Capital of France?')
            ->answerConsistently(runs: 3);

        $this->addToAssertionCount(1);
    }

    public function test_answer_consistently_is_case_insensitive(): void
    {
        $scenario = $this->makeScenario();

        $scenario
            ->withMockResult(new ProviderResult('Paris'))
            ->withMockResult(new ProviderResult('paris'))
            ->withMockResult(new ProviderResult('PARIS'));

        $scenario
            ->user('Capital of France?')
            ->answerConsistently(runs: 3);

        $this->addToAssertionCount(1);
    }

    public function test_answer_consistently_fails_when_responses_differ(): void
    {
        $scenario = $this->makeScenario();

        $scenario
            ->withMockResult(new ProviderResult('Paris'))
            ->withMockResult(new ProviderResult('London'))
            ->withMockResult(new ProviderResult('Paris'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Consistency check failed');

        $scenario
            ->user('Capital of France?')
            ->answerConsistently(runs: 3);
    }

    public function test_answer_consistently_runs_assertions_on_each(): void
    {
        $scenario = $this->makeScenario();

        $scenario
            ->withMockResult(new ProviderResult('{"x": 1}'))
            ->withMockResult(new ProviderResult('{"x": 1}'));

        $assertionCount = 0;

        $scenario
            ->user('test')
            ->answerConsistently(runs: 2, assertions: function ($a) use (&$assertionCount) {
                $a->assertJson();
                $assertionCount++;
            });

        $this->assertSame(2, $assertionCount);
    }

    /*
    |----------------------------------------------------------------------
    | sampleAnswer (non-asserting)
    |----------------------------------------------------------------------
    */

    public function test_sample_answer_returns_statistical_result(): void
    {
        $scenario = $this->makeScenario();

        $scenario
            ->withMockResult(new ProviderResult('{"ok": true}'))
            ->withMockResult(new ProviderResult('not json'))
            ->withMockResult(new ProviderResult('{"ok": true}'));

        $result = $scenario
            ->user('test')
            ->sampleAnswer(runs: 3, assertions: fn($a) => $a->assertJson());

        $this->assertInstanceOf(StatisticalResult::class, $result);
        $this->assertSame(3, $result->getTotalRuns());
        $this->assertSame(2, $result->getPassed());
        $this->assertSame(1, $result->getFailed());
        $this->assertEqualsWithDelta(0.666, $result->getPassRate(), 0.01);
        $this->assertCount(1, $result->getFailures());
        $this->assertCount(3, $result->getContents());
    }

    public function test_statistical_result_consistency_check(): void
    {
        $result = new StatisticalResult(3, 3, [], ['Paris', 'paris', 'PARIS']);
        $this->assertTrue($result->isConsistent());

        $result2 = new StatisticalResult(3, 3, [], ['Paris', 'London', 'Paris']);
        $this->assertFalse($result2->isConsistent());
    }

    /*
    |----------------------------------------------------------------------
    | Multi-turn chaining
    |----------------------------------------------------------------------
    */

    public function test_answer_sampled_allows_multi_turn_chaining(): void
    {
        $scenario = $this->makeScenario();

        // 2 runs for first turn, then 1 regular answer for second turn.
        $scenario
            ->withMockResult(new ProviderResult('Hi there!'))
            ->withMockResult(new ProviderResult('Hello!'))
            ->withMockResult(new ProviderResult('Your name is Alice.'));

        $secondTurnRan = false;

        $scenario
            ->user('Hello, my name is Alice')
            ->answerSampled(runs: 2, passRate: 1.0, assertions: fn($a) => $a) // no-op assertion
            ->user('What is my name?')
            ->answer(function ($a) use (&$secondTurnRan) {
                $secondTurnRan = true;
                $this->assertStringContainsString('Alice', $a->lastMessage());
            });

        $this->assertTrue($secondTurnRan);
    }

    /*
    |----------------------------------------------------------------------
    | Helpers
    |----------------------------------------------------------------------
    */

    private function makeScenario(): DialogScenario
    {
        return new DialogScenario(new NullProvider(), new CassetteStore());
    }
}
