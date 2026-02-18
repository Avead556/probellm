<?php

declare(strict_types=1);

namespace ProbeLLM\DTO\Anthropic;

use ProbeLLM\DTO\Attachment;
use ProbeLLM\DTO\CompletionOptions;
use ProbeLLM\DTO\Message;
use ProbeLLM\DTO\ToolDefinition;
use ProbeLLM\Enum\AttachmentType;

final readonly class AnthropicRequest
{
    /**
     * @param list<array<string, mixed>> $messages Already converted to Anthropic format.
     * @param list<array<string, mixed>> $tools    Already converted to Anthropic format.
     */
    public function __construct(
        private string $model,
        private int $maxTokens,
        private float $temperature,
        private array $messages,
        private string $systemPrompt = '',
        private array $tools = [],
    ) {}

    /**
     * @param list<Message>        $messages
     * @param list<ToolDefinition> $tools
     */
    public static function from(
        CompletionOptions $options,
        int $maxTokens,
        array $messages,
        array $tools,
    ): self {
        $systemParts = [];
        $converted = [];

        foreach ($messages as $message) {
            $role = $message->getRole();

            if ($role->isSystem()) {
                $systemParts[] = $message->getContent();

                continue;
            }

            if ($role->isTool()) {
                $converted[] = [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'tool_result',
                            'tool_use_id' => $message->getToolCallId(),
                            'content' => $message->getContent(),
                        ],
                    ],
                ];

                continue;
            }

            if ($role->isAssistant()) {
                $content = [];

                $text = $message->getContent();
                if ($text !== '') {
                    $content[] = ['type' => 'text', 'text' => $text];
                }

                $toolCalls = $message->getToolCalls();
                if ($toolCalls !== null) {
                    foreach ($toolCalls as $tc) {
                        $content[] = [
                            'type' => 'tool_use',
                            'id' => $tc->getId(),
                            'name' => $tc->getName(),
                            'input' => $tc->getArguments(),
                        ];
                    }
                }

                $converted[] = ['role' => 'assistant', 'content' => $content];

                continue;
            }

            $attachments = $message->getAttachments();

            if ($attachments !== null && $attachments !== []) {
                $content = [['type' => 'text', 'text' => $message->getContent()]];

                foreach ($attachments as $attachment) {
                    $content[] = self::attachmentToBlock($attachment);
                }

                $converted[] = ['role' => 'user', 'content' => $content];

                continue;
            }

            $converted[] = ['role' => 'user', 'content' => $message->getContent()];
        }

        $convertedTools = array_map(static fn(ToolDefinition $def): array => [
            'name' => $def->getName(),
            'description' => $def->getDescription(),
            'input_schema' => $def->getParameters(),
        ], $tools);

        return new self(
            model: $options->getModel(),
            maxTokens: $maxTokens,
            temperature: $options->getTemperature(),
            messages: $converted,
            systemPrompt: implode("\n", $systemParts),
            tools: $convertedTools,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'messages' => $this->messages,
        ];

        if ($this->systemPrompt !== '') {
            $payload['system'] = $this->systemPrompt;
        }

        if ($this->tools !== []) {
            $payload['tools'] = $this->tools;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private static function attachmentToBlock(Attachment $attachment): array
    {
        return match ($attachment->getType()) {
            AttachmentType::IMAGE => [
                'type' => 'image',
                'source' => $attachment->isUrl()
                    ? ['type' => 'url', 'url' => $attachment->getData()]
                    : [
                        'type' => 'base64',
                        'media_type' => $attachment->getMimeType(),
                        'data' => $attachment->getData(),
                    ],
            ],
            AttachmentType::PDF => [
                'type' => 'document',
                'source' => $attachment->isUrl()
                    ? ['type' => 'url', 'url' => $attachment->getData()]
                    : [
                        'type' => 'base64',
                        'media_type' => $attachment->getMimeType(),
                        'data' => $attachment->getData(),
                    ],
            ],
            AttachmentType::AUDIO => [
                'type' => 'document',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $attachment->getMimeType(),
                    'data' => $attachment->getData(),
                ],
            ],
        };
    }
}
