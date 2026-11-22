<?php

declare(strict_types=1);

// Autoloader sederhana supaya library jalan tanpa Composer:
//   require __DIR__ . '/src/autoload.php';
spl_autoload_register(static function (string $class): void {
    $prefix = 'QrisDinamis\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
