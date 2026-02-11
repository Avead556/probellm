<?php

declare(strict_types=1);

namespace ProbeLLM\DTO\OpenAI;

use ProbeLLM\Exception\InvalidResponseException;

final readonly class OpenAIChoice
{
    public function __construct(
        private int $index,
        private OpenAIMessage $message,
        private string $finishReason,
    ) {}

    /**
     * @param array<string, mixed> $data Raw choice object from OpenAI response.
     */
    public static function fromArray(array $data): self
    {
        $messageData = $data['message'] ?? throw new InvalidResponseException('Choice is missing the "message" key.');

        return new self(
            index: (int) ($data['index'] ?? 0),
            message: OpenAIMessage::fromArray($messageData),
            finishReason: $data['finish_reason'] ?? 'stop',
        );
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function getMessage(): OpenAIMessage
    {
        return $this->message;
    }

    public function getFinishReason(): string
    {
        return $this->finishReason;
    }
}
