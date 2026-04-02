<?php

declare(strict_types=1);

namespace ProbeLLM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProbeLLM\Dataset\DatasetLoader;
use ProbeLLM\DTO\DatasetRow;
use ProbeLLM\DTO\EvalSummary;
use ProbeLLM\Exception\ConfigurationException;
use ProbeLLM\Exception\InvalidResponseException;

class DatasetTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/../fixtures/datasets';
    }

    /*
    |----------------------------------------------------------------------
    | DatasetRow DTO
    |----------------------------------------------------------------------
    */

    public function test_row_get_input_with_standard_key(): void
    {
        $row = DatasetRow::fromArray(['input' => 'Hello', 'expected' => 'Hi']);

        $this->assertSame('Hello', $row->getInput());
    }

    public function test_row_get_input_with_question_alias(): void
    {
        $row = DatasetRow::fromArray(['question' => 'What?', 'answer' => '42']);

        $this->assertSame('What?', $row->getInput());
    }

    public function test_row_get_input_with_prompt_alias(): void
    {
        $row = DatasetRow::fromArray(['prompt' => 'Tell me', 'output' => 'Sure']);

        $this->assertSame('Tell me', $row->getInput());
    }

    public function test_row_get_expected_with_standard_key(): void
    {
        $row = DatasetRow::fromArray(['input' => 'Hi', 'expected' => 'Hello']);

        $this->assertSame('Hello', $row->getExpected());
    }

    public function test_row_get_expected_with_answer_alias(): void
    {
        $row = DatasetRow::fromArray(['question' => 'X', 'answer' => '42']);

        $this->assertSame('42', $row->getExpected());
    }

    public function test_row_get_input_throws_when_no_alias_found(): void
    {
        $row = DatasetRow::fromArray(['foo' => 'bar'], index: 5);

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('row [5]');
        $this->expectExceptionMessage('no input field found');

        $row->getInput();
    }

    public function test_row_get_expected_throws_when_no_alias_found(): void
    {
        $row = DatasetRow::fromArray(['input' => 'Hi', 'foo' => 'bar']);

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('no expected field found');

        $row->getExpected();
    }

    public function test_row_get_string(): void
    {
        $row = DatasetRow::fromArray(['category' => 'math', 'input' => 'x']);

        $this->assertSame('math', $row->getString('category'));
    }

    public function test_row_get_string_throws_on_missing_key(): void
    {
        $row = DatasetRow::fromArray(['input' => 'x'], index: 3);

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage("missing key 'category'");

        $row->getString('category');
    }

    public function test_row_get_int(): void
    {
        $row = DatasetRow::fromArray(['score' => 42, 'input' => 'x']);

        $this->assertSame(42, $row->getInt('score'));
    }

    public function test_row_get_float(): void
    {
        $row = DatasetRow::fromArray(['threshold' => 0.95, 'input' => 'x']);

        $this->assertSame(0.95, $row->getFloat('threshold'));
    }

    public function test_row_get_bool(): void
    {
        $row = DatasetRow::fromArray(['is_hard' => true, 'input' => 'x']);

        $this->assertTrue($row->getBool('is_hard'));
    }

    public function test_row_generic_get_with_default(): void
    {
        $row = DatasetRow::fromArray(['input' => 'x']);

        $this->assertNull($row->get('missing'));
        $this->assertSame('default', $row->get('missing', 'default'));
        $this->assertSame('x', $row->get('input'));
    }

    public function test_row_has(): void
    {
        $row = DatasetRow::fromArray(['input' => 'x', 'category' => 'math']);

        $this->assertTrue($row->has('input'));
        $this->assertTrue($row->has('category'));
        $this->assertFalse($row->has('missing'));
    }

    public function test_row_index_and_keys(): void
    {
        $row = DatasetRow::fromArray(['a' => 1, 'b' => 2], index: 7);

        $this->assertSame(7, $row->getIndex());
        $this->assertSame(['a', 'b'], $row->keys());
    }

    public function test_row_to_array(): void
    {
        $data = ['input' => 'Hi', 'expected' => 'Hello'];
        $row = DatasetRow::fromArray($data);

        $this->assertSame($data, $row->toArray());
    }

    /*
    |----------------------------------------------------------------------
    | DatasetLoader
    |----------------------------------------------------------------------
    */

    public function test_load_jsonl(): void
    {
        $rows = DatasetLoader::loadJsonl($this->fixturesDir . '/qa.jsonl');

        $this->assertCount(3, $rows);
        $this->assertSame('Capital of France?', $rows[0]->getInput());
        $this->assertSame('Paris', $rows[0]->getExpected());
        $this->assertSame(0, $rows[0]->getIndex());
        $this->assertSame('Tokyo', $rows[1]->getExpected());
        $this->assertSame(2, $rows[2]->getIndex());
    }

    public function test_load_csv(): void
    {
        $rows = DatasetLoader::loadCsv($this->fixturesDir . '/qa.csv');

        $this->assertCount(3, $rows);
        $this->assertSame('Capital of France?', $rows[0]->getInput());
        $this->assertSame('Paris', $rows[0]->getExpected());
        $this->assertSame('geography', $rows[0]->getString('category'));
    }

    public function test_load_json(): void
    {
        $rows = DatasetLoader::loadJson($this->fixturesDir . '/qa.json');

        $this->assertCount(2, $rows);
        $this->assertSame('Capital of France?', $rows[0]->getInput());
        $this->assertSame('Tokyo', $rows[1]->getExpected());
    }

    public function test_load_auto_detects_format(): void
    {
        $jsonlRows = DatasetLoader::load($this->fixturesDir . '/qa.jsonl');
        $csvRows = DatasetLoader::load($this->fixturesDir . '/qa.csv');
        $jsonRows = DatasetLoader::load($this->fixturesDir . '/qa.json');

        $this->assertCount(3, $jsonlRows);
        $this->assertCount(3, $csvRows);
        $this->assertCount(2, $jsonRows);
    }

    public function test_load_unsupported_format_throws(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Unsupported dataset format');

        DatasetLoader::load($this->fixturesDir . '/qa.xml');
    }

    public function test_load_with_custom_key_aliases(): void
    {
        $rows = DatasetLoader::loadJsonl($this->fixturesDir . '/custom_keys.jsonl');

        $this->assertCount(2, $rows);
        // "question" maps to getInput(), "answer" maps to getExpected()
        $this->assertSame('What is 2+2?', $rows[0]->getInput());
        $this->assertSame('4', $rows[0]->getExpected());
        $this->assertSame('easy', $rows[0]->getString('difficulty'));
    }

    /*
    |----------------------------------------------------------------------
    | EvalSummary
    |----------------------------------------------------------------------
    */

    public function test_eval_summary_all_passed(): void
    {
        $summary = new EvalSummary(3, 3, []);

        $this->assertSame(3, $summary->getTotal());
        $this->assertSame(3, $summary->getPassed());
        $this->assertSame(0, $summary->getFailed());
        $this->assertSame(1.0, $summary->getPassRate());
        $this->assertTrue($summary->isAllPassed());
        $this->assertSame([], $summary->getFailures());
    }

    public function test_eval_summary_with_failures(): void
    {
        $failures = [
            ['index' => 1, 'input' => 'Capital of Japan?', 'error' => 'Wrong answer'],
        ];
        $summary = new EvalSummary(3, 2, $failures);

        $this->assertSame(1, $summary->getFailed());
        $this->assertEqualsWithDelta(0.666, $summary->getPassRate(), 0.01);
        $this->assertFalse($summary->isAllPassed());
        $this->assertCount(1, $summary->getFailures());
    }

    public function test_eval_summary_to_string(): void
    {
        $failures = [
            ['index' => 1, 'input' => 'Capital of Japan?', 'error' => 'Expected Tokyo'],
        ];
        $summary = new EvalSummary(3, 2, $failures);

        $output = $summary->toString();

        $this->assertStringContainsString('2/3 passed', $output);
        $this->assertStringContainsString('FAIL [1]', $output);
        $this->assertStringContainsString('Capital of Japan', $output);
    }
}
