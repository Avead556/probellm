<?php

declare(strict_types=1);

namespace ProbeLLM\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class AgentTools
{
    /** @var list<class-string> */
    public array $toolClasses;

    /** @param class-string ...$toolClasses */
    public function __construct(string ...$toolClasses)
    {
        $this->toolClasses = array_values($toolClasses);
    }
}
