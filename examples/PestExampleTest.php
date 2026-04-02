<?php

declare(strict_types=1);

/*
 * Example Pest tests for ProbeLLM.
 *
 * Prerequisites:
 *   1. Install Pest: composer require pestphp/pest --dev
 *   2. In tests/Pest.php:
 *      uses(ProbeLLM\AgentTestCase::class)->in('../examples');
 */

use ProbeLLM\DSL\AnswerExpectations;
use ProbeLLM\Tools\SearchTool;

// ---------------------------------------------------------------------------
// Simple single-turn test
// ---------------------------------------------------------------------------

test('agent responds to a greeting', function () {
    dialog()
        ->system('You are a friendly assistant.')
        ->withModel('gpt-4o')
        ->withTemperature(0.0)
        ->withReplayMode()
        ->user('Hello!')
        ->answer(fn(AnswerExpectations $a) => $a->assertByPrompt('Response is a friendly greeting'));
});

// ---------------------------------------------------------------------------
// JSON response with assertions
// ---------------------------------------------------------------------------

test('agent returns valid JSON with required fields', function () {
    dialog()
        ->system('You are a JSON API. Always respond with JSON: {"answer": "...", "confidence": 0.0-1.0}')
        ->withModel('gpt-4o')
        ->withTemperature(0.0)
        ->withReplayMode()
        ->user('What is the capital of France?')
        ->answer(function ($a) {
            expect($a)
                ->toBeValidLlmJson()
                ->toHaveJsonPath('$.answer', contains: 'Paris')
                ->toHaveJsonPath('$.confidence', notEmpty: true);
        });
});

// ---------------------------------------------------------------------------
// Tool calling
// ---------------------------------------------------------------------------

test('agent calls the search tool when asked to search', function () {
    dialog()
        ->system('You are a helpful assistant with search capabilities.')
        ->withModel('gpt-4o')
        ->withTemperature(0.0)
        ->withTools([SearchTool::class])
        ->withReplayMode()
        ->user('Search for "PHP 8.5 release date"')
        ->answer(fn(AnswerExpectations $a) => expect($a)->toHaveCalledTool('search'));
});

// ---------------------------------------------------------------------------
// Multi-turn dialog
// ---------------------------------------------------------------------------

test('agent maintains context across turns', function () {
    dialog()
        ->system('You are a helpful assistant.')
        ->withModel('gpt-4o')
        ->withTemperature(0.0)
        ->withReplayMode()
        ->user('My name is Alice.')
        ->answer(fn(AnswerExpectations $a) => $a->assertByPrompt('Response acknowledges the name Alice'))
        ->user('What is my name?')
        ->answer(fn(AnswerExpectations $a) => $a->assertByPrompt('Response correctly states the name is Alice'));
});

// ---------------------------------------------------------------------------
// LLM-as-judge with Pest expectations
// ---------------------------------------------------------------------------

test('agent explains concepts clearly', function () {
    dialog()
        ->system('You are a teacher who explains concepts simply.')
        ->withModel('gpt-4o')
        ->withTemperature(0.0)
        ->withReplayMode()
        ->user('Explain recursion to a 5-year-old')
        ->answer(fn(AnswerExpectations $a) => expect($a)->toPassJudge(
            'Explanation is simple, uses an analogy or example, '
            . 'and avoids technical jargon. A 5-year-old could understand it.',
        ));
});

// ---------------------------------------------------------------------------
// Mixed: Pest expectations + classic assertions
// ---------------------------------------------------------------------------

test('agent produces structured output', function () {
    dialog()
        ->system('You are a classifier. Respond with JSON: {"category": "...", "tags": ["..."]}')
        ->withModel('gpt-4o')
        ->withTemperature(0.0)
        ->withReplayMode()
        ->user('Classify: "Laravel 11 introduces new routing features"')
        ->answer(function ($a) {
            // Pest-style
            expect($a)
                ->toBeValidLlmJson()
                ->toHaveJsonPath('$.category', notEmpty: true)
                ->toHaveJsonPath('$.tags', notEmpty: true);

            // Classic-style (works too)
            $json = $a->json();
            expect($json['category'])->toBeString();
            expect($json['tags'])->toBeArray()->not->toBeEmpty();
        });
});
