<?php

declare(strict_types=1);

namespace ProbeLLM\DTO;

use ProbeLLM\Exception\InvalidResponseException;

/**
 * A single row from an evaluation dataset (JSONL/CSV).
 *
 * Provides typed accessors for common fields and generic access for arbitrary keys.
 * All array access is encapsulated here — no raw array access in test code.
 */
final readonly class DatasetRow
{
    /** @var list<string> Common field aliases for "input". */
    private const array INPUT_ALIASES = ['input', 'question', 'prompt', 'query', 'user'];

    /** @var list<string> Common field aliases for "expected". */
    private const array EXPECTED_ALIASES = ['expected', 'answer', 'output', 'target', 'response'];

    /**
     * @param array<string, mixed> $data  Raw row data.
     * @param int                  $index Zero-based row index in the dataset.
     */
    public function __construct(
        private array $data,
        private int $index,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, int $index = 0): self
    {
        return new self($data, $index);
    }

    /**
     * The user input / question / prompt.
     *
     * Looks for keys: input, question, prompt, query, user.
     */
    public function getInput(): string
    {
        return $this->resolveAlias(self::INPUT_ALIASES, 'input');
    }

    /**
     * The expected output / answer / target.
     *
     * Looks for keys: expected, answer, output, target, response.
     */
    public function getExpected(): string
    {
        return $this->resolveAlias(self::EXPECTED_ALIASES, 'expected');
    }

    /**
     * Get a value by key with an optional default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Get a string value by key.
     *
     * @throws InvalidResponseException If the key is missing or not a string.
     */
    public function getString(string $key): string
    {
        if (! array_key_exists($key, $this->data)) {
            throw new InvalidResponseException(
                "Dataset row [{$this->index}]: missing key '{$key}'.",
            );
        }

        if (! is_string($this->data[$key])) {
            throw new InvalidResponseException(
                "Dataset row [{$this->index}]: key '{$key}' is not a string.",
            );
        }

        return $this->data[$key];
    }

    /**
     * Get an integer value by key.
     *
     * @throws InvalidResponseException If the key is missing or not an integer.
     */
    public function getInt(string $key): int
    {
        if (! array_key_exists($key, $this->data)) {
            throw new InvalidResponseException(
                "Dataset row [{$this->index}]: missing key '{$key}'.",
            );
        }

        if (! is_int($this->data[$key])) {
            throw new InvalidResponseException(
                "Dataset row [{$this->index}]: key '{$key}' is not an integer.",
            );
        }

        return $this->data[$key];
    }

    /**
     * Get a float value by key.
     *
     * @throws InvalidResponseException If the key is missing or not numeric.
     */
    public function getFloat(string $key): float
    {
        if (! array_key_exists($key, $this->data)) {
            throw new InvalidResponseException(
                "Dataset row [{$this->index}]: missing key '{$key}'.",
            );
        }

        if (! is_int($this->data[$key]) && ! is_float($this->data[$key])) {
            throw new InvalidResponseException(
                "Dataset row [{$this->index}]: key '{$key}' is not numeric.",
            );
        }

        return (float) $this->data[$key];
    }

    /**
     * Get a boolean value by key.
     *
     * @throws InvalidResponseException If the key is missing or not a boolean.
     */
    public function getBool(string $key): bool
    {
        if (! array_key_exists($key, $this->data)) {
            throw new InvalidResponseException(
                "Dataset row [{$this->index}]: missing key '{$key}'.",
            );
        }

        if (! is_bool($this->data[$key])) {
            throw new InvalidResponseException(
                "Dataset row [{$this->index}]: key '{$key}' is not a boolean.",
            );
        }

        return $this->data[$key];
    }

    /**
     * Check if a key exists in the row.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Zero-based index of this row in the dataset.
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * All keys available in this row.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @param list<string> $aliases
     */
    private function resolveAlias(array $aliases, string $label): string
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $this->data)) {
                $value = $this->data[$alias];

                if (! is_string($value)) {
                    throw new InvalidResponseException(
                        "Dataset row [{$this->index}]: '{$alias}' is not a string.",
                    );
                }

                return $value;
            }
        }

        throw new InvalidResponseException(
            "Dataset row [{$this->index}]: no {$label} field found. "
            . 'Expected one of: ' . implode(', ', $aliases) . '. '
            . 'Available keys: ' . implode(', ', array_keys($this->data)) . '.',
        );
    }
}
