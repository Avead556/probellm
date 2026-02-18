<?php

declare(strict_types=1);

namespace ProbeLLM\Provider;

use ProbeLLM\DTO\Anthropic\AnthropicRequest;
use ProbeLLM\DTO\Anthropic\AnthropicResponse;
use ProbeLLM\DTO\CompletionOptions;
use ProbeLLM\DTO\ProviderResult;
use ProbeLLM\Enum\BaseUrl;
use ProbeLLM\Http\HttpClient;

/**
 * Native Anthropic API provider (Claude models).
 */
final readonly class AnthropicProvider implements LLMProvider
{
    private string $baseUrl;

    public function __construct(
        private string $apiKey,
        string $baseUrl = '',
        private string $apiVersion = '2023-06-01',
        private int $maxTokens = 4096,
        private int $timeout = 60,
    ) {
        $this->baseUrl = $baseUrl !== '' ? $baseUrl : BaseUrl::ANTHROPIC->value;
    }

    public function complete(array $messages, array $tools, CompletionOptions $options): ProviderResult
    {
        $request = AnthropicRequest::from($options, $this->maxTokens, $messages, $tools);

        $url = rtrim($this->baseUrl, '/') . '/v1/messages';

        $json = HttpClient::postJson(
            $url,
            [
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: ' . $this->apiVersion,
            ],
            $request->toArray(),
            $this->timeout,
            'Anthropic',
        );

        $response = AnthropicResponse::fromArray($json);

        return new ProviderResult(
            content: $response->getTextContent(),
            toolCalls: $response->getToolCalls(),
            meta: ['model' => $options->getModel(), 'usage' => $response->getUsage()->toArray()],
        );
    }
}
