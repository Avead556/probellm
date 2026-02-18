<?php

declare(strict_types=1);

namespace ProbeLLM\Cassette;

use JsonException;
use ProbeLLM\DTO\ProviderResult;

final readonly class CassetteResolver
{
    public function __construct(
        private CassetteStore $store,
        private bool $replayMode,
    ) {}

    /**
     * @param string $cassetteKey SHA256 cassette key.
     * @param callable(): ProviderResult $callProvider Invokes the LLM provider.
     * @param callable(): array<string, mixed> $buildRequest Builds the request payload for cassette storage.
     * @param array<string, mixed> $meta Extra metadata for the cassette.
     * @throws JsonException
     */
    public function resolve(
        string $cassetteKey,
        callable $callProvider,
        callable $buildRequest,
        array $meta = [],
    ): ProviderResult {
        if ($this->store->has($cassetteKey)) {
            return $this->store->load($cassetteKey);
        }

        $result = $callProvider();

        if ($this->replayMode) {
            $this->store->save($cassetteKey, $buildRequest(), $result, $meta);
        }

        return $result;
    }
}
