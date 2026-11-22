<?php

declare(strict_types=1);

// Endpoint JSON kecil untuk badge notifikasi (dipanggil kafe.js).
require __DIR__ . '/../layout.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

$u = penggunaSekarang();
if (!$u || !in_array($u['peran'], ['admin', 'owner'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'butuh masuk sebagai admin']);
    exit;
}

$baru = hitungNotifikasiBaru();
$st = db()->query('SELECT id, jenis, judul FROM notifikasi WHERE dibaca = 0 ORDER BY id DESC LIMIT 1');
$akhir = $st->fetch() ?: null;

echo json_encode(['baru' => $baru, 'terakhir' => $akhir], JSON_UNESCAPED_UNICODE);
