<?php

declare(strict_types=1);

namespace ProbeLLM\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class ElevenLabsAgentId
{
    public string $agentId;

    public function __construct(
        string $agentId = '',
        string $env = '',
    ) {
        $this->agentId = $env !== '' ? (getenv($env) ?: '') : $agentId;
    }
}
