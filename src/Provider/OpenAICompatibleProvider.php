<?php

declare(strict_types=1);

namespace ProbeLLM\Provider;

use ProbeLLM\DTO\CompletionOptions;
use ProbeLLM\DTO\OpenAI\OpenAIRequest;
use ProbeLLM\DTO\OpenAI\OpenAIResponse;
use ProbeLLM\Exception\ConfigurationException;
use ProbeLLM\Exception\ProviderException;
use JsonException;

/**
 * Provider for any OpenAI-compatible API (OpenAI, Azure OpenAI, OpenRouter, Groq, Together, Ollama, etc.).
 */
final class OpenAICompatibleProvider implements LLMProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.openai.com/v1',
        private readonly int $timeout = 60,
    ) {
        if (! extension_loaded('curl')) {
            throw new ConfigurationException('OpenAICompatibleProvider requires the curl PHP extension.');
        }
    }

    public static function openAI(string $apiKey, int $timeout = 60): self
    {
        return new self($apiKey, 'https://api.openai.com/v1', $timeout);
    }

    public static function openRouter(string $apiKey, int $timeout = 60): self
    {
        return new self($apiKey, 'https://openrouter.ai/api/v1', $timeout);
    }

    public function complete(array $messages, array $tools, CompletionOptions $options): ProviderResult
    {
        $request = OpenAIRequest::from($options, $messages, $tools);

        $url = rtrim($this->baseUrl, '/') . '/chat/completions';

        $ch = curl_init($url);

        if ($ch === false) {
            throw new ProviderException('Failed to init curl handle.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($request->toArray(), JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($raw === false) {
            throw new ProviderException("OpenAI request failed: {$error}");
        }

        if ($code !== 200) {
            throw new ProviderException(
                "OpenAI API returned HTTP {$code}: " . mb_substr((string) $raw, 0, 500),
            );
        }

        try {
            $json = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ProviderException('OpenAI returned invalid JSON: ' . $e->getMessage());
        }

        $response = OpenAIResponse::fromArray($json);
        $message = $response->getFirstChoice()->getMessage();

        return new ProviderResult(
            content: $message->getContent(),
            toolCalls: $message->getToolCalls(),
            meta: ['model' => $options->getModel(), 'usage' => $response->getUsage()->toArray()],
        );
    }
}
