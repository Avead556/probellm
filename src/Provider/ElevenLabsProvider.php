<?php

declare(strict_types=1);

namespace ProbeLLM\Provider;

use ProbeLLM\DTO\ElevenLabs\SimulationRequest;
use ProbeLLM\DTO\ElevenLabs\SimulationResponse;
use ProbeLLM\Enum\BaseUrl;
use ProbeLLM\Http\HttpClient;

final readonly class ElevenLabsProvider implements ElevenLabsConvaiProvider
{
    private string $baseUrl;

    public function __construct(
        private string $apiKey,
        string $baseUrl = '',
        private int $timeout = 120,
    ) {
        $this->baseUrl = $baseUrl !== '' ? $baseUrl : BaseUrl::ELEVENLABS->value;
    }

    public function simulateConversation(SimulationRequest $request): SimulationResponse
    {
        $url = rtrim($this->baseUrl, '/')
            . '/v1/convai/agents/'
            . urlencode($request->getAgentId())
            . '/simulate-conversation';

        $json = HttpClient::postJson(
            $url,
            ['xi-api-key: ' . $this->apiKey],
            $request->toArray(),
            $this->timeout,
            'ElevenLabs',
        );

        return SimulationResponse::fromArray($json);
    }
}
