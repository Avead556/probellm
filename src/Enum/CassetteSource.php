<?php

declare(strict_types=1);

namespace ProbeLLM\Enum;

enum CassetteSource: string
{
    case FIXTURE = 'fixture';
    case JUDGE = 'judge';
    case ELEVENLABS = 'elevenlabs';
}
