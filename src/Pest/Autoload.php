<?php

declare(strict_types=1);

/*
 * ProbeLLM Pest Plugin — global helpers and custom expectations.
 *
 * This file is auto-loaded via Composer's "files" directive.
 * When Pest is not installed, all registrations are silently skipped.
 */

// Guard: only register when Pest is available.
if (! function_exists('test')) {
    return;
}

use ProbeLLM\AgentTestCase;
use ProbeLLM\DSL\AnswerExpectations;
use ProbeLLM\DSL\DialogScenario;
use ProbeLLM\DSL\ElevenLabsExpectations;
use ProbeLLM\DSL\ElevenLabsScenario;
use ProbeLLM\DTO\EvalSummary;
use ProbeLLM\ElevenLabsTestCase;
use ProbeLLM\Exception\ConfigurationException;

/*
|--------------------------------------------------------------------------
| Global Helper Functions
|--------------------------------------------------------------------------
*/

if (! function_exists('dialog')) {
    /**
     * Start a dialog scenario from the current Pest test.
     *
     * Requires `uses(AgentTestCase::class)` in the test file or Pest.php.
     */
    function dialog(): DialogScenario
    {
        $test = test();

        if (! $test instanceof AgentTestCase) {
            throw new ConfigurationException(
                'dialog() requires uses(AgentTestCase::class). '
                . 'Add it to your test file or tests/Pest.php.',
            );
        }

        return Closure::bind(fn() => $this->dialog(), $test, $test::class)();
    }
}

if (! function_exists('evalDataset')) {
    /**
     * Run an evaluation dataset against the agent.
     *
     * Requires `uses(AgentTestCase::class)` in the test file or Pest.php.
     *
     * @param Closure(ProbeLLM\DTO\DatasetRow, DialogScenario): void $callback
     */
    function evalDataset(
        Closure $callback,
        ?string $path = null,
        float $requiredPassRate = 1.0,
    ): EvalSummary {
        $test = test();

        if (! $test instanceof AgentTestCase) {
            throw new ConfigurationException(
                'evalDataset() requires uses(AgentTestCase::class). '
                . 'Add it to your test file or tests/Pest.php.',
            );
        }

        return Closure::bind(
            fn() => $this->evalDataset($callback, $path, $requiredPassRate),
            $test,
            $test::class,
        )();
    }
}

if (! function_exists('elevenLabs')) {
    /**
     * Start an ElevenLabs scenario from the current Pest test.
     *
     * Requires `uses(ElevenLabsTestCase::class)` in the test file or Pest.php.
     */
    function elevenLabs(): ElevenLabsScenario
    {
        $test = test();

        if (! $test instanceof ElevenLabsTestCase) {
            throw new ConfigurationException(
                'elevenLabs() requires uses(ElevenLabsTestCase::class). '
                . 'Add it to your test file or tests/Pest.php.',
            );
        }

        return Closure::bind(fn() => $this->elevenLabs(), $test, $test::class)();
    }
}

/*
|--------------------------------------------------------------------------
| Custom Pest Expectations
|--------------------------------------------------------------------------
*/

if (class_exists(Pest\Expectation::class)) {
    /*
     * Assert the LLM response passes an LLM-as-judge evaluation.
     *
     * Usage: expect($answer)->toPassJudge('Response is helpful');
     */
    expect()->extend(
        'toPassJudge',
        function (string $criteria, ?string $model = null, ?float $temperature = null) {
            /** @var Pest\Expectation<AnswerExpectations|ElevenLabsExpectations> $this */
            match (true) {
                $this->value instanceof AnswerExpectations => $this->value->assertByPrompt($criteria, $model, $temperature),
                $this->value instanceof ElevenLabsExpectations => $this->value->assertByPrompt($criteria, $model, $temperature),
                default => throw new ConfigurationException(
                    'toPassJudge() expects an AnswerExpectations or ElevenLabsExpectations value.',
                ),
            };

            return $this;
        },
    );

    /*
     * Assert a specific tool was called N times (default 1).
     *
     * Usage: expect($answer)->toHaveCalledTool('search');
     *        expect($answer)->toHaveCalledTool('search', times: 2);
     */
    expect()->extend(
        'toHaveCalledTool',
        function (string $name, int $times = 1) {
            /** @var Pest\Expectation<AnswerExpectations|ElevenLabsExpectations> $this */
            match (true) {
                $this->value instanceof AnswerExpectations => $this->value->assertToolCalled($name, $times),
                $this->value instanceof ElevenLabsExpectations => $this->value->assertToolCalledTimes($name, $times),
                default => throw new ConfigurationException(
                    'toHaveCalledTool() expects an AnswerExpectations or ElevenLabsExpectations value.',
                ),
            };

            return $this;
        },
    );

    /*
     * Assert the assistant response is valid JSON.
     *
     * Usage: expect($answer)->toBeValidLlmJson();
     */
    expect()->extend(
        'toBeValidLlmJson',
        function () {
            /** @var Pest\Expectation<AnswerExpectations> $this */
            if (! $this->value instanceof AnswerExpectations) {
                throw new ConfigurationException(
                    'toBeValidLlmJson() expects an AnswerExpectations value.',
                );
            }

            $this->value->assertJson();

            return $this;
        },
    );

    /*
     * Assert a JSONPath value in the assistant response.
     *
     * Usage: expect($answer)->toHaveJsonPath('$.name', equals: 'John');
     *        expect($answer)->toHaveJsonPath('$.items', notEmpty: true);
     *        expect($answer)->toHaveJsonPath('$.bio', contains: 'developer');
     */
    expect()->extend(
        'toHaveJsonPath',
        function (
            string $path,
            mixed $equals = null,
            bool $notEmpty = false,
            ?string $contains = null,
            ?string $notContains = null,
        ) {
            /** @var Pest\Expectation<AnswerExpectations> $this */
            if (! $this->value instanceof AnswerExpectations) {
                throw new ConfigurationException(
                    'toHaveJsonPath() expects an AnswerExpectations value.',
                );
            }

            $this->value->assertJsonPath($path, $equals, $notEmpty, $contains, $notContains);

            return $this;
        },
    );

    /*
     * Assert the transcript contains a string (ElevenLabs).
     *
     * Usage: expect($expectations)->toHaveTranscriptContaining('hello');
     */
    expect()->extend(
        'toHaveTranscriptContaining',
        function (string $needle) {
            /** @var Pest\Expectation<ElevenLabsExpectations> $this */
            if (! $this->value instanceof ElevenLabsExpectations) {
                throw new ConfigurationException(
                    'toHaveTranscriptContaining() expects an ElevenLabsExpectations value.',
                );
            }

            $this->value->assertTranscriptContains($needle);

            return $this;
        },
    );

    /*
     * Assert that the agent said something (ElevenLabs).
     *
     * Usage: expect($expectations)->agentToHaveSaid('welcome');
     */
    expect()->extend(
        'agentToHaveSaid',
        function (string $needle) {
            /** @var Pest\Expectation<ElevenLabsExpectations> $this */
            if (! $this->value instanceof ElevenLabsExpectations) {
                throw new ConfigurationException(
                    'agentToHaveSaid() expects an ElevenLabsExpectations value.',
                );
            }

            $this->value->assertAgentSaid($needle);

            return $this;
        },
    );

    /*
     * Assert all ElevenLabs evaluations passed.
     *
     * Usage: expect($expectations)->allEvaluationsToPass();
     */
    expect()->extend(
        'allEvaluationsToPass',
        function () {
            /** @var Pest\Expectation<ElevenLabsExpectations> $this */
            if (! $this->value instanceof ElevenLabsExpectations) {
                throw new ConfigurationException(
                    'allEvaluationsToPass() expects an ElevenLabsExpectations value.',
                );
            }

            $this->value->assertAllEvaluationsPassed();

            return $this;
        },
    );

    /*
    |----------------------------------------------------------------------
    | Guardrail / Security Expectations
    |----------------------------------------------------------------------
    */

    /*
    |----------------------------------------------------------------------
    | Latency Expectations
    |----------------------------------------------------------------------
    */

    /*
     * Assert the response was received within the given time limit.
     *
     * Usage: expect($answer)->toRespondWithin(3000);
     */
    expect()->extend(
        'toRespondWithin',
        function (int $maxMs) {
            /** @var Pest\Expectation<AnswerExpectations> $this */
            if (! $this->value instanceof AnswerExpectations) {
                throw new ConfigurationException(
                    'toRespondWithin() expects an AnswerExpectations value.',
                );
            }

            $this->value->assertResponseTime($maxMs);

            return $this;
        },
    );

    /*
     * Assert no system prompt leak in the response.
     *
     * Usage: expect($answer)->toNotLeakPrompt();
     */
    expect()->extend(
        'toNotLeakPrompt',
        function () {
            /** @var Pest\Expectation<AnswerExpectations> $this */
            if (! $this->value instanceof AnswerExpectations) {
                throw new ConfigurationException(
                    'toNotLeakPrompt() expects an AnswerExpectations value.',
                );
            }

            $this->value->assertNoPromptLeak();

            return $this;
        },
    );

    /*
     * Assert no PII in the response.
     *
     * Usage: expect($answer)->toNotContainPII();
     */
    expect()->extend(
        'toNotContainPII',
        function () {
            /** @var Pest\Expectation<AnswerExpectations> $this */
            if (! $this->value instanceof AnswerExpectations) {
                throw new ConfigurationException(
                    'toNotContainPII() expects an AnswerExpectations value.',
                );
            }

            $this->value->assertNoPII();

            return $this;
        },
    );

    /*
     * Assert the agent refuses a harmful request.
     *
     * Usage: expect($answer)->toRefuseHarmful();
     */
    expect()->extend(
        'toRefuseHarmful',
        function () {
            /** @var Pest\Expectation<AnswerExpectations> $this */
            if (! $this->value instanceof AnswerExpectations) {
                throw new ConfigurationException(
                    'toRefuseHarmful() expects an AnswerExpectations value.',
                );
            }

            $this->value->assertRefusesHarmful();

            return $this;
        },
    );

    /*
     * Assert the agent stays in its assigned role.
     *
     * Usage: expect($answer)->toStayInRole();
     */
    expect()->extend(
        'toStayInRole',
        function () {
            /** @var Pest\Expectation<AnswerExpectations> $this */
            if (! $this->value instanceof AnswerExpectations) {
                throw new ConfigurationException(
                    'toStayInRole() expects an AnswerExpectations value.',
                );
            }

            $this->value->assertStaysInRole();

            return $this;
        },
    );

    /*
     * Assert the response is grounded in context (no hallucination).
     *
     * Usage: expect($answer)->toNotHallucinate('Context text...');
     */
    expect()->extend(
        'toNotHallucinate',
        function (string $context) {
            /** @var Pest\Expectation<AnswerExpectations> $this */
            if (! $this->value instanceof AnswerExpectations) {
                throw new ConfigurationException(
                    'toNotHallucinate() expects an AnswerExpectations value.',
                );
            }

            $this->value->assertNoHallucination($context);

            return $this;
        },
    );
}
