<?php

declare(strict_types=1);

namespace ProbeLLM\Tools;

use ProbeLLM\DTO\ToolDefinition;

final class SearchTool implements ToolContract
{
    public static function name(): string
    {
        return 'search';
    }

    public static function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'search',
            description: 'Search for information by query',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'The search query',
                    ],
                ],
                'required' => ['query'],
            ],
        );
    }
}
