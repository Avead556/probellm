<?php

declare(strict_types=1);

namespace ProbeLLM\Provider;

use ProbeLLM\DTO\Anthropic\AnthropicRequest;
use ProbeLLM\DTO\Anthropic\AnthropicResponse;
use ProbeLLM\DTO\CompletionOptions;
use ProbeLLM\Exception\ConfigurationException;
use ProbeLLM\Exception\ProviderException;
use JsonException;

/**
 * Native Anthropic API provider (Claude models).
 */
final class AnthropicProvider implements LLMProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.anthropic.com',
        private readonly string $apiVersion = '2023-06-01',
        private readonly int $maxTokens = 4096,
        private readonly int $timeout = 60,
    ) {
        if (! extension_loaded('curl')) {
            throw new ConfigurationException('AnthropicProvider requires the curl PHP extension.');
        }
    }

    public function complete(array $messages, array $tools, CompletionOptions $options): ProviderResult
    {
        $request = AnthropicRequest::from($options, $this->maxTokens, $messages, $tools);

        $url = rtrim($this->baseUrl, '/') . '/v1/messages';

        $ch = curl_init($url);

        if ($ch === false) {
            throw new ProviderException('Failed to init curl handle.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: ' . $this->apiVersion,
            ],
            CURLOPT_POSTFIELDS => json_encode($request->toArray(), JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($raw === false) {
            throw new ProviderException("Anthropic request failed: {$error}");
        }

        if ($code !== 200) {
            throw new ProviderException(
                "Anthropic API returned HTTP {$code}: " . mb_substr((string) $raw, 0, 500),
            );
        }

        try {
            $json = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ProviderException('Anthropic returned invalid JSON: ' . $e->getMessage());
        }

        $response = AnthropicResponse::fromArray($json);

        return new ProviderResult(
            content: $response->getTextContent(),
            toolCalls: $response->getToolCalls(),
            meta: ['model' => $options->getModel(), 'usage' => $response->getUsage()->toArray()],
        );
    }
}
