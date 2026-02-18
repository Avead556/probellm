<?php

declare(strict_types=1);

namespace ProbeLLM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProbeLLM\DTO\Attachment;
use ProbeLLM\DTO\Message;
use ProbeLLM\Enum\AttachmentType;
use ProbeLLM\Exception\ConfigurationException;

class AttachmentDtoTest extends TestCase
{
    public function test_attachment_type_from_mime_image(): void
    {
        $this->assertSame(AttachmentType::IMAGE, AttachmentType::fromMimeType('image/png'));
        $this->assertSame(AttachmentType::IMAGE, AttachmentType::fromMimeType('image/jpeg'));
    }

    public function test_attachment_type_from_mime_pdf(): void
    {
        $this->assertSame(AttachmentType::PDF, AttachmentType::fromMimeType('application/pdf'));
    }

    public function test_attachment_type_from_mime_audio(): void
    {
        $this->assertSame(AttachmentType::AUDIO, AttachmentType::fromMimeType('audio/wav'));
        $this->assertSame(AttachmentType::AUDIO, AttachmentType::fromMimeType('audio/mp3'));
    }

    public function test_attachment_type_from_unsupported_mime_throws(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Unsupported MIME type');

        AttachmentType::fromMimeType('text/plain');
    }

    public function test_attachment_from_url_infers_mime(): void
    {
        $attachment = Attachment::fromUrl('https://example.com/photo.png');

        $this->assertTrue($attachment->isUrl());
        $this->assertSame('https://example.com/photo.png', $attachment->getData());
        $this->assertSame('image/png', $attachment->getMimeType());
        $this->assertSame(AttachmentType::IMAGE, $attachment->getType());
    }

    public function test_attachment_from_url_infers_pdf(): void
    {
        $attachment = Attachment::fromUrl('https://example.com/doc.pdf');

        $this->assertSame(AttachmentType::PDF, $attachment->getType());
        $this->assertSame('application/pdf', $attachment->getMimeType());
    }

    public function test_attachment_from_url_explicit_mime_overrides(): void
    {
        $attachment = Attachment::fromUrl('https://example.com/file', 'audio/wav');

        $this->assertSame(AttachmentType::AUDIO, $attachment->getType());
        $this->assertSame('audio/wav', $attachment->getMimeType());
    }

    public function test_attachment_from_url_unknown_extension_throws(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Cannot infer MIME type');

        Attachment::fromUrl('https://example.com/file.xyz');
    }

    public function test_attachment_from_base64(): void
    {
        $data = base64_encode('fake-image');
        $attachment = Attachment::fromBase64($data, 'image/jpeg');

        $this->assertFalse($attachment->isUrl());
        $this->assertSame($data, $attachment->getData());
        $this->assertSame('image/jpeg', $attachment->getMimeType());
    }

    public function test_attachment_from_nonexistent_file_throws(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('not found');

        Attachment::fromFile('/nonexistent/path/to/file.png');
    }

    public function test_attachment_to_array(): void
    {
        $attachment = Attachment::fromUrl('https://example.com/doc.pdf');
        $arr = $attachment->toArray();

        $this->assertSame('pdf', $arr['type']);
        $this->assertSame('https://example.com/doc.pdf', $arr['data']);
        $this->assertSame('application/pdf', $arr['mime_type']);
        $this->assertTrue($arr['is_url']);
    }

    public function test_message_user_with_attachments(): void
    {
        $msg = Message::userWithAttachments('Describe', [
            Attachment::fromUrl('https://example.com/photo.png'),
        ]);

        $this->assertSame('user', $msg->getRole()->value);
        $this->assertSame('Describe', $msg->getContent());
        $this->assertNotNull($msg->getAttachments());
        $this->assertCount(1, $msg->getAttachments());
    }

    public function test_message_user_with_empty_attachments(): void
    {
        $msg = Message::userWithAttachments('Hello', []);

        $this->assertNull($msg->getAttachments());
    }

    public function test_message_with_attachments_to_array(): void
    {
        $msg = Message::userWithAttachments('Describe', [
            Attachment::fromBase64('abc123', 'image/png'),
        ]);
        $arr = $msg->toArray();

        $this->assertArrayHasKey('attachments', $arr);
        $this->assertCount(1, $arr['attachments']);
        $this->assertSame('image', $arr['attachments'][0]['type']);
    }
}
