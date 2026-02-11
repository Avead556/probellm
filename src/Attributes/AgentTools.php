<?php

declare(strict_types=1);

namespace ProbeLLM\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class AgentTools
{
    /** @var array<class-string> */
    public readonly array $toolClasses;

    public function __construct(string ...$toolClasses)
    {
        $this->toolClasses = $toolClasses;
    }
}
