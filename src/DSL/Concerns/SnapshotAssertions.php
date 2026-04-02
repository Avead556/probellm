<?php

declare(strict_types=1);

namespace ProbeLLM\DSL\Concerns;

use PHPUnit\Framework\Assert;
use ProbeLLM\Snapshot\SnapshotStore;

/**
 * Snapshot / golden testing assertions for LLM responses.
 */
trait SnapshotAssertions
{
    abstract private function getContent(): string;

    abstract public function assertByPrompt(
        string $criteria,
        ?string $model = null,
        ?float $temperature = null,
    ): self;

    /**
     * Assert the response matches a saved snapshot, or save it if it doesn't exist yet.
     *
     * On first run: saves the response as the golden snapshot (test passes).
     * On subsequent runs: compares current response to the snapshot using LLM-as-judge.
     *
     * To update a snapshot, delete the file in tests/snapshots/ and re-run.
     *
     * @param string      $snapshotKey Unique key for this snapshot (e.g. 'greeting_test').
     * @param string|null $criteria    Custom judge criteria. Default: "semantically equivalent".
     */
    public function assertMatchesSnapshot(
        string $snapshotKey,
        ?string $criteria = null,
    ): self {
        $store = new SnapshotStore();
        $content = $this->getContent();

        if (! $store->has($snapshotKey)) {
            // First run — save as golden snapshot.
            $store->save($snapshotKey, $content);

            return $this;
        }

        $golden = $store->load($snapshotKey);

        // Exact match — no need for judge.
        if (trim($golden) === trim($content)) {
            return $this;
        }

        $judgeCriteria = $criteria
            ?? 'The current response is semantically equivalent to the golden response. '
            . 'Minor wording differences are acceptable, but the meaning, key facts, '
            . 'and structure should be the same.';

        $fullCriteria = $judgeCriteria
            . "\n\n## Golden (expected) response:\n" . $golden
            . "\n\n## Current response:\n" . $content;

        return $this->assertByPrompt($fullCriteria);
    }

    /**
     * Save the current response as a snapshot (overwriting any existing one).
     */
    public function saveSnapshot(string $snapshotKey): self
    {
        $store = new SnapshotStore();
        $store->save($snapshotKey, $this->getContent());

        return $this;
    }
}
