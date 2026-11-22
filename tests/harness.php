<?php

declare(strict_types=1);

// Mini test runner tanpa dependensi (PHPUnit tidak dibutuhkan).

use QrisDinamis\QrisException;

/** @var list<array{0: string, 1: callable}> $GLOBALS['__tests'] */
$GLOBALS['__tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = [$name, $fn];
}

function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionError(
            ($message !== '' ? $message . "\n" : '')
            . '  diharapkan: ' . var_export($expected, true) . "\n"
            . '  didapat   : ' . var_export($actual, true)
        );
    }
}

function assertTrue(bool $condition, string $message = ''): void
{
    if (!$condition) {
        throw new AssertionError($message !== '' ? $message : 'Kondisi seharusnya true.');
    }
}

/** Pastikan $fn melempar QrisException dengan kode tertentu; kembalikan exception-nya. */
function assertQrisError(string $code, callable $fn): QrisException
{
    try {
        $fn();
    } catch (QrisException $e) {
        assertSame($code, $e->errorCode, 'Kode error salah. Pesan: ' . $e->getMessage());
        assertTrue($e->getMessage() !== '', 'Pesan error tidak boleh kosong.');
        return $e;
    }
    throw new AssertionError("Seharusnya melempar QrisException {$code}, tetapi tidak ada error.");
}
