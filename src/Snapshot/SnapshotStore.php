<?php

declare(strict_types=1);

namespace ProbeLLM\Snapshot;

use ProbeLLM\Cassette\CassetteStore;
use ProbeLLM\Exception\ConfigurationException;

/**
 * Stores and compares golden/snapshot responses for regression testing.
 *
 * Snapshots are stored as plain text files in a configurable directory.
 */
final class SnapshotStore
{
    private readonly string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? CassetteStore::resolveBasePath() . '/tests/snapshots';
    }

    /**
     * Check if a snapshot exists for the given key.
     */
    public function has(string $key): bool
    {
        return file_exists($this->path($key));
    }

    /**
     * Load a snapshot's content.
     *
     * @throws ConfigurationException If the snapshot does not exist.
     */
    public function load(string $key): string
    {
        $path = $this->path($key);

        if (! file_exists($path)) {
            throw new ConfigurationException("Snapshot not found: '{$path}'.");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new ConfigurationException("Failed to read snapshot: '{$path}'.");
        }

        return $content;
    }

    /**
     * Save a snapshot.
     */
    public function save(string $key, string $content): void
    {
        $path = $this->path($key);
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $content);
    }

    /**
     * Delete a snapshot.
     */
    public function delete(string $key): void
    {
        $path = $this->path($key);

        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function path(string $key): string
    {
        // Sanitize key for filesystem.
        $safe = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $key);

        return $this->directory . '/' . $safe . '.txt';
    }
}
