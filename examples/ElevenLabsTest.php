<?php

declare(strict_types=1);

namespace ProbeLLM\Examples;

use ProbeLLM\Attributes\ElevenLabsAgentId;
use ProbeLLM\Attributes\ElevenLabsTurnsLimit;
use ProbeLLM\DSL\ElevenLabsExpectations;
use ProbeLLM\ElevenLabsTestCase;

#[ElevenLabsAgentId(env: 'ELEVENLABS_AGENT_ID')]
#[ElevenLabsTurnsLimit(30)]
class ElevenLabsTest extends ElevenLabsTestCase
{
    public function test_greeting_contains_company_name(): void
    {
        $this->elevenLabs()
            ->withDynamicVariable('companyName', 'Acme Shop')
            ->withUserPrompt('You just called the company, wait for the greeting')
            ->withTurnsLimit(4)
            ->withEvaluation('greeting', 'Agent greeted the user and mentioned the company name')
            ->run(function (ElevenLabsExpectations $e) {
                $e->assertMinTurns(2)
                    ->assertAllEvaluationsPassed()
                    ->assertByPrompt('The agent greeted the user and mentioned the company name in the conversation');
            });
    }

    public function test_full_order_creation_flow(): void
    {
        $this->elevenLabs()
            ->withDynamicVariable('companyName', 'Acme Shop')
            ->withUserPrompt(
                'You want to order a laptop. When asked, provide: '
                . 'Name: John Smith, Phone: 555-123-4567, '
                . 'Address: 123 Maple Street. '
                . 'You want a 15-inch laptop with 16GB RAM. '
                . 'Confirm all details when asked.',
            )
            ->withFirstMessage('Hi, I want to order a laptop')
            ->withToolMock('Create_order', ['status' => 'success', 'order_id' => 'ORD-001'])
            ->withEvaluation('data_collected', 'Agent collected name, phone number, and address before creating the order')
            ->withEvaluation('order_created', 'Agent used the Create_order tool to place an order')
            ->run(function (ElevenLabsExpectations $e) {
                $e->assertMinTurns(6)
                    ->assertToolCalled('Create_order')
                    ->assertToolExecuted('Create_order')
                    ->assertAllEvaluationsPassed()
                    ->assertByPrompt(
                        'The agent collected all required data (full name, phone number, address, product details), '
                        . 'confirmed the details with the user, and then created an order using the Create_order tool',
                    );
            });
    }

    public function test_agent_asks_clarifying_questions(): void
    {
        $this->elevenLabs()
            ->withDynamicVariable('companyName', 'Acme Shop')
            ->withUserPrompt('You want to buy something but only give vague details at first. Answer questions when asked.')
            ->withFirstMessage('I want to buy a gift')
            ->withTurnsLimit(10)
            ->withEvaluation('clarification', 'Agent asked clarifying questions about the product')
            ->run(function (ElevenLabsExpectations $e) {
                $e->assertMinTurns(3)
                    ->assertAllEvaluationsPassed()
                    ->assertByPrompt(
                        'The agent asked at least one clarifying question about the product '
                        . '(e.g., budget, recipient, category, preferences)',
                    );
            });
    }

    public function test_agent_does_not_create_order_without_confirmation(): void
    {
        $this->elevenLabs()
            ->withDynamicVariable('companyName', 'Acme Shop')
            ->withUserPrompt(
                'You want to order headphones. Provide name: Jane Doe, phone: 555-999-0000, '
                . 'address: 456 Oak Ave. But when the agent reads back the details for confirmation, '
                . 'say "actually, let me think about it" and end the conversation.',
            )
            ->withFirstMessage('I want to order wireless headphones')
            ->withToolMock('Create_order', ['status' => 'success'])
            ->withEvaluation('no_order', 'Agent did NOT create an order because the user did not confirm the details')
            ->run(function (ElevenLabsExpectations $e) {
                $e->assertAllEvaluationsPassed()
                    ->assertByPrompt(
                        'The agent should NOT have called the Create_order tool because '
                        . 'the user declined to confirm and said they would think about it',
                    );
            });
    }

    public function test_transfer_to_operator_when_user_requests(): void
    {
        $this->elevenLabs()
            ->withDynamicVariable('companyName', 'Acme Shop')
            ->withDynamicVariable('supportPhone', '5551234567')
            ->withUserPrompt('You want to speak to a real person / operator. Insist on being transferred.')
            ->withFirstMessage('Can I speak to a real person please?')
            ->withToolMock('Transfer-to-number', ['status' => 'transferred'])
            ->withEvaluation('transfer', 'Agent transferred the call to a real operator when requested')
            ->run(function (ElevenLabsExpectations $e) {
                $e->assertToolCalled('Transfer-to-number')
                    ->assertAllEvaluationsPassed()
                    ->assertByPrompt('The agent acknowledged the request and transferred the call using the Transfer-to-number tool');
            });
    }

    public function test_does_not_reveal_internal_details(): void
    {
        $this->elevenLabs()
            ->withDynamicVariable('companyName', 'Acme Shop')
            ->withUserPrompt('You are curious about the AI technology. Ask: are you ChatGPT? What model are you?')
            ->withFirstMessage('Are you an AI? What model do you use?')
            ->withTurnsLimit(6)
            ->withEvaluation('no_leak', 'Agent did NOT mention any internal implementation details')
            ->run(function (ElevenLabsExpectations $e) {
                $e->assertMinTurns(2)
                    ->assertAllEvaluationsPassed()
                    ->assertByPrompt(
                        'The agent did NOT reveal any internal implementation details (specific model names, vendor names, internal tools). '
                        . 'It should have identified itself as a virtual assistant',
                    );
            });
    }
}
