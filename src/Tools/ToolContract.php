<?php

declare(strict_types=1);

namespace ProbeLLM\Tools;

use ProbeLLM\DTO\ToolDefinition;

interface ToolContract
{
    /**
     * Tool name used in function-calling (e.g. "search").
     */
    public static function name(): string;

    /**
     * JSON-schema compatible definition for LLM function calling.
     */
    public static function definition(): ToolDefinition;
}
