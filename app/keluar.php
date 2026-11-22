<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValid()) {
    $_SESSION = [];
    session_destroy();
    header('Location: /app/masuk.php');
    exit;
}

header('Location: /app/');
