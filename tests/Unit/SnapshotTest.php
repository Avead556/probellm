<?php

declare(strict_types=1);

namespace ProbeLLM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProbeLLM\Exception\ConfigurationException;
use ProbeLLM\Snapshot\SnapshotStore;

class SnapshotTest extends TestCase
{
    private string $tmpDir;
    private SnapshotStore $store;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/probellm-snapshots-' . uniqid();
        $this->store = new SnapshotStore($this->tmpDir);
    }

    protected function tearDown(): void
    {
        // Clean up.
        if (is_dir($this->tmpDir)) {
            array_map('unlink', glob($this->tmpDir . '/*') ?: []);
            rmdir($this->tmpDir);
        }
    }

    public function test_save_and_load(): void
    {
        $this->store->save('greeting', 'Hello, world!');

        $this->assertTrue($this->store->has('greeting'));
        $this->assertSame('Hello, world!', $this->store->load('greeting'));
    }

    public function test_has_returns_false_for_missing(): void
    {
        $this->assertFalse($this->store->has('nonexistent'));
    }

    public function test_load_throws_on_missing(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Snapshot not found');

        $this->store->load('nonexistent');
    }

    public function test_save_overwrites(): void
    {
        $this->store->save('key', 'first');
        $this->store->save('key', 'second');

        $this->assertSame('second', $this->store->load('key'));
    }

    public function test_delete(): void
    {
        $this->store->save('key', 'value');
        $this->assertTrue($this->store->has('key'));

        $this->store->delete('key');
        $this->assertFalse($this->store->has('key'));
    }

    public function test_delete_nonexistent_is_safe(): void
    {
        // Should not throw.
        $this->store->delete('nonexistent');
        $this->addToAssertionCount(1);
    }

    public function test_key_sanitization(): void
    {
        $this->store->save('Class::method/turn:0', 'content');

        $this->assertTrue($this->store->has('Class::method/turn:0'));
        $this->assertSame('content', $this->store->load('Class::method/turn:0'));
    }

    public function test_creates_directory_if_missing(): void
    {
        $deepDir = $this->tmpDir . '/nested/deep';
        $store = new SnapshotStore($deepDir);

        $store->save('test', 'content');

        $this->assertTrue(is_dir($deepDir));
        $this->assertSame('content', $store->load('test'));

        // Clean up nested dirs.
        unlink($deepDir . '/test.txt');
        rmdir($deepDir);
        rmdir($this->tmpDir . '/nested');
    }
}
