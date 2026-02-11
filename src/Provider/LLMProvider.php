<?php

declare(strict_types=1);

namespace ProbeLLM\Provider;

use ProbeLLM\DTO\CompletionOptions;
use ProbeLLM\DTO\Message;
use ProbeLLM\DTO\ToolDefinition;

interface LLMProvider
{
    /**
     * Send a chat-completion request.
     *
     * @param list<Message>         $messages
     * @param list<ToolDefinition>  $tools    Function definitions.
     */
    public function complete(array $messages, array $tools, CompletionOptions $options): ProviderResult;
}
