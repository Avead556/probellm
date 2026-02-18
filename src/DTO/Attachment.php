<?php

declare(strict_types=1);

namespace ProbeLLM\DTO;

use ProbeLLM\Enum\AttachmentType;
use ProbeLLM\Exception\ConfigurationException;

final readonly class Attachment
{
    public function __construct(
        private AttachmentType $type,
        private string $data,
        private string $mimeType,
        private bool $isUrl = false,
    ) {}

    public function getType(): AttachmentType
    {
        return $this->type;
    }

    /**
     * Base64-encoded data or URL string.
     */
    public function getData(): string
    {
        return $this->data;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function isUrl(): bool
    {
        return $this->isUrl;
    }

    /**
     * Create from a local file path.
     */
    public static function fromFile(string $path): self
    {
        if (! file_exists($path)) {
            throw new ConfigurationException("Attachment file not found: '{$path}'.");
        }

        $mimeType = mime_content_type($path);

        if ($mimeType === false) {
            throw new ConfigurationException("Cannot detect MIME type for: '{$path}'.");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new ConfigurationException("Failed to read attachment file: '{$path}'.");
        }

        return new self(
            type: AttachmentType::fromMimeType($mimeType),
            data: base64_encode($content),
            mimeType: $mimeType,
        );
    }

    /**
     * Create from a URL. MIME type is inferred from extension when omitted.
     */
    public static function fromUrl(string $url, ?string $mimeType = null): self
    {
        $mimeType ??= self::mimeFromUrl($url);

        return new self(
            type: AttachmentType::fromMimeType($mimeType),
            data: $url,
            mimeType: $mimeType,
            isUrl: true,
        );
    }

    private static function mimeFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'wav' => 'audio/wav',
            'mp3' => 'audio/mp3',
            'ogg' => 'audio/ogg',
            default => throw new ConfigurationException(
                "Cannot infer MIME type from URL '{$url}'. Pass mimeType explicitly.",
            ),
        };
    }

    /**
     * Create from raw base64 data.
     */
    public static function fromBase64(string $base64, string $mimeType): self
    {
        return new self(
            type: AttachmentType::fromMimeType($mimeType),
            data: $base64,
            mimeType: $mimeType,
        );
    }

    /**
     * @return array{type: string, data: string, mime_type: string, is_url: bool}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'data' => $this->data,
            'mime_type' => $this->mimeType,
            'is_url' => $this->isUrl,
        ];
    }
}
