<?php

declare(strict_types=1);

namespace ProbeLLM\DTO\ElevenLabs;

final readonly class SimulationResponse
{
    /**
     * @param list<TranscriptEntry>  $transcript
     * @param list<EvaluationResult> $evaluationResults
     * @param array<string, mixed>   $rawData
     */
    public function __construct(
        private array $transcript,
        private array $evaluationResults,
        private array $rawData = [],
    ) {}

    /**
     * @return list<TranscriptEntry>
     */
    public function getTranscript(): array
    {
        return $this->transcript;
    }

    /**
     * @return list<EvaluationResult>
     */
    public function getEvaluationResults(): array
    {
        return $this->evaluationResults;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    /**
     * Collect all tool calls across the entire transcript.
     *
     * @return list<TranscriptToolCall>
     */
    public function getToolCalls(): array
    {
        $calls = [];

        foreach ($this->transcript as $entry) {
            foreach ($entry->getToolCalls() as $tc) {
                $calls[] = $tc;
            }
        }

        return $calls;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'transcript' => array_map(
                static fn(TranscriptEntry $e): array => $e->toArray(),
                $this->transcript,
            ),
            'evaluation_results' => array_map(
                static fn(EvaluationResult $e): array => $e->toArray(),
                $this->evaluationResults,
            ),
        ];

        if ($this->rawData !== []) {
            $data['raw_data'] = $this->rawData;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $transcript = array_map(
            static fn(array $entry): TranscriptEntry => TranscriptEntry::fromArray($entry),
            $data['simulated_conversation'] ?? $data['transcript'] ?? [],
        );

        $rawEvalResults = $data['analysis']['evaluation_criteria_results_list']
            ?? $data['evaluation_results']
            ?? [];

        $evaluationResults = array_map(
            static fn(array $result): EvaluationResult => EvaluationResult::fromArray($result),
            $rawEvalResults,
        );

        return new self(
            transcript: $transcript,
            evaluationResults: $evaluationResults,
            rawData: $data['raw_data'] ?? $data,
        );
    }
}
