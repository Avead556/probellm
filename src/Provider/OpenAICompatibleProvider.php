<?php

declare(strict_types=1);

namespace ProbeLLM\Provider;

use ProbeLLM\DTO\CompletionOptions;
use ProbeLLM\DTO\OpenAI\OpenAIRequest;
use ProbeLLM\DTO\OpenAI\OpenAIResponse;
use ProbeLLM\DTO\ProviderResult;
use ProbeLLM\Enum\BaseUrl;
use ProbeLLM\Http\HttpClient;

/**
 * Provider for any OpenAI-compatible API (OpenAI, Azure OpenAI, OpenRouter, Groq, Together, Ollama, etc.).
 */
final readonly class OpenAICompatibleProvider implements LLMProvider
{
    private string $baseUrl;

    public function __construct(
        private string $apiKey,
        string $baseUrl = '',
        private int $timeout = 60,
    ) {
        $this->baseUrl = $baseUrl !== '' ? $baseUrl : BaseUrl::OPENAI->value;
    }

    public static function openAI(string $apiKey, int $timeout = 60): self
    {
        return new self($apiKey, BaseUrl::OPENAI->value, $timeout);
    }

    public static function openRouter(string $apiKey, int $timeout = 60): self
    {
        return new self($apiKey, BaseUrl::OPEN_ROUTER->value, $timeout);
    }

    public function complete(array $messages, array $tools, CompletionOptions $options): ProviderResult
    {
        $request = OpenAIRequest::from($options, $messages, $tools);

        $url = rtrim($this->baseUrl, '/') . '/chat/completions';

        $json = HttpClient::postJson(
            $url,
            ['Authorization: Bearer ' . $this->apiKey],
            $request->toArray(),
            $this->timeout,
            'OpenAI',
        );

        $response = OpenAIResponse::fromArray($json);
        $message = $response->getFirstChoice()->getMessage();

        return new ProviderResult(
            content: $message->getContent(),
            toolCalls: $message->getToolCalls(),
            meta: ['model' => $options->getModel(), 'usage' => $response->getUsage()->toArray()],
        );
    }
}
