<?php

declare(strict_types=1);

namespace ProbeLLM\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class JudgeTemperature
{
    public function __construct(
        public float $value,
    ) {}
}
