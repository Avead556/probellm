<?php

declare(strict_types=1);

namespace ProbeLLM\Dataset;

use ProbeLLM\Cassette\CassetteStore;
use ProbeLLM\DTO\DatasetRow;
use ProbeLLM\Exception\ConfigurationException;
use ValueError;

/**
 * Loads evaluation datasets from JSONL and CSV files into DatasetRow objects.
 */
final class DatasetLoader
{
    /**
     * Load a dataset file, auto-detecting format from extension.
     *
     * @return list<DatasetRow>
     */
    public static function load(string $path): array
    {
        $supported = str_ends_with($path, '.jsonl')
            || str_ends_with($path, '.csv')
            || str_ends_with($path, '.json');

        if (! $supported) {
            throw new ConfigurationException(
                "Unsupported dataset format: '{$path}'. Supported: .jsonl, .csv, .json",
            );
        }

        $absolute = self::resolvePath($path);

        return match (true) {
            str_ends_with($absolute, '.jsonl') => self::loadJsonl($absolute),
            str_ends_with($absolute, '.csv') => self::loadCsv($absolute),
            default => self::loadJson($absolute),
        };
    }

    /**
     * @return list<DatasetRow>
     */
    public static function loadJsonl(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new ConfigurationException("Failed to read dataset file: '{$path}'.");
        }

        $rows = [];

        foreach ($lines as $index => $line) {
            $data = json_decode($line, true);

            if (! is_array($data)) {
                throw new ConfigurationException(
                    "Dataset file '{$path}', line {$index}: invalid JSON.",
                );
            }

            $rows[] = new DatasetRow($data, $index);
        }

        return $rows;
    }

    /**
     * @return list<DatasetRow>
     */
    public static function loadCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new ConfigurationException("Failed to open dataset file: '{$path}'.");
        }

        try {
            $rawHeaders = fgetcsv($handle, 0, ',', '"', '');

            if ($rawHeaders === false || $rawHeaders === [null]) {
                throw new ConfigurationException(
                    "Dataset file '{$path}': empty or invalid CSV header.",
                );
            }

            /** @var list<string> $headers */
            $headers = array_map(strval(...), $rawHeaders);

            $rows = [];
            $index = 0;

            while (($record = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($record === [null]) {
                    continue;
                }

                try {
                    $data = array_combine($headers, $record);
                } catch (ValueError) {
                    throw new ConfigurationException(
                        "Dataset file '{$path}', row {$index}: column count mismatch.",
                    );
                }

                $rows[] = new DatasetRow($data, $index);
                $index++;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Load a JSON file containing an array of objects.
     *
     * @return list<DatasetRow>
     */
    public static function loadJson(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new ConfigurationException("Failed to read dataset file: '{$path}'.");
        }

        $data = json_decode($content, true);

        if (! is_array($data) || ! array_is_list($data)) {
            throw new ConfigurationException(
                "Dataset file '{$path}': expected a JSON array of objects.",
            );
        }

        $rows = [];

        foreach ($data as $index => $item) {
            if (! is_array($item)) {
                throw new ConfigurationException(
                    "Dataset file '{$path}', item {$index}: expected an object.",
                );
            }

            $rows[] = new DatasetRow($item, $index);
        }

        return $rows;
    }

    private static function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            if (! file_exists($path)) {
                throw new ConfigurationException("Dataset file not found: '{$path}'.");
            }

            return $path;
        }

        $base = CassetteStore::resolveBasePath();
        $absolute = $base . '/' . ltrim($path, '/');

        if (! file_exists($absolute)) {
            throw new ConfigurationException(
                "Dataset file not found at '{$absolute}' (declared path: '{$path}').",
            );
        }

        return $absolute;
    }
}
