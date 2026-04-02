<?php

declare(strict_types=1);

namespace ProbeLLM\Tests\Unit;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use ProbeLLM\DSL\AnswerExpectations;
use ProbeLLM\DTO\ProviderResult;
use ProbeLLM\Provider\NullProvider;

class JsonSchemaTest extends TestCase
{
    public function test_valid_object_passes(): void
    {
        $this->makeJson(['name' => 'Alice', 'age' => 30])
            ->assertJsonSchema([
                'type' => 'object',
                'required' => ['name', 'age'],
                'properties' => [
                    'name' => ['type' => 'string'],
                    'age' => ['type' => 'integer'],
                ],
            ]);
        $this->addToAssertionCount(1);
    }

    public function test_missing_required_field_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("missing required property 'age'");

        $this->makeJson(['name' => 'Alice'])
            ->assertJsonSchema([
                'type' => 'object',
                'required' => ['name', 'age'],
                'properties' => [
                    'name' => ['type' => 'string'],
                    'age' => ['type' => 'integer'],
                ],
            ]);
    }

    public function test_wrong_type_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("expected type 'string'");

        $this->makeJson(['name' => 123])
            ->assertJsonSchema([
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
            ]);
    }

    public function test_enum_passes(): void
    {
        $this->makeJson(['status' => 'active'])
            ->assertJsonSchema([
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => 'string', 'enum' => ['active', 'inactive']],
                ],
            ]);
        $this->addToAssertionCount(1);
    }

    public function test_enum_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('not in enum');

        $this->makeJson(['status' => 'deleted'])
            ->assertJsonSchema([
                'type' => 'object',
                'properties' => [
                    'status' => ['enum' => ['active', 'inactive']],
                ],
            ]);
    }

    public function test_string_min_length(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('below minimum 5');

        $this->makeJson(['name' => 'AB'])
            ->assertJsonSchema([
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'minLength' => 5],
                ],
            ]);
    }

    public function test_string_pattern(): void
    {
        $this->makeJson(['email' => 'test@example.com'])
            ->assertJsonSchema([
                'type' => 'object',
                'properties' => [
                    'email' => ['type' => 'string', 'pattern' => '^[^@]+@[^@]+$'],
                ],
            ]);
        $this->addToAssertionCount(1);
    }

    public function test_numeric_range(): void
    {
        $this->makeJson(['score' => 0.5])
            ->assertJsonSchema([
                'type' => 'object',
                'properties' => [
                    'score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                ],
            ]);
        $this->addToAssertionCount(1);
    }

    public function test_numeric_above_maximum_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('exceeds maximum');

        $this->makeJson(['score' => 1.5])
            ->assertJsonSchema([
                'type' => 'object',
                'properties' => [
                    'score' => ['type' => 'number', 'maximum' => 1],
                ],
            ]);
    }

    public function test_array_with_items(): void
    {
        $this->makeJson(['tags' => ['php', 'laravel']])
            ->assertJsonSchema([
                'type' => 'object',
                'properties' => [
                    'tags' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'minItems' => 1,
                    ],
                ],
            ]);
        $this->addToAssertionCount(1);
    }

    public function test_array_item_wrong_type_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("expected type 'string'");

        $this->makeJson(['tags' => ['php', 123]])
            ->assertJsonSchema([
                'type' => 'object',
                'properties' => [
                    'tags' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
            ]);
    }

    public function test_nested_object(): void
    {
        $this->makeJson([
            'user' => ['name' => 'Alice', 'age' => 30],
        ])->assertJsonSchema([
            'type' => 'object',
            'properties' => [
                'user' => [
                    'type' => 'object',
                    'required' => ['name'],
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'age' => ['type' => 'integer'],
                    ],
                ],
            ],
        ]);
        $this->addToAssertionCount(1);
    }

    private function makeJson(array $data): AnswerExpectations
    {
        return new AnswerExpectations(
            result: new ProviderResult(json_encode($data, JSON_THROW_ON_ERROR)),
            provider: new NullProvider(),
        );
    }
}
