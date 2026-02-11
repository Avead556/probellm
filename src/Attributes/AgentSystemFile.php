<?php

declare(strict_types=1);

namespace ProbeLLM\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class AgentSystemFile
{
    public function __construct(
        public readonly string $path,
    ) {}
}
