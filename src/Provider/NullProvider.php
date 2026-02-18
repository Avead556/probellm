<?php

declare(strict_types=1);

namespace ProbeLLM\Provider;

use ProbeLLM\DTO\CompletionOptions;
use ProbeLLM\DTO\ProviderResult;
use ProbeLLM\Exception\ConfigurationException;

/**
 * Provider that always throws — used as default when replay is expected.
 */
final class NullProvider implements LLMProvider
{
    public function complete(array $messages, array $tools, CompletionOptions $options): ProviderResult
    {
        throw new ConfigurationException(
            'NullProvider: no real LLM provider configured. '
            . 'Set a provider via AgentTestCase::resolveProvider() or use replay mode with a recorded cassette.',
        );
    }
}
