<?php

declare(strict_types=1);

namespace ProbeLLM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProbeLLM\Cassette\CassetteResolver;
use ProbeLLM\Cassette\CassetteStore;
use ProbeLLM\Cassette\Hasher;
use ProbeLLM\DTO\CassetteData;
use ProbeLLM\DTO\Message;
use ProbeLLM\DTO\ProviderResult;
use ProbeLLM\Exception\InvalidResponseException;

class CassetteTest extends TestCase
{
    public function test_resolver_records_on_replay_mode(): void
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

        $this->assertSame('recorded', $result->getContent());
        $this->assertTrue($store->has('test-key'));
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

        $this->assertSame($hash1, $hash2);
    }

    public function test_hasher_produces_different_hash_for_different_input(): void
    {
        $messages = [Message::user('Hello')];

        $hash1 = Hasher::make('system', $messages, 'gpt-4o', 0.7, [], 'Test::method', 0);
        $hash2 = Hasher::make('different-system', $messages, 'gpt-4o', 0.7, [], 'Test::method', 0);
        $hash3 = Hasher::make('system', $messages, 'gpt-4o', 0.7, [], 'Test::method', 1);

        $this->assertNotSame($hash1, $hash2);
        $this->assertNotSame($hash1, $hash3);
    }
}
