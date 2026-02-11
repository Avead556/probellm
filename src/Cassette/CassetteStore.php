<?php

declare(strict_types=1);

namespace ProbeLLM\Cassette;

use ProbeLLM\DTO\CassetteData;
use ProbeLLM\Exception\CassetteMissingException;
use ProbeLLM\Provider\ProviderResult;
use Throwable;

final class CassetteStore
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim($directory ?? $this->defaultDirectory(), '/');
    }

    /**
     * Check if a cassette exists for the given key.
     */
    public function has(string $key): bool
    {
        return file_exists($this->path($key));
    }

    /**
     * Load a cassette and return a ProviderResult.
     *
     * @throws CassetteMissingException When cassette file is missing or unreadable.
     */
    public function load(string $key): ProviderResult
    {
        $path = $this->path($key);

        if (! file_exists($path)) {
            throw new CassetteMissingException(
                "Cassette not found: {$path}. "
                . 'Run tests with a configured provider to record cassettes, or provide the cassette file manually.',
            );
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new CassetteMissingException("Failed to read cassette file: {$path}");
        }

        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return CassetteData::fromArray($data, $path)->getResult();
    }

    /**
     * Record a cassette.
     *
     * @param array<string, mixed> $request  The full request payload (messages, options).
     * @param ProviderResult       $result   The provider response.
     * @param array<string, mixed> $meta     Extra metadata (model, temperature, provider name).
     */
    public function save(string $key, array $request, ProviderResult $result, array $meta = []): void
    {
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }

        $cassette = new CassetteData(
            request: $request,
            result: $result,
            meta: $meta,
        );

        $json = json_encode($cassette->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        file_put_contents($this->path($key), $json);
    }

    /**
     * Resolve file path for a given cache key.
     */
    public function path(string $key): string
    {
        return $this->directory . '/' . $key . '.json';
    }

    private function defaultDirectory(): string
    {
        return self::resolveBasePath() . '/tests/cassettes';
    }

    /**
     * Safely resolve project root — works with or without a booted Laravel app.
     */
    public static function resolveBasePath(): string
    {
        if (function_exists('base_path')) {
            try {
                return base_path();
            } catch (Throwable) {
            }
        }

        return (string) getcwd();
    }
}
