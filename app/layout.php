<?php

declare(strict_types=1);

// Helper tampilan bersama: header/nav sesuai peran, badge notifikasi.
require_once __DIR__ . '/bootstrap.php';

function penggunaSekarang(): ?array
{
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM pengguna WHERE id = ?');
    $st->execute([(int) $_SESSION['uid']]);
    $u = $st->fetch();
    return $u ?: null;
}

function wajibPeran(string ...$peran): array
{
    $u = penggunaSekarang();
    if (!$u || !in_array($u['peran'], $peran, true)) {
        header('Location: /app/masuk.php');
        exit;
    }
    return $u;
}

function kepala(string $judul): void
{
    $u = penggunaSekarang();
    $toko = setting('nama_toko');
    $baru = $u && in_array($u['peran'], ['admin', 'owner'], true) ? hitungNotifikasiBaru() : 0;
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?> — <?= e($toko) ?></title>
<link rel="stylesheet" href="/app/assets/kafe.css">
</head>
<body>
<header class="nav">
  <div class="navin">
    <a class="brand" href="/app/"><?= e($toko) ?></a>
    <nav class="links">
      <a href="/app/">Menu</a>
      <?php if ($u): ?>
        <?php if ($u['peran'] === 'pembeli' || $u['peran'] === 'admin' || $u['peran'] === 'owner'): ?>
          <a href="/app/pesanan-saya.php">Pesanan Saya</a>
        <?php endif; ?>
        <?php if (in_array($u['peran'], ['admin', 'owner'], true)): ?>
          <a href="/app/admin/">Dapur</a>
          <a href="/app/owner/">Owner</a>
          <a href="/app/owner/ulasan.php">Ulasan</a>
        <?php endif; ?>
        <a href="/app/akun.php">Akun</a>
      <?php endif; ?>
    </nav>
    <div class="navright">
      <?php if (in_array($u['peran'] ?? '', ['admin', 'owner'], true)): ?>
        <a class="bell" href="/app/admin/notifikasi.php" title="Notifikasi">
          &#128276;<?php if ($baru > 0): ?><span class="dot"><?= $baru ?></span><?php endif; ?>
        </a>
      <?php endif; ?>
      <?php if ($u): ?>
        <span class="who"><?= e($u['nama']) ?> <small>(<?= e($u['peran']) ?>)</small></span>
        <form method="post" action="/app/keluar.php" style="display:inline">
          <?= csrfField() ?>
          <button class="ghost mini" type="submit">Keluar</button>
        </form>
      <?php else: ?>
        <a class="btn mini" href="/app/masuk.php">Masuk</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main class="wrap">
    <?php
}

function kaki(): void
{
    ?>
</main>
<footer class="foot">
  <?= e(setting('nama_toko')) ?> · <?= e(setting('alamat')) ?>, <?= e(setting('kota')) ?> <?= e(setting('kode_pos')) ?>
  <br><small>Demo ujian — pembayaran disimulasikan, bukan transaksi nyata.</small>
</footer>
<script src="/app/assets/kafe.js"></script>
</body>
</html>
    <?php
}

function badgeStatus(string $status): string
{
    $map = [
        'menunggu' => ['wait', 'menunggu bayar'],
        'dbayar'   => ['ok', 'dibayar'],
        'diproses' => ['info', 'diproses'],
        'siap'     => ['info', 'siap'],
        'selesai'  => ['ok', 'selesai'],
        'batal'    => ['off', 'batal'],
    ];
    [$cls, $teks] = $map[$status] ?? ['off', $status];
    return '<span class="badge ' . $cls . '">' . e($teks) . '</span>';
}
