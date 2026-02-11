<?php

declare(strict_types=1);

namespace ProbeLLM\DTO;

enum MessageRole: string
{
    case SYSTEM = 'system';
    case USER = 'user';
    case ASSISTANT = 'assistant';
    case TOOL = 'tool';

    public function isSystem(): bool
    {
        return $this === self::SYSTEM;
    }

    public function isUser(): bool
    {
        return $this === self::USER;
    }

    public function isAssistant(): bool
    {
        return $this === self::ASSISTANT;
    }

    public function isTool(): bool
    {
        return $this === self::TOOL;
    }
}
