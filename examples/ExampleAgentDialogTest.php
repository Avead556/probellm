<?php

declare(strict_types=1);

namespace ProbeLLM\Examples;

use ProbeLLM\AgentTestCase;
use ProbeLLM\Attributes\AgentModel;
use ProbeLLM\Attributes\AgentReplayMode;
use ProbeLLM\Attributes\AgentSystem;
use ProbeLLM\Attributes\AgentSystemFile;
use ProbeLLM\Attributes\AgentTemperature;
use ProbeLLM\Attributes\AgentTools;
use ProbeLLM\Attributes\JudgeModel;
use ProbeLLM\Attributes\JudgeTemperature;
use ProbeLLM\DSL\AnswerExpectations;
use ProbeLLM\Provider\AnthropicProvider;
use ProbeLLM\Provider\LLMProvider;
use ProbeLLM\Provider\OpenAICompatibleProvider;
use ProbeLLM\Tools\SearchTool;

#[AgentSystem('You are a helpful assistant that always responds in valid JSON.')]
#[AgentModel('openai/gpt-4o')]
#[AgentTemperature(0.0)]
#[AgentReplayMode]
#[JudgeModel('openai/gpt-4o-mini')]
#[JudgeTemperature(0.0)]
class ExampleAgentDialogTest extends AgentTestCase
{
    protected function resolveProvider(): LLMProvider
    {
        $apiKey = getenv('LLM_API_KEY') ?: '';

        if ($apiKey === '') {
            return parent::resolveProvider();
        }

        $baseUrl = getenv('LLM_BASE_URL') ?: '';

        if ($baseUrl !== '') {
            return new OpenAICompatibleProvider($apiKey, $baseUrl);
        }

        return OpenAICompatibleProvider::openRouter($apiKey);
    }

    protected function resolveJudgeProvider(): ?LLMProvider
    {
        $apiKey = getenv('ANTHROPIC_API_KEY') ?: '';

        if ($apiKey === '') {
            return null;
        }

        return new AnthropicProvider($apiKey);
    }

    public function test_single_turn_json_response(): void
    {
        $this->dialog()
            ->user('Return a JSON object with key "greeting" and value "hello".')
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.greeting', equals: 'hello');
            });
    }

    public function test_multi_turn_conversation(): void
    {
        $this->dialog()
            ->user('Return JSON: {"count": 1}')
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.count', equals: 1);
            })
            ->user('Now increment the count and return JSON: {"count": 2}')
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.count', equals: 2);
            });
    }

    #[AgentSystem('You are a calculator. Always respond in JSON with key "result".')]
    public function test_method_level_system_prompt_override(): void
    {
        $this->dialog()
            ->user('What is 2 + 2?')
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.result', notEmpty: true);
            });
    }

    public function test_dsl_system_override(): void
    {
        $this->dialog()
            ->system('You are a poet. Respond with JSON: {"poem": "..."}.')
            ->user('Write a haiku about PHP.')
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.poem', notEmpty: true);
            });
    }

    #[AgentSystemFile('tests/fixtures/prompts/translator.txt')]
    public function test_system_prompt_from_file(): void
    {
        $this->dialog()
            ->user('Translate "hello" to Spanish.')
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.translation', notEmpty: true);
            });
    }

    #[AgentModel('openai/gpt-4o-mini')]
    #[AgentTemperature(0.5)]
    public function test_method_level_model_and_temperature_override(): void
    {
        $this->dialog()
            ->user('Return JSON: {"model_test": true}')
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.model_test', equals: true);
            });
    }

    public function test_json_path_string_assertions(): void
    {
        $this->dialog()
            ->user('Return JSON: {"message": "Hello, World!"}')
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.message', contains: 'Hello')
                    ->assertJsonPath('$.message', notContains: 'Goodbye');
            });
    }

    public function test_json_path_nested_array_access(): void
    {
        $this->dialog()
            ->user('Return JSON: {"items": [{"name": "first"}, {"name": "second"}]}')
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.items[0].name', equals: 'first')
                    ->assertJsonPath('$.items[1].name', equals: 'second');
            });
    }

    public function test_json_accessor(): void
    {
        $this->dialog()
            ->user('Return JSON: {"a": 1, "b": 2}')
            ->answer(function (AnswerExpectations $a) {
                $json = $a->json();
                self::assertArrayHasKey('a', $json);
                self::assertArrayHasKey('b', $json);
                self::assertSame(3, $json['a'] + $json['b']);
            });
    }

    #[AgentTools(SearchTool::class)]
    public function test_tool_calling(): void
    {
        $this->dialog()
            ->user('Search for "PHP 8.5 release date".')
            ->answer(function (AnswerExpectations $a) {
                $a->assertToolCalled('search')
                    ->assertToolArgs('search', function (array $args) {
                        self::assertArrayHasKey('query', $args);
                        self::assertStringContainsString('PHP', $args['query']);
                    });
            });
    }

    #[AgentTools(SearchTool::class)]
    public function test_tool_result_round_trip(): void
    {
        $this->dialog()
            ->user('Search for "Laravel 12".')
            ->answer(function (AnswerExpectations $a) {
                $a->assertToolCalled('search');

                $calls = $a->toolCalls();
                self::assertCount(1, $calls);
                self::assertSame('search', $calls[0]->getName());
            })
            ->toolResult('search', [
                'results' => [
                    ['title' => 'Laravel 12 Released', 'url' => 'https://laravel.com/blog/12'],
                ],
            ])
            ->answer(function (AnswerExpectations $a) {
                $message = $a->lastMessage();
                self::assertNotEmpty($message);
            });
    }

    #[AgentSystem('You are a nutrition expert. Always respond in JSON with key "advice".')]
    public function test_judge_uses_class_level_attributes(): void
    {
        $this->dialog()
            ->user('Give me a short healthy breakfast suggestion.')
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertByPrompt('The advice is about a healthy breakfast, not lunch or dinner')
                    ->assertByPrompt('The response does not recommend anything with excessive sugar');
            });
    }

    #[JudgeModel('openai/gpt-4o')]
    public function test_judge_model_override_via_method_attribute(): void
    {
        $this->dialog()
            ->user('Return JSON: {"color": "blue", "hex": "#0000FF"}')
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertByPrompt('The hex value correctly corresponds to blue color');
            });
    }

    public function test_judge_per_call_model_override(): void
    {
        $this->dialog()
            ->user('Return JSON: {"lang": "PHP", "version": 8}')
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertByPrompt(
                        criteria: 'The language is PHP and version is a number',
                        model: 'openai/gpt-4o',
                        temperature: 0.1,
                    );
            });
    }

    public function test_judge_combined_with_classic_assertions_multi_turn(): void
    {
        $this->dialog()
            ->system('You are a travel guide. Respond in JSON: {"destination": "...", "tips": ["..."]}.')
            ->user('Suggest a destination in Japan.')
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.destination', notEmpty: true)
                    ->assertJsonPath('$.tips', notEmpty: true)
                    ->assertByPrompt('The destination is actually located in Japan');
            })
            ->user('Now suggest a destination in Italy.')
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.destination', notEmpty: true)
                    ->assertByPrompt('The destination is in Italy, not Japan or any other country');
            });
    }
}
