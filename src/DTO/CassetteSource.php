<?php

declare(strict_types=1);

namespace ProbeLLM\DTO;

enum CassetteSource: string
{
    case FIXTURE = 'fixture';
    case JUDGE = 'judge';
}
