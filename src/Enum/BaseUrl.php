<?php

declare(strict_types=1);

namespace ProbeLLM\Enum;

enum BaseUrl: string
{
    case OPENAI = 'https://api.openai.com/v1';
    case OPEN_ROUTER = 'https://openrouter.ai/api/v1';
    case ANTHROPIC = 'https://api.anthropic.com';
    case ELEVENLABS = 'https://api.elevenlabs.io';
}
