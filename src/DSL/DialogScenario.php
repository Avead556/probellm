<?php

declare(strict_types=1);

namespace ProbeLLM\DSL;

use ProbeLLM\Cassette\CassetteResolver;
use ProbeLLM\Cassette\CassetteStore;
use ProbeLLM\Cassette\Hasher;
use ProbeLLM\DTO\Attachment;
use ProbeLLM\DTO\CompletionOptions;
use ProbeLLM\DTO\Message;
use ProbeLLM\DTO\ProviderResult;
use ProbeLLM\DTO\ToolDefinition;
use ProbeLLM\Exception\ToolResolutionException;
use ProbeLLM\Provider\LLMProvider;
use ProbeLLM\Tools\ToolContract;

final class DialogScenario
{
    /** @var list<Message> */
    private array $messages = [];

    private string $systemPrompt = '';
    private string $model = 'gpt-4o';
    private float $temperature = 0.7;

    /** @var list<class-string<ToolContract>> */
    private array $toolClasses = [];

    private bool $replayMode = false;

    private string $testName = '';
    private int $turnIndex = 0;
    private ?ProviderResult $lastResult = null;

    private ?LLMProvider $judgeProvider = null;
    private ?string $judgeModel = null;
    private ?float $judgeTemperature = null;

    /** @var list<ProviderResult> */
    private array $mockResults = [];

    private ?CassetteResolver $cassetteResolver = null;

    public function __construct(
        private LLMProvider $provider,
        private CassetteStore $cassetteStore,
    ) {}

    public function withSystem(string $prompt): self
    {
        $this->systemPrompt = $prompt;

        return $this;
    }

    public function withModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function withTemperature(float $temperature): self
    {
        $this->temperature = $temperature;

        return $this;
    }

    /**
     * @param list<class-string<ToolContract>> $toolClasses
     */
    public function withTools(array $toolClasses): self
    {
        $this->toolClasses = $toolClasses;

        return $this;
    }

    public function withReplayMode(bool $replay = true): self
    {
        $this->replayMode = $replay;

        return $this;
    }

    public function withTestName(string $name): self
    {
        $this->testName = $name;

        return $this;
    }

    public function withJudgeProvider(LLMProvider $provider): self
    {
        $this->judgeProvider = $provider;

        return $this;
    }

    public function withJudgeModel(?string $model): self
    {
        $this->judgeModel = $model;

        return $this;
    }

    public function withJudgeTemperature(?float $temperature): self
    {
        $this->judgeTemperature = $temperature;

        return $this;
    }

    /**
     * Provide a pre-built result for the next answer() call.
     */
    public function withMockResult(ProviderResult $result): self
    {
        $this->mockResults[] = $result;

        return $this;
    }

    /**
     * Override/set the system prompt from DSL (takes precedence over attributes).
     */
    public function system(string $prompt): self
    {
        $this->systemPrompt = $prompt;

        return $this;
    }

    /**
     * Append a user message.
     */
    public function user(string $text): self
    {
        $this->messages[] = Message::user($text);

        return $this;
    }

    /**
     * Append a user message with file attachments.
     * Strings starting with http(s):// are treated as URLs, otherwise as local file paths.
     *
     * @param list<Attachment|string> $attachments
     */
    public function userWithAttachments(string $text, array $attachments): self
    {
        $resolved = array_map(
            static fn(Attachment|string $a): Attachment => match (true) {
                $a instanceof Attachment => $a,
                str_starts_with($a, 'http://'), str_starts_with($a, 'https://') => Attachment::fromUrl($a),
                default => Attachment::fromFile($a),
            },
            $attachments,
        );

        $this->messages[] = Message::userWithAttachments($text, $resolved);

        return $this;
    }

    /**
     * Append a tool result message.
     *
     * When $toolCallId is null, it is auto-resolved from the last assistant response's
     * tool call matching $toolName.
     *
     * @param array<string, mixed> $payload
     */
    public function toolResult(string $toolName, array $payload, ?string $toolCallId = null): self
    {
        if ($toolCallId === null) {
            $toolCallId = $this->resolveToolCallId($toolName);
        }

        $this->messages[] = Message::tool(
            $toolCallId,
            $toolName,
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        return $this;
    }

    /**
     * Find tool_call_id from the last assistant response by tool name.
     */
    private function resolveToolCallId(string $toolName): string
    {
        if ($this->lastResult === null) {
            throw new ToolResolutionException(
                "Cannot auto-resolve tool_call_id for '{$toolName}': no previous answer() call.",
            );
        }

        foreach ($this->lastResult->getToolCalls() as $tc) {
            if ($tc->getName() === $toolName) {
                return $tc->getId();
            }
        }

        throw new ToolResolutionException(
            "Cannot auto-resolve tool_call_id: tool '{$toolName}' was not called in the last answer.",
        );
    }

    /**
     * Execute one turn and run assertions.
     *
     * @param callable(AnswerExpectations): void $assertions
     */
    public function answer(callable $assertions): self
    {
        $result = $this->executeTurn();

        $this->messages[] = Message::assistant($result->getContent(), $result->getToolCalls());
        $this->lastResult = $result;

        $expectations = new AnswerExpectations(
            result: $result,
            provider: $this->provider,
            providerOptions: new CompletionOptions(model: $this->model, temperature: $this->temperature),
            cassetteResolver: $this->getCassetteResolver(),
            testName: $this->testName,
            turnIndex: $this->turnIndex,
            judgeProvider: $this->judgeProvider,
            judgeModel: $this->judgeModel,
            judgeTemperature: $this->judgeTemperature,
        );
        $assertions($expectations);

        $this->turnIndex++;

        return $this;
    }

    private function executeTurn(): ProviderResult
    {
        if ($this->mockResults !== []) {
            return array_shift($this->mockResults);
        }

        $tools = $this->resolveToolDefinitions();
        $fullMessages = $this->buildMessages();

        $options = new CompletionOptions(
            model: $this->model,
            temperature: $this->temperature,
        );

        $cassetteKey = Hasher::make(
            $this->systemPrompt,
            $fullMessages,
            $this->model,
            $this->temperature,
            $tools,
            $this->testName,
            $this->turnIndex,
        );

        return $this->getCassetteResolver()->resolve(
            $cassetteKey,
            fn(): ProviderResult => $this->provider->complete($fullMessages, $tools, $options),
            fn(): array => [
                'messages' => array_map(static fn(Message $m): array => $m->toArray(), $fullMessages),
                'options' => $options->toArray(),
                'tools' => array_map(static fn(ToolDefinition $t): array => $t->toArray(), $tools),
            ],
            ['model' => $this->model, 'temperature' => $this->temperature, 'provider' => $this->provider::class],
        );
    }

    /**
     * @return list<Message>
     */
    private function buildMessages(): array
    {
        $result = [];

        if ($this->systemPrompt !== '') {
            $result[] = Message::system($this->systemPrompt);
        }

        return [...$result, ...$this->messages];
    }

    private function getCassetteResolver(): CassetteResolver
    {
        return $this->cassetteResolver ??= new CassetteResolver($this->cassetteStore, $this->replayMode);
    }

    /**
     * @return list<ToolDefinition>
     */
    private function resolveToolDefinitions(): array
    {
        $defs = [];

        foreach ($this->toolClasses as $class) {
            if (! is_a($class, ToolContract::class, true)) {
                throw new ToolResolutionException("Tool class '{$class}' must implement " . ToolContract::class);
            }

            $defs[] = $class::definition();
        }

        return $defs;
    }
}
