<?php

declare(strict_types=1);

namespace ProbeLLM\Concerns;

use ProbeLLM\Attributes\AgentReplayMode;
use ProbeLLM\Cassette\CassetteStore;
use ReflectionClass;
use ReflectionMethod;

/**
 * Shared attribute resolution logic for test cases.
 */
trait ResolvesAttributes
{
    private ?CassetteStore $cassetteStore = null;

    /**
     * Override to change cassette directory.
     */
    protected function cassettesDirectory(): ?string
    {
        return null;
    }

    /**
     * Resolve an attribute value with method-first, then class, then default.
     */
    private function resolveAttribute(
        ReflectionClass $classRef,
        ReflectionMethod $methodRef,
        string $attributeClass,
        string $property,
        mixed $default = null,
    ): mixed {
        $methodAttrs = $methodRef->getAttributes($attributeClass);

        if ($methodAttrs !== []) {
            return end($methodAttrs)->newInstance()->$property;
        }

        $classAttrs = $classRef->getAttributes($attributeClass);

        if ($classAttrs !== []) {
            return end($classAttrs)->newInstance()->$property;
        }

        return $default;
    }

    /**
     * Check whether an attribute is present on method or class.
     */
    private function hasAttribute(
        ReflectionClass $classRef,
        ReflectionMethod $methodRef,
        string $attributeClass,
    ): bool {
        return $methodRef->getAttributes($attributeClass) !== []
            || $classRef->getAttributes($attributeClass) !== [];
    }

    private function resolveReplayMode(ReflectionClass $classRef, ReflectionMethod $methodRef): bool
    {
        return $this->hasAttribute($classRef, $methodRef, AgentReplayMode::class);
    }

    private function getCassetteStore(): CassetteStore
    {
        if ($this->cassetteStore === null) {
            $this->cassetteStore = new CassetteStore($this->cassettesDirectory());
        }

        return $this->cassetteStore;
    }
}
