<?php

declare(strict_types=1);

// Jalankan: php tests/run.php   (exit code 0 = semua lulus)

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/harness.php';

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    require $file;
}

$passed = 0;
$failed = 0;

foreach ($GLOBALS['__tests'] as [$name, $fn]) {
    try {
        $fn();
        $passed++;
        echo "  \033[32m✓\033[0m {$name}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  \033[31m✗\033[0m {$name}\n    " . str_replace("\n", "\n    ", $e->getMessage()) . "\n";
    }
}

echo "\n{$passed} lulus, {$failed} gagal\n";
exit($failed === 0 ? 0 : 1);
