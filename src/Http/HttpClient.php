<?php

declare(strict_types=1);

namespace ProbeLLM\Http;

use JsonException;
use ProbeLLM\Exception\ConfigurationException;
use ProbeLLM\Exception\ProviderException;

final class HttpClient
{
    /**
     * Send a POST request with JSON body and return decoded response.
     *
     * @param list<string>        $headers
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     *
     * @throws ProviderException
     * @throws ConfigurationException
     */
    public static function postJson(
        string $url,
        array $headers,
        array $body,
        int $timeout,
        string $providerName,
    ): array {
        if (! extension_loaded('curl')) {
            throw new ConfigurationException("{$providerName} requires the curl PHP extension.");
        }

        $ch = curl_init($url);

        if ($ch === false) {
            throw new ProviderException('Failed to init curl handle.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', ...$headers],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
        ]);

        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($raw === false) {
            throw new ProviderException("{$providerName} request failed: {$error}");
        }

        if ($code !== 200) {
            throw new ProviderException(
                "{$providerName} API returned HTTP {$code}: " . mb_substr((string) $raw, 0, 500),
            );
        }

        try {
            $json = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ProviderException("{$providerName} returned invalid JSON: " . $e->getMessage());
        }

        return $json;
    }
}
