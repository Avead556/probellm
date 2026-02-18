<?php

declare(strict_types=1);

namespace ProbeLLM\Enum;

use ProbeLLM\Exception\ConfigurationException;

enum AttachmentType: string
{
    case IMAGE = 'image';
    case PDF = 'pdf';
    case AUDIO = 'audio';

    /**
     * @param string $mimeType e.g. "image/png", "application/pdf", "audio/wav"
     */
    public static function fromMimeType(string $mimeType): self
    {
        $lower = strtolower($mimeType);

        if (str_starts_with($lower, 'image/')) {
            return self::IMAGE;
        }

        if ($lower === 'application/pdf') {
            return self::PDF;
        }

        if (str_starts_with($lower, 'audio/')) {
            return self::AUDIO;
        }

        throw new ConfigurationException("Unsupported MIME type for attachment: '{$mimeType}'.");
    }
}
