<?php

/**
 * Pest function stubs for PHPStan / IDE support.
 *
 * These stubs allow static analysis to understand Pest's global functions
 * without requiring Pest as a dependency.
 */

/**
 * @return mixed
 */
function test(string $description = '', ?\Closure $closure = null): mixed {}

/**
 * @return mixed
 */
function it(string $description = '', ?\Closure $closure = null): mixed {}

/**
 * @return mixed
 */
function expect(mixed $value = null): mixed {}
