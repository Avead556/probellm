<?php

declare(strict_types=1);

namespace ProbeLLM\Cassette;

use Closure;
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
     * @param Closure(): ProviderResult $callProvider Invokes the LLM provider.
     * @param Closure(): array<string, mixed> $buildRequest Builds the request payload for cassette storage.
     * @param array<string, mixed> $meta Extra metadata for the cassette.
     * @throws JsonException
     */
    public function resolve(
        string $cassetteKey,
        Closure $callProvider,
        Closure $buildRequest,
        array $meta = [],
    ): ProviderResult {
        if ($this->store->has($cassetteKey)) {
            return $this->store->load($cassetteKey);
        }

        $startTime = hrtime(true);
        $result = $callProvider();
        $elapsedMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        // Attach elapsed time to the result metadata.
        $resultWithTiming = new ProviderResult(
            $result->getContent(),
            $result->getToolCalls(),
            [...$result->getMeta(), 'elapsed_ms' => $elapsedMs],
        );

        if ($this->replayMode) {
            $this->store->save($cassetteKey, $buildRequest(), $resultWithTiming, $meta);
        }

        return $resultWithTiming;
    }
}
