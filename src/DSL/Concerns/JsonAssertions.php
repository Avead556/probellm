<?php

declare(strict_types=1);

namespace ProbeLLM\DSL\Concerns;

use PHPUnit\Framework\Assert;
use ProbeLLM\Exception\InvalidResponseException;

/**
 * JSON and JSON Schema assertions for LLM responses.
 */
trait JsonAssertions
{
    abstract private function getContent(): string;

    /**
     * Decode assistant content as JSON array.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $decoded = json_decode(self::stripMarkdownFences($this->getContent()), true);

        if (! is_array($decoded)) {
            throw new InvalidResponseException(
                'Assistant content is not valid JSON: ' . mb_substr($this->getContent(), 0, 200),
            );
        }

        return $decoded;
    }

    /**
     * Assert that assistant content is valid JSON.
     */
    public function assertJson(): self
    {
        $decoded = json_decode(self::stripMarkdownFences($this->getContent()), true);

        Assert::assertNotNull(
            $decoded,
            'Expected assistant content to be valid JSON, got: ' . mb_substr($this->getContent(), 0, 200),
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
     * Assert the response is valid JSON conforming to a JSON Schema.
     *
     * Supports: type, required, properties, items, enum, minimum, maximum,
     * minLength, maxLength, pattern, minItems, maxItems.
     *
     * @param array<string, mixed> $schema JSON Schema definition.
     */
    public function assertJsonSchema(array $schema): self
    {
        $data = $this->json();
        $errors = self::validateSchema($data, $schema, '$');

        if ($errors !== []) {
            Assert::fail(
                "JSON Schema validation failed:\n- " . implode("\n- ", $errors),
            );
        }

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
     * Recursively validate data against a JSON Schema subset.
     *
     * @param array<string, mixed> $schema
     * @return list<string> Validation error messages.
     */
    private static function validateSchema(mixed $data, array $schema, string $path): array
    {
        $errors = [];

        // Type check.
        if (isset($schema['type'])) {
            $valid = match ($schema['type']) {
                'string' => is_string($data),
                'number' => is_int($data) || is_float($data),
                'integer' => is_int($data),
                'boolean' => is_bool($data),
                'array' => is_array($data) && array_is_list($data),
                'object' => is_array($data) && ! array_is_list($data),
                'null' => $data === null,
                default => true,
            };

            if (! $valid) {
                $actual = get_debug_type($data);
                $errors[] = "{$path}: expected type '{$schema['type']}', got '{$actual}'.";

                return $errors;
            }
        }

        // Enum.
        if (isset($schema['enum']) && is_array($schema['enum'])) {
            if (! in_array($data, $schema['enum'], true)) {
                $allowed = implode(', ', array_map(static fn($v) => json_encode($v), $schema['enum']));
                $errors[] = "{$path}: value " . json_encode($data) . " not in enum [{$allowed}].";
            }
        }

        // String constraints.
        if (is_string($data)) {
            if (isset($schema['minLength']) && mb_strlen($data) < $schema['minLength']) {
                $errors[] = "{$path}: string length " . mb_strlen($data) . " is below minimum {$schema['minLength']}.";
            }

            if (isset($schema['maxLength']) && mb_strlen($data) > $schema['maxLength']) {
                $errors[] = "{$path}: string length " . mb_strlen($data) . " exceeds maximum {$schema['maxLength']}.";
            }

            if (isset($schema['pattern']) && ! preg_match('/' . $schema['pattern'] . '/', $data)) {
                $errors[] = "{$path}: string does not match pattern '{$schema['pattern']}'.";
            }
        }

        // Numeric constraints.
        if (is_int($data) || is_float($data)) {
            if (isset($schema['minimum']) && $data < $schema['minimum']) {
                $errors[] = "{$path}: value {$data} is below minimum {$schema['minimum']}.";
            }

            if (isset($schema['maximum']) && $data > $schema['maximum']) {
                $errors[] = "{$path}: value {$data} exceeds maximum {$schema['maximum']}.";
            }
        }

        // Object: required + properties.
        if (is_array($data) && ! array_is_list($data)) {
            if (isset($schema['required']) && is_array($schema['required'])) {
                foreach ($schema['required'] as $key) {
                    if (! array_key_exists($key, $data)) {
                        $errors[] = "{$path}: missing required property '{$key}'.";
                    }
                }
            }

            if (isset($schema['properties']) && is_array($schema['properties'])) {
                foreach ($schema['properties'] as $key => $propSchema) {
                    if (array_key_exists($key, $data)) {
                        $errors = [...$errors, ...self::validateSchema($data[$key], $propSchema, "{$path}.{$key}")];
                    }
                }
            }
        }

        // Array: items + minItems + maxItems.
        if (is_array($data) && array_is_list($data)) {
            if (isset($schema['minItems']) && count($data) < $schema['minItems']) {
                $errors[] = "{$path}: array has " . count($data) . " items, minimum is {$schema['minItems']}.";
            }

            if (isset($schema['maxItems']) && count($data) > $schema['maxItems']) {
                $errors[] = "{$path}: array has " . count($data) . " items, maximum is {$schema['maxItems']}.";
            }

            if (isset($schema['items']) && is_array($schema['items'])) {
                foreach ($data as $i => $item) {
                    $errors = [...$errors, ...self::validateSchema($item, $schema['items'], "{$path}[{$i}]")];
                }
            }
        }

        return $errors;
    }

    /**
     * Resolve a simple JSONPath like $.a.b[0].c against an array.
     *
     * @param array<string, mixed> $data
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
