<?php

declare(strict_types=1);

// Jalankan: php tests/runner_json.php
// Mengeluarkan hasil tes sebagai JSON (dipakai halaman web tests/hasil-tes.html
// dan tetapan CLI). Dipisah dari run.php supaya run.php tetap ramah terminal.

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/harness.php';

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    require $file;
}

$results = [];
$passed = 0;
$failed = 0;

foreach ($GLOBALS['__tests'] as [$name, $fn]) {
    $start = microtime(true);
    try {
        $fn();
        $passed++;
        $results[] = [
            'name' => $name,
            'ok' => true,
            'message' => '',
            'ms' => round((microtime(true) - $start) * 1000, 2),
        ];
    } catch (Throwable $e) {
        $failed++;
        $results[] = [
            'name' => $name,
            'ok' => false,
            'message' => $e->getMessage(),
            'ms' => round((microtime(true) - $start) * 1000, 2),
        ];
    }
}

$payload = [
    'php' => PHP_VERSION,
    'gd' => function_exists('imagecreatetruecolor') && function_exists('imagepng'),
    'total' => $passed + $failed,
    'passed' => $passed,
    'failed' => $failed,
    'durationMs' => 0.0,
    'results' => $results,
];

if (PHP_SAPI === 'cli') {
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
    exit($failed === 0 ? 0 : 1);
}

$payload['durationMs'] = round(array_sum(array_column($results, 'ms')), 2);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
