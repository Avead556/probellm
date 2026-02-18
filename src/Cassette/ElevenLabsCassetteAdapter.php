<?php

declare(strict_types=1);

namespace ProbeLLM\Cassette;

use JsonException;
use ProbeLLM\DTO\ElevenLabs\SimulationResponse;
use ProbeLLM\DTO\ProviderResult;

/**
 * Bridges SimulationResponse ↔ ProviderResult so the existing CassetteResolver can be reused.
 *
 * The full SimulationResponse is serialized as JSON into ProviderResult::$content.
 */
final class ElevenLabsCassetteAdapter
{
    /**
     * @throws JsonException
     */
    public static function toProviderResult(SimulationResponse $response): ProviderResult
    {
        return new ProviderResult(
            content: json_encode($response->toArray(), JSON_THROW_ON_ERROR),
        );
    }

    public static function toSimulationResponse(ProviderResult $result): SimulationResponse
    {
        $data = json_decode($result->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return SimulationResponse::fromArray($data);
    }
}
