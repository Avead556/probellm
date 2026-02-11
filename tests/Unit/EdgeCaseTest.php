<?php

declare(strict_types=1);

namespace ProbeLLM\Tests\Unit;

use ProbeLLM\Cassette\CassetteResolver;
use ProbeLLM\Cassette\CassetteStore;
use ProbeLLM\Cassette\Hasher;
use ProbeLLM\DSL\AnswerExpectations;
use ProbeLLM\DSL\DialogScenario;
use ProbeLLM\DTO\CassetteData;
use ProbeLLM\DTO\CompletionOptions;
use ProbeLLM\DTO\JudgeVerdict;
use ProbeLLM\DTO\Message;
use ProbeLLM\Exception\ConfigurationException;
use ProbeLLM\Exception\InvalidResponseException;
use ProbeLLM\Exception\ToolResolutionException;
use ProbeLLM\Provider\NullProvider;
use ProbeLLM\Provider\ProviderResult;
use PHPUnit\Framework\TestCase;
use stdClass;

class EdgeCaseTest extends TestCase
{
    public function test_cassette_resolver_records_on_replay_mode(): void
    {
        $tmpDir = sys_get_temp_dir() . '/ai-unit-test-' . uniqid();
        mkdir($tmpDir, 0755, true);

        $store = new CassetteStore($tmpDir);
        $resolver = new CassetteResolver($store, replayMode: true);

        $result = $resolver->resolve(
            'test-key',
            fn(): ProviderResult => new ProviderResult('recorded'),
            fn(): array => ['messages' => []],
        );

        self::assertSame('recorded', $result->getContent());
        self::assertTrue($store->has('test-key'));
    }

    public function test_null_provider_throws_configuration_exception(): void
    {
        $provider = new NullProvider();

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('NullProvider');

        $provider->complete([], [], new CompletionOptions());
    }

    public function test_json_accessor_throws_on_invalid_json(): void
    {
        $result = new ProviderResult('not json at all');
        $expectations = new AnswerExpectations(
            result: $result,
            provider: new NullProvider(),
        );

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('not valid JSON');

        $expectations->json();
    }

    public function test_tool_class_not_implementing_contract_throws(): void
    {
        $scenario = new DialogScenario(new NullProvider(), new CassetteStore());

        $this->expectException(ToolResolutionException::class);
        $this->expectExceptionMessage('must implement');

        $scenario
            ->withTools([stdClass::class])
            ->user('test')
            ->answer(fn() => null);
    }

    public function test_tool_result_without_previous_answer_throws(): void
    {
        $scenario = new DialogScenario(new NullProvider(), new CassetteStore());

        $this->expectException(ToolResolutionException::class);
        $this->expectExceptionMessage('no previous answer() call');

        $scenario->toolResult('search', ['query' => 'test']);
    }

    public function test_tool_result_with_non_existent_tool_throws(): void
    {
        $tmpDir = sys_get_temp_dir() . '/ai-unit-test-' . uniqid();
        mkdir($tmpDir, 0755, true);

        $store = new CassetteStore($tmpDir);
        $scenario = new DialogScenario(new NullProvider(), $store);

        $messages = [Message::user('test')];
        $cassetteKey = Hasher::make('', $messages, 'gpt-4o', 0.7, [], '', 0);

        $store->save(
            $cassetteKey,
            ['messages' => [], 'options' => [], 'tools' => []],
            new ProviderResult('response text'),
            [],
        );

        $this->expectException(ToolResolutionException::class);
        $this->expectExceptionMessage("tool 'nonexistent' was not called");

        $scenario
            ->user('test')
            ->answer(fn() => null)
            ->toolResult('nonexistent', ['data' => 'test']);
    }

    public function test_judge_verdict_from_invalid_json_throws(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('invalid response');

        JudgeVerdict::fromJson('this is not json');
    }

    public function test_judge_verdict_without_pass_key_throws(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage("'pass' key");

        JudgeVerdict::fromJson('{"reason":"x"}');
    }

    public function test_cassette_data_without_response_key_throws(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage("'response' key");

        CassetteData::fromArray([]);
    }

    public function test_hasher_is_deterministic(): void
    {
        $messages = [Message::system('You are helpful.'), Message::user('Hello')];

        $hash1 = Hasher::make('system', $messages, 'gpt-4o', 0.7, [], 'TestClass::testMethod', 0);
        $hash2 = Hasher::make('system', $messages, 'gpt-4o', 0.7, [], 'TestClass::testMethod', 0);

        self::assertSame($hash1, $hash2);
    }

    public function test_hasher_produces_different_hash_for_different_input(): void
    {
        $messages = [Message::user('Hello')];

        $hash1 = Hasher::make('system', $messages, 'gpt-4o', 0.7, [], 'Test::method', 0);
        $hash2 = Hasher::make('different-system', $messages, 'gpt-4o', 0.7, [], 'Test::method', 0);
        $hash3 = Hasher::make('system', $messages, 'gpt-4o', 0.7, [], 'Test::method', 1);

        self::assertNotSame($hash1, $hash2);
        self::assertNotSame($hash1, $hash3);
    }
}
