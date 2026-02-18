<?php

declare(strict_types=1);

namespace ProbeLLM\DTO\OpenAI;

use ProbeLLM\DTO\Attachment;
use ProbeLLM\DTO\CompletionOptions;
use ProbeLLM\DTO\Message;
use ProbeLLM\DTO\ToolDefinition;
use ProbeLLM\Enum\AttachmentType;

final readonly class OpenAIRequest
{
    /**
     * @param list<array<string, mixed>> $messages Already serialized messages.
     * @param list<array<string, mixed>> $tools    Already serialized tools.
     */
    public function __construct(
        private string $model,
        private float $temperature,
        private array $messages,
        private array $tools = [],
    ) {}

    /**
     * @param list<Message>        $messages
     * @param list<ToolDefinition> $tools
     */
    public static function from(CompletionOptions $options, array $messages, array $tools): self
    {
        return new self(
            model: $options->getModel(),
            temperature: $options->getTemperature(),
            messages: array_map(static fn(Message $m): array => self::serializeMessage($m), $messages),
            tools: array_map(static fn(ToolDefinition $def): array => [
                'type' => 'function',
                'function' => $def->toArray(),
            ], $tools),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializeMessage(Message $message): array
    {
        $attachments = $message->getAttachments();

        if ($attachments === null || $attachments === []) {
            return $message->toArray();
        }

        $base = $message->toArray();
        $parts = [['type' => 'text', 'text' => $base['content']]];

        foreach ($attachments as $attachment) {
            $parts[] = self::attachmentToPart($attachment);
        }

        $base['content'] = $parts;
        unset($base['attachments']);

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    private static function attachmentToPart(Attachment $attachment): array
    {
        return match ($attachment->getType()) {
            AttachmentType::IMAGE => [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $attachment->isUrl()
                        ? $attachment->getData()
                        : 'data:' . $attachment->getMimeType() . ';base64,' . $attachment->getData(),
                ],
            ],
            AttachmentType::AUDIO => [
                'type' => 'input_audio',
                'input_audio' => [
                    'data' => $attachment->getData(),
                    'format' => self::audioFormat($attachment->getMimeType()),
                ],
            ],
            AttachmentType::PDF => [
                'type' => 'file',
                'file' => [
                    'filename' => 'document.pdf',
                    'file_data' => $attachment->isUrl()
                        ? $attachment->getData()
                        : 'data:' . $attachment->getMimeType() . ';base64,' . $attachment->getData(),
                ],
            ],
        };
    }

    private static function audioFormat(string $mimeType): string
    {
        return match ($mimeType) {
            'audio/wav' => 'wav',
            'audio/mp3', 'audio/mpeg' => 'mp3',
            default => 'wav',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $this->messages,
            'temperature' => $this->temperature,
        ];

        if ($this->tools !== []) {
            $payload['tools'] = $this->tools;
        }

        return $payload;
    }
}
