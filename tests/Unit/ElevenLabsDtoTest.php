<?php

declare(strict_types=1);

namespace ProbeLLM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProbeLLM\Cassette\ElevenLabsCassetteAdapter;
use ProbeLLM\Cassette\ElevenLabsHasher;
use ProbeLLM\DTO\ElevenLabs\EvaluationCriterion;
use ProbeLLM\DTO\ElevenLabs\EvaluationResult;
use ProbeLLM\DTO\ElevenLabs\SimulatedUserConfig;
use ProbeLLM\DTO\ElevenLabs\SimulationRequest;
use ProbeLLM\DTO\ElevenLabs\SimulationResponse;
use ProbeLLM\DTO\ElevenLabs\TranscriptEntry;
use ProbeLLM\DTO\ElevenLabs\TranscriptToolCall;
use ProbeLLM\DTO\ElevenLabs\TranscriptToolResult;

class ElevenLabsDtoTest extends TestCase
{
    public function test_simulated_user_config_roundtrip(): void
    {
        $config = new SimulatedUserConfig('Book a table', 'Hi there');
        $arr = $config->toArray();

        $this->assertSame('Book a table', $arr['prompt']['prompt']);
        $this->assertSame('Hi there', $arr['first_message']);

        $restored = SimulatedUserConfig::fromArray($arr);
        $this->assertSame('Book a table', $restored->getPrompt());
        $this->assertSame('Hi there', $restored->getFirstMessage());
    }

    public function test_simulated_user_config_omits_empty_first_message(): void
    {
        $arr = (new SimulatedUserConfig('Test'))->toArray();

        $this->assertArrayNotHasKey('first_message', $arr);
    }

    public function test_evaluation_criterion_roundtrip(): void
    {
        $criterion = new EvaluationCriterion('booking_done', 'Agent completed the booking');
        $arr = $criterion->toArray();

        $this->assertSame('booking_done', $arr['id']);
        $this->assertSame('booking_done', $arr['name']);
        $this->assertSame('prompt', $arr['type']);
        $this->assertSame('Agent completed the booking', $arr['conversation_goal_prompt']);

        $restored = EvaluationCriterion::fromArray($arr);
        $this->assertSame('booking_done', $restored->getId());
        $this->assertSame('Agent completed the booking', $restored->getCriteria());
    }

    public function test_transcript_tool_call_roundtrip(): void
    {
        $tc = new TranscriptToolCall('check_availability', '{"date":"2024-01-01"}', true);
        $arr = $tc->toArray();

        $this->assertSame('check_availability', $arr['tool_name']);
        $this->assertTrue($arr['tool_has_been_called']);

        $restored = TranscriptToolCall::fromArray($arr);
        $this->assertSame('check_availability', $restored->getToolName());
        $this->assertTrue($restored->hasBeenCalled());
        $this->assertSame(['date' => '2024-01-01'], $restored->getParams());
    }

    public function test_transcript_tool_result_roundtrip(): void
    {
        $tr = new TranscriptToolResult('make_reservation', '{"id":"RES-123"}', false);
        $arr = $tr->toArray();

        $this->assertSame('make_reservation', $arr['tool_name']);
        $this->assertFalse($arr['is_error']);

        $restored = TranscriptToolResult::fromArray($arr);
        $this->assertSame('make_reservation', $restored->getToolName());
        $this->assertSame('{"id":"RES-123"}', $restored->getResultValue());
    }

    public function test_transcript_entry_roundtrip(): void
    {
        $entry = new TranscriptEntry(
            role: 'agent',
            content: 'Let me check availability',
            toolCalls: [new TranscriptToolCall('check_availability', '{}', true)],
            toolResults: [new TranscriptToolResult('check_availability', '{"available":true}')],
        );

        $arr = $entry->toArray();
        $this->assertSame('agent', $arr['role']);
        $this->assertCount(1, $arr['tool_calls']);
        $this->assertCount(1, $arr['tool_results']);

        $restored = TranscriptEntry::fromArray($arr);
        $this->assertSame('agent', $restored->getRole());
        $this->assertCount(1, $restored->getToolCalls());
        $this->assertCount(1, $restored->getToolResults());
    }

    public function test_transcript_entry_omits_empty_tools(): void
    {
        $arr = (new TranscriptEntry(role: 'user', content: 'Hello'))->toArray();

        $this->assertArrayNotHasKey('tool_calls', $arr);
        $this->assertArrayNotHasKey('tool_results', $arr);
    }

    public function test_evaluation_result_roundtrip(): void
    {
        $result = new EvaluationResult('booking_done', true, 'Successfully completed');
        $arr = $result->toArray();

        $this->assertSame('booking_done', $arr['criteria_id']);
        $this->assertTrue($arr['pass']);

        $restored = EvaluationResult::fromArray($arr);
        $this->assertTrue($restored->isPassed());
        $this->assertSame('Successfully completed', $restored->getReason());
    }

    public function test_simulation_response_roundtrip(): void
    {
        $response = new SimulationResponse(
            transcript: [
                new TranscriptEntry('user', 'Book a table'),
                new TranscriptEntry(
                    'agent',
                    'Checking...',
                    [new TranscriptToolCall('check_availability', '{"date":"tonight"}', true)],
                ),
            ],
            evaluationResults: [new EvaluationResult('booking_done', true, 'OK')],
        );

        $arr = $response->toArray();
        $this->assertCount(2, $arr['transcript']);
        $this->assertCount(1, $arr['evaluation_results']);

        $restored = SimulationResponse::fromArray($arr);
        $this->assertCount(2, $restored->getTranscript());
        $this->assertCount(1, $restored->getEvaluationResults());
    }

    public function test_simulation_response_get_tool_calls(): void
    {
        $response = new SimulationResponse(
            transcript: [
                new TranscriptEntry('user', 'Hi'),
                new TranscriptEntry('agent', 'Checking...', [new TranscriptToolCall('check_availability', '{}', true)]),
                new TranscriptEntry('user', 'OK'),
                new TranscriptEntry('agent', 'Booking...', [new TranscriptToolCall('make_reservation', '{}', true)]),
            ],
            evaluationResults: [],
        );

        $allCalls = $response->getToolCalls();
        $this->assertCount(2, $allCalls);
        $this->assertSame('check_availability', $allCalls[0]->getToolName());
        $this->assertSame('make_reservation', $allCalls[1]->getToolName());
    }

    public function test_simulation_request_to_array(): void
    {
        $request = new SimulationRequest(
            agentId: 'agent_abc',
            simulatedUserConfig: new SimulatedUserConfig('Book a table', 'Hi'),
            evaluationCriteria: [new EvaluationCriterion('done', 'Booking done')],
            toolMockConfig: ['check_availability' => '{"available":true}'],
            turnsLimit: 15,
        );

        $arr = $request->toArray();

        $spec = $arr['simulation_specification'];
        $this->assertSame('Book a table', $spec['simulated_user_config']['prompt']['prompt']);
        $this->assertSame('Hi', $spec['simulated_user_config']['first_message']);
        $this->assertCount(1, $arr['extra_evaluation_criteria']);
        $this->assertSame('done', $arr['extra_evaluation_criteria'][0]['id']);
        $this->assertArrayHasKey('check_availability', $spec['tool_mock_config']);
        $this->assertSame('{"available":true}', $spec['tool_mock_config']['check_availability']['default_return_value']);
        $this->assertSame(15, $arr['new_turns_limit']);
    }

    public function test_simulation_request_from_array(): void
    {
        $request = SimulationRequest::fromArray([
            'agent_id' => 'agent_xyz',
            'simulated_user_config' => ['prompt' => 'Test', 'first_message' => 'Hey'],
            'evaluation_criteria' => [['id' => 'c1', 'criteria' => 'Test criteria']],
            'tool_overrides' => [['tool_name' => 'search', 'mock_response' => '{}']],
            'extra_body' => ['simulation' => ['max_turns' => 20]],
        ]);

        $this->assertSame('agent_xyz', $request->getAgentId());
        $this->assertSame('Test', $request->getSimulatedUserConfig()->getPrompt());
        $this->assertCount(1, $request->getEvaluationCriteria());
        $this->assertSame(['search' => '{}'], $request->getToolMockConfig());
        $this->assertSame(20, $request->getTurnsLimit());
    }

    public function test_elevenlabs_hasher_is_deterministic(): void
    {
        $criteria = [new EvaluationCriterion('c1', 'Test')];

        $hash1 = ElevenLabsHasher::make('agent1', 'prompt', 'first', ['tool' => '{}'], $criteria, 10, 'Test::method');
        $hash2 = ElevenLabsHasher::make('agent1', 'prompt', 'first', ['tool' => '{}'], $criteria, 10, 'Test::method');

        $this->assertSame($hash1, $hash2);
    }

    public function test_elevenlabs_hasher_produces_different_hash(): void
    {
        $criteria = [new EvaluationCriterion('c1', 'Test')];

        $hash1 = ElevenLabsHasher::make('agent1', 'prompt', 'first', [], $criteria, 10, 'Test::method');
        $hash2 = ElevenLabsHasher::make('agent2', 'prompt', 'first', [], $criteria, 10, 'Test::method');
        $hash3 = ElevenLabsHasher::make('agent1', 'different', 'first', [], $criteria, 10, 'Test::method');

        $this->assertNotSame($hash1, $hash2);
        $this->assertNotSame($hash1, $hash3);
    }

    public function test_elevenlabs_cassette_adapter_roundtrip(): void
    {
        $original = new SimulationResponse(
            transcript: [
                new TranscriptEntry('user', 'Hello'),
                new TranscriptEntry('agent', 'Hi there'),
            ],
            evaluationResults: [new EvaluationResult('greeting', true, 'Good greeting')],
        );

        $restored = ElevenLabsCassetteAdapter::toSimulationResponse(
            ElevenLabsCassetteAdapter::toProviderResult($original),
        );

        $this->assertCount(2, $restored->getTranscript());
        $this->assertSame('user', $restored->getTranscript()[0]->getRole());
        $this->assertSame('Hi there', $restored->getTranscript()[1]->getContent());
        $this->assertCount(1, $restored->getEvaluationResults());
        $this->assertTrue($restored->getEvaluationResults()[0]->isPassed());
    }
}
