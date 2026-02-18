<?php

declare(strict_types=1);

namespace ProbeLLM\Provider;

use ProbeLLM\DTO\ElevenLabs\SimulationRequest;
use ProbeLLM\DTO\ElevenLabs\SimulationResponse;

interface ElevenLabsConvaiProvider
{
    public function simulateConversation(SimulationRequest $request): SimulationResponse;
}
