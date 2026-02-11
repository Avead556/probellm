<?php

declare(strict_types=1);

namespace ProbeLLM\Cassette;

use ProbeLLM\Provider\ProviderResult;

final class CassetteResolver
{
    public function __construct(
        private readonly CassetteStore $store,
        private readonly bool $replayMode,
    ) {}

    /**
     * @param string                              $cassetteKey   SHA256 cassette key.
     * @param callable(): ProviderResult          $callProvider  Invokes the LLM provider.
     * @param callable(): array<string, mixed>    $buildRequest  Builds the request payload for cassette storage.
     * @param array<string, mixed>                $meta          Extra metadata for the cassette.
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
