<?php

declare(strict_types=1);

namespace ProbeLLM\Examples;

use ProbeLLM\AgentTestCase;
use ProbeLLM\Attributes\AgentModel;
use ProbeLLM\Attributes\AgentReplayMode;
use ProbeLLM\Attributes\AgentSystem;
use ProbeLLM\Attributes\AgentTemperature;
use ProbeLLM\DSL\AnswerExpectations;
use ProbeLLM\DTO\Attachment;
use ProbeLLM\Provider\LLMProvider;
use ProbeLLM\Provider\OpenAICompatibleProvider;

#[AgentSystem('You are a helpful assistant. Always respond in valid JSON.')]
#[AgentModel('openai/gpt-4o')]
#[AgentTemperature(0.0)]
#[AgentReplayMode]
class AttachmentTest extends AgentTestCase
{
    private const string FIXTURES = __DIR__ . '/../tests/fixtures/files/';

    protected function resolveProvider(): LLMProvider
    {
        $apiKey = getenv('LLM_API_KEY') ?: '';

        if ($apiKey === '') {
            return parent::resolveProvider();
        }

        $baseUrl = getenv('LLM_BASE_URL') ?: '';

        if ($baseUrl !== '') {
            return new OpenAICompatibleProvider($apiKey, $baseUrl);
        }

        return OpenAICompatibleProvider::openRouter($apiKey);
    }

    public function test_describe_local_png(): void
    {
        $this->dialog()
            ->userWithAttachments('What color is the pixel in this image? Return JSON: {"color": "..."}', [
                self::FIXTURES . 'red.png',
            ])
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.color', notEmpty: true);
            });
    }

    public function test_describe_local_jpeg(): void
    {
        $this->dialog()
            ->userWithAttachments('What color is this image? Return JSON: {"color": "..."}', [
                self::FIXTURES . 'blue.jpg',
            ])
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.color', notEmpty: true);
            });
    }

    public function test_local_pdf(): void
    {
        $this->dialog()
            ->userWithAttachments('What type of file did I send you? Return JSON: {"file_type": "..."}', [
                self::FIXTURES . 'sample.pdf',
            ])
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.file_type', notEmpty: true);
            });
    }

    public function test_multiple_local_files(): void
    {
        $this->dialog()
            ->userWithAttachments('How many files did I send you? Return JSON: {"count": ...}', [
                self::FIXTURES . 'red.png',
                self::FIXTURES . 'blue.jpg',
            ])
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.count', equals: 2);
            });
    }

    public function test_url_image(): void
    {
        $this->dialog()
            ->userWithAttachments(
                'Describe this image briefly. Return JSON: {"description": "..."}',
                [Attachment::fromUrl('https://cataas.com/cat/cute?width=400', 'image/jpeg')],
            )
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.description', notEmpty: true);
            });
    }

    public function test_mixed_local_and_url(): void
    {
        $this->dialog()
            ->userWithAttachments('How many images did I send? Return JSON: {"count": ...}', [
                self::FIXTURES . 'red.png',
                Attachment::fromUrl('https://cataas.com/cat/cute?width=400', 'image/jpeg'),
            ])
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.count', equals: 2);
            });
    }

    public function test_attachment_multi_turn(): void
    {
        $this->dialog()
            ->userWithAttachments('What color is the pixel? Return JSON: {"color": "..."}', [
                self::FIXTURES . 'red.png',
            ])
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.color', notEmpty: true);
            })
            ->user('Now return the same answer but add the format. Return JSON: {"color": "...", "format": "..."}')
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                    ->assertJsonPath('$.color', 'red')
                    ->assertJsonPath('$.format', notEmpty: true);
            });
    }
}
