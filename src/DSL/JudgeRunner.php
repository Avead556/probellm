<?php

declare(strict_types=1);

namespace ProbeLLM\DSL;

use JsonException;
use PHPUnit\Framework\Assert;
use ProbeLLM\Cassette\CassetteResolver;
use ProbeLLM\Cassette\Hasher;
use ProbeLLM\DTO\CompletionOptions;
use ProbeLLM\DTO\JudgeVerdict;
use ProbeLLM\DTO\Message;
use ProbeLLM\DTO\ProviderResult;
use ProbeLLM\Enum\CassetteSource;
use ProbeLLM\Provider\LLMProvider;

final class JudgeRunner
{
    /**
     * Evaluate content against criteria using an LLM judge.
     *
     * @param string $content The content to evaluate (assistant response or transcript).
     * @param string $contentLabel Label for the content section ("Assistant's response" or "Conversation transcript").
     * @param string $criteria Natural-language evaluation criteria.
     * @param string $testName Full test name prefix for cassette key (e.g. "judge:Class::method:0:0").
     * @param int $judgeIndex Current judge call index (will be incremented).
     * @throws JsonException
     */
    public static function evaluate(
        LLMProvider $provider,
        CassetteResolver $resolver,
        string $content,
        string $contentLabel,
        string $criteria,
        string $testName,
        int &$judgeIndex,
        ?string $model = null,
        ?float $temperature = null,
    ): JudgeVerdict {
        $judgeSystem = <<<'PROMPT'
You are a strict test evaluator. You will receive an AI assistant's response and evaluation criteria.
Evaluate whether the response fully satisfies the criteria.
You MUST respond with ONLY a JSON object in this exact format, no other text:
{"pass": true, "reason": "brief explanation"}
or
{"pass": false, "reason": "brief explanation of what failed"}
PROMPT;

        $judgeUser = <<<PROMPT
## {$contentLabel}:
{$content}

## Evaluation criteria:
{$criteria}
PROMPT;

        $judgeModel = $model ?? 'gpt-4o';
        $resolvedTemperature = $temperature ?? 0.0;
        $options = new CompletionOptions(
            model: $judgeModel,
            temperature: $resolvedTemperature,
        );

        $judgeMessages = [
            Message::system($judgeSystem),
            Message::user($judgeUser),
        ];

        $cassetteKey = Hasher::make($judgeSystem, $judgeMessages, $judgeModel, $resolvedTemperature, [], $testName . ':' . $judgeIndex, 0);
        $judgeIndex++;

        $judgeResult = $resolver->resolve(
            $cassetteKey,
            fn(): ProviderResult => $provider->complete($judgeMessages, [], $options),
            fn(): array => [
                'messages' => array_map(static fn(Message $m): array => $m->toArray(), $judgeMessages),
                'options' => $options->toArray(),
                'tools' => [],
            ],
            ['model' => $options->getModel(), 'temperature' => $options->getTemperature(), 'provider' => CassetteSource::JUDGE->value],
        );

        return JudgeVerdict::fromJson($judgeResult->getContent());
    }

    /**
     * Run evaluate() and assert that the verdict passed.
     */
    public static function assertPassed(
        LLMProvider $provider,
        CassetteResolver $resolver,
        string $content,
        string $contentLabel,
        string $criteria,
        string $testName,
        int &$judgeIndex,
        ?string $model = null,
        ?float $temperature = null,
    ): void {
        $verdict = self::evaluate(
            $provider,
            $resolver,
            $content,
            $contentLabel,
            $criteria,
            $testName,
            $judgeIndex,
            $model,
            $temperature,
        );

        Assert::assertTrue(
            $verdict->isPassed(),
            "LLM judge failed assertion.\n"
            . "Criteria: {$criteria}\n"
            . 'Reason: ' . $verdict->getReason() . "\n"
            . $contentLabel . ': ' . mb_substr($content, 0, 500),
        );
    }
}
