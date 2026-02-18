<?php

declare(strict_types=1);

namespace ProbeLLM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProbeLLM\DTO\Anthropic\AnthropicRequest;
use ProbeLLM\DTO\Attachment;
use ProbeLLM\DTO\CompletionOptions;
use ProbeLLM\DTO\Message;
use ProbeLLM\DTO\OpenAI\OpenAIRequest;

class ProviderRequestTest extends TestCase
{
    public function test_openai_request_serializes_image_url_attachment(): void
    {
        $messages = [
            Message::userWithAttachments('What is this?', [
                Attachment::fromUrl('https://example.com/photo.png'),
            ]),
        ];

        $request = OpenAIRequest::from(
            new CompletionOptions(model: 'gpt-4o', temperature: 0.7),
            $messages,
            [],
        );

        $arr = $request->toArray();
        $content = $arr['messages'][0]['content'];

        $this->assertIsArray($content);
        $this->assertSame('text', $content[0]['type']);
        $this->assertSame('image_url', $content[1]['type']);
        $this->assertSame('https://example.com/photo.png', $content[1]['image_url']['url']);
    }

    public function test_openai_request_serializes_base64_image_attachment(): void
    {
        $data = base64_encode('fake-image');
        $messages = [
            Message::userWithAttachments('Describe', [
                Attachment::fromBase64($data, 'image/jpeg'),
            ]),
        ];

        $request = OpenAIRequest::from(
            new CompletionOptions(model: 'gpt-4o', temperature: 0.7),
            $messages,
            [],
        );

        $arr = $request->toArray();
        $content = $arr['messages'][0]['content'];

        $this->assertSame('image_url', $content[1]['type']);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $content[1]['image_url']['url']);
    }

    public function test_openai_request_serializes_audio_attachment(): void
    {
        $messages = [
            Message::userWithAttachments('Transcribe', [
                Attachment::fromBase64(base64_encode('audio'), 'audio/wav'),
            ]),
        ];

        $request = OpenAIRequest::from(
            new CompletionOptions(model: 'gpt-4o', temperature: 0.7),
            $messages,
            [],
        );

        $arr = $request->toArray();
        $content = $arr['messages'][0]['content'];

        $this->assertSame('input_audio', $content[1]['type']);
        $this->assertSame('wav', $content[1]['input_audio']['format']);
    }

    public function test_openai_request_serializes_pdf_attachment(): void
    {
        $messages = [
            Message::userWithAttachments('Summarize', [
                Attachment::fromBase64(base64_encode('fake-pdf'), 'application/pdf'),
            ]),
        ];

        $request = OpenAIRequest::from(
            new CompletionOptions(model: 'gpt-4o', temperature: 0.7),
            $messages,
            [],
        );

        $arr = $request->toArray();
        $content = $arr['messages'][0]['content'];

        $this->assertSame('file', $content[1]['type']);
        $this->assertSame('document.pdf', $content[1]['file']['filename']);
        $this->assertStringStartsWith('data:application/pdf;base64,', $content[1]['file']['file_data']);
    }

    public function test_openai_request_serializes_plain_message_without_content_array(): void
    {
        $messages = [Message::user('Hello')];

        $request = OpenAIRequest::from(
            new CompletionOptions(model: 'gpt-4o', temperature: 0.7),
            $messages,
            [],
        );

        $arr = $request->toArray();

        $this->assertIsString($arr['messages'][0]['content']);
        $this->assertSame('Hello', $arr['messages'][0]['content']);
    }

    public function test_anthropic_request_serializes_image_url_attachment(): void
    {
        $messages = [
            Message::userWithAttachments('What is this?', [
                Attachment::fromUrl('https://example.com/photo.png'),
            ]),
        ];

        $request = AnthropicRequest::from(
            new CompletionOptions(model: 'claude-3-5-sonnet-20241022', temperature: 0.7),
            4096,
            $messages,
            [],
        );

        $arr = $request->toArray();
        $content = $arr['messages'][0]['content'];

        $this->assertIsArray($content);
        $this->assertSame('text', $content[0]['type']);
        $this->assertSame('image', $content[1]['type']);
        $this->assertSame('url', $content[1]['source']['type']);
        $this->assertSame('https://example.com/photo.png', $content[1]['source']['url']);
    }

    public function test_anthropic_request_serializes_base64_image_attachment(): void
    {
        $data = base64_encode('fake-image');
        $messages = [
            Message::userWithAttachments('Describe', [
                Attachment::fromBase64($data, 'image/jpeg'),
            ]),
        ];

        $request = AnthropicRequest::from(
            new CompletionOptions(model: 'claude-3-5-sonnet-20241022', temperature: 0.7),
            4096,
            $messages,
            [],
        );

        $arr = $request->toArray();
        $content = $arr['messages'][0]['content'];

        $this->assertSame('image', $content[1]['type']);
        $this->assertSame('base64', $content[1]['source']['type']);
        $this->assertSame('image/jpeg', $content[1]['source']['media_type']);
        $this->assertSame($data, $content[1]['source']['data']);
    }

    public function test_anthropic_request_serializes_pdf_attachment(): void
    {
        $messages = [
            Message::userWithAttachments('Summarize', [
                Attachment::fromBase64(base64_encode('fake-pdf'), 'application/pdf'),
            ]),
        ];

        $request = AnthropicRequest::from(
            new CompletionOptions(model: 'claude-3-5-sonnet-20241022', temperature: 0.7),
            4096,
            $messages,
            [],
        );

        $arr = $request->toArray();
        $content = $arr['messages'][0]['content'];

        $this->assertSame('document', $content[1]['type']);
        $this->assertSame('base64', $content[1]['source']['type']);
        $this->assertSame('application/pdf', $content[1]['source']['media_type']);
    }

    public function test_anthropic_request_serializes_plain_message_as_string(): void
    {
        $messages = [Message::user('Hello')];

        $request = AnthropicRequest::from(
            new CompletionOptions(model: 'claude-3-5-sonnet-20241022', temperature: 0.7),
            4096,
            $messages,
            [],
        );

        $arr = $request->toArray();

        $this->assertIsString($arr['messages'][0]['content']);
        $this->assertSame('Hello', $arr['messages'][0]['content']);
    }
}
