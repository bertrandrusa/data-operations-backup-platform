<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/src/Security.php';
require dirname(__DIR__) . '/app/src/helpers.php';

use DataOps\Security;

$tests = [];
$test = static function (string $name, callable $callback) use (&$tests): void {
    $tests[] = [$name, $callback];
};

$test('accepts a version 4 UUID', static function (): void {
    assertTrue(Security::validUuid('11111111-1111-4111-8111-111111111111'));
});

$test('rejects malformed UUIDs', static function (): void {
    assertFalse(Security::validUuid('../not-a-job'));
});

$test('accepts paths within an allowed root', static function (): void {
    assertTrue(Security::pathInside('/data/source/reports', '/data/source'));
    assertTrue(Security::pathInside('/data/source', '/data/source'));
});

$test('rejects traversal outside an allowed root', static function (): void {
    assertFalse(Security::pathInside('/data/source/../../etc', '/data/source'));
    assertFalse(Security::pathInside('/data/source-other', '/data/source'));
    assertFalse(Security::pathInside('relative/path', '/data/source'));
});

$test('formats byte counts for operators', static function (): void {
    assertSame('0 B', formatBytes(0));
    assertSame('1.0 KB', formatBytes(1024));
    assertSame('1.5 MB', formatBytes(1572864));
});

$test('normalizes PostgreSQL-style boolean values', static function (): void {
    assertTrue(asBool(true));
    assertTrue(asBool('t'));
    assertFalse(asBool(false));
    assertFalse(asBool('f'));
});

function assertTrue(bool $value): void
{
    if (!$value) {
        throw new RuntimeException('Expected true.');
    }
}

function assertFalse(bool $value): void
{
    if ($value) {
        throw new RuntimeException('Expected false.');
    }
}

function assertSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf('Expected %s, received %s.', var_export($expected, true), var_export($actual, true)));
    }
}

$passed = 0;
foreach ($tests as [$name, $callback]) {
    try {
        $callback();
        $passed++;
        echo "✓ {$name}\n";
    } catch (Throwable $error) {
        fwrite(STDERR, "✗ {$name}: {$error->getMessage()}\n");
        exit(1);
    }
}

echo "\n{$passed} tests passed.\n";
